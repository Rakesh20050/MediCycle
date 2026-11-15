<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$donationId = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_GET, 'donation_id', FILTER_VALIDATE_INT);

if (!$donationId) {
    $_SESSION['donation_review_error'] = 'Invalid donation selected.';
    header('Location: review_donations.php');
    exit;
}

$formError = null;
$donation  = null;

try {
    $stmt = $pdo->prepare(
        "SELECT d.donation_id, d.donation_date, d.donation_status,
                dn.donor_name, dn.email, dn.phone,
                b.batch_id, b.batch_number, b.manufacturing_date, b.expiry_date,
                b.quantity_received, b.quantity_available,
                m.medicine_name, m.category, m.manufacturer
         FROM DONATION d
         JOIN DONOR dn ON dn.donor_id = d.donor_id
         JOIN MEDICINE_BATCH b ON b.donation_id = d.donation_id
         JOIN MEDICINE m ON m.medicine_id = b.medicine_id
         WHERE d.donation_id = ? AND d.donation_status = 'Pending'
         ORDER BY b.batch_id ASC
         LIMIT 1"
    );
    $stmt->execute([$donationId]);
    $donation = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[MediCycle create batch] ' . $e->getMessage());
    $formError = pdo_error_message($e);
}

if ($donation === false || $donation === null) {
    $_SESSION['donation_review_error'] = $formError
        ?? 'That donation is no longer pending or has no batch to complete.';
    header('Location: review_donations.php');
    exit;
}

$old = [
    'quantity_received' => (string) $donation['quantity_received'],
    'expiry_date'       => (string) $donation['expiry_date'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['quantity_received'] = trim((string) ($_POST['quantity_received'] ?? ''));
    $old['expiry_date']       = trim((string) ($_POST['expiry_date'] ?? ''));

    $quantity = filter_var($old['quantity_received'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    $expiry   = DateTimeImmutable::createFromFormat('!Y-m-d', $old['expiry_date']);
    $expiryOk = $expiry !== false && $expiry->format('Y-m-d') === $old['expiry_date'];

    $errors = [];

    if ($quantity === false) {
        $errors[] = 'Quantity received must be a positive whole number.';
    }
    if (!$expiryOk) {
        $errors[] = 'Please enter a valid expiry date.';
    } elseif ($old['expiry_date'] <= (string) $donation['manufacturing_date']) {
        $errors[] = 'Expiry date must be later than the manufacturing date ('
            . date('d M Y', strtotime((string) $donation['manufacturing_date'])) . ').';
    }

    if ($errors) {
        $formError = implode(' ', $errors);
    } else {
        try {
            $pdo->beginTransaction();

            $accept = $pdo->prepare(
                "UPDATE DONATION
                 SET donation_status = 'Accepted', verified_by = ?
                 WHERE donation_id = ? AND donation_status = 'Pending'"
            );
            $accept->execute([(int) $_SESSION['admin_id'], $donationId]);

            if ($accept->rowCount() !== 1) {
                $pdo->rollBack();
                $formError = 'That donation has already been reviewed.';
            } else {
                // quantity_available is capped by chk_batch_available_lte_received,
                // so the received quantity is corrected to match the verified stock.
                $release = $pdo->prepare(
                    'UPDATE MEDICINE_BATCH
                     SET expiry_date = ?, quantity_received = ?, quantity_available = ?
                     WHERE batch_id = ?'
                );
                $release->execute([
                    $old['expiry_date'],
                    $quantity,
                    $quantity,
                    (int) $donation['batch_id'],
                ]);

                $pdo->commit();

                $_SESSION['donation_accepted'] = true;
                header('Location: review_donations.php?accepted=1');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[MediCycle create batch] ' . $e->getMessage());
            $formError = pdo_error_message($e);
        }
    }
}

$pageTitle    = 'Accept Donation';
$pageSubtitle = 'Verify the donated batch and release it to stock';
$activePage   = 'donations';

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-3">
    <a href="review_donations.php" class="text-decoration-none small fw-semibold">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to pending donations
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Finalise the donated batch
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    The donor already submitted these batch details. Confirm the quantity when the
                    medicines arrive, and override the expiry date only if the label says otherwise.
                </p>

                <form method="post" action="create_batch.php" id="acceptDonationForm">
                    <input type="hidden" name="donation_id" value="<?= (int) $donation['donation_id'] ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Medicine</label>
                            <input type="text" class="form-control" id="medicine_display"
                                   value="<?= htmlspecialchars($donation['medicine_name']) ?>" readonly aria-readonly="true">
                            <div class="form-hint">
                                <?= htmlspecialchars($donation['category']) ?>
                                · <?= htmlspecialchars($donation['manufacturer']) ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Batch number</label>
                            <input type="text" class="form-control" id="batch_display"
                                   value="<?= htmlspecialchars($donation['batch_number']) ?>" readonly aria-readonly="true">
                            <div class="form-hint">
                                Manufacturing date:
                                <?= htmlspecialchars(date('d M Y', strtotime((string) $donation['manufacturing_date']))) ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="quantity_received" class="form-label fw-semibold">Quantity received</label>
                            <input type="number" class="form-control" id="quantity_received" name="quantity_received"
                                   value="<?= htmlspecialchars($old['quantity_received']) ?>"
                                   min="1" step="1" required>
                            <div class="form-hint">
                                Donor declared <?= (int) $donation['quantity_received'] ?> units.
                                This quantity becomes available stock.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="expiry_date" class="form-label fw-semibold">Expiry date</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date"
                                   value="<?= htmlspecialchars($old['expiry_date']) ?>" required>
                            <div class="form-hint">
                                Prefilled from the donation. Change it only to override the donor's value.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                        <button type="submit" class="btn btn-primary" id="acceptDonationSubmit">
                            <i class="fa-solid fa-check me-1"></i>Accept Donation &amp; Add to Stock
                        </button>
                        <a href="review_donations.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-hand-holding-heart me-2 text-primary"></i>Donation #<?= (int) $donation['donation_id'] ?>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small">Donor</div>
                    <div class="fw-semibold"><?= htmlspecialchars($donation['donor_name']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Email</div>
                    <div><?= htmlspecialchars($donation['email']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Phone</div>
                    <div><?= htmlspecialchars($donation['phone']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Donated on</div>
                    <div><?= htmlspecialchars(date('d M Y, h:i A', strtotime($donation['donation_date']))) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Current available stock</div>
                    <div><?= (int) $donation['quantity_available'] ?> units</div>
                </div>
                <div>
                    <div class="text-muted small">Status</div>
                    <span class="badge rounded-pill badge-soft-warning">Pending</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($formError !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'Donation not accepted',
        text: <?= json_encode($formError) ?>,
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
