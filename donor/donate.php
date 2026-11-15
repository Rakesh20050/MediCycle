<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_donor();

$formError = null;
$old = [
    'medicine_name'      => '',
    'category'           => '',
    'batch_number'       => '',
    'manufacturing_date' => '',
    'expiry_date'        => '',
    'quantity_received'  => '',
];

$justSubmitted = false;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['submitted'])) {
    $justSubmitted = (bool) ($_SESSION['donation_submitted'] ?? false);
    unset($_SESSION['donation_submitted']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($old) as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $quantity = filter_var($old['quantity_received'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $errors = [];

    if ($old['medicine_name'] === '' || strlen($old['medicine_name']) > 150) {
        $errors[] = 'Medicine name is required (maximum 150 characters).';
    }
    if (strlen($old['category']) > 100) {
        $errors[] = 'Category is too long (maximum 100 characters).';
    }
    if ($old['batch_number'] === '' || strlen($old['batch_number']) > 50) {
        $errors[] = 'Batch number is required (maximum 50 characters).';
    }

    $mfgDate = DateTimeImmutable::createFromFormat('!Y-m-d', $old['manufacturing_date']);
    $expDate = DateTimeImmutable::createFromFormat('!Y-m-d', $old['expiry_date']);
    $mfgOk   = $mfgDate !== false && $mfgDate->format('Y-m-d') === $old['manufacturing_date'];
    $expOk   = $expDate !== false && $expDate->format('Y-m-d') === $old['expiry_date'];

    if (!$mfgOk) {
        $errors[] = 'Please enter a valid manufacturing date.';
    }
    if (!$expOk) {
        $errors[] = 'Please enter a valid expiry date.';
    }
    if ($mfgOk && $expOk && $expDate <= $mfgDate) {
        $errors[] = 'Expiry date must be later than the manufacturing date.';
    }
    if ($quantity === false) {
        $errors[] = 'Quantity received must be a positive whole number.';
    }

    try {
        $pdo->beginTransaction();

        if ($errors) {
            $pdo->rollBack();
            $formError = implode(' ', $errors);
        } else {
            $findMedicine = $pdo->prepare(
                'SELECT medicine_id
                 FROM MEDICINE
                 WHERE medicine_name = ?
                 ORDER BY medicine_id ASC
                 LIMIT 1'
            );
            $findMedicine->execute([$old['medicine_name']]);
            $existingMedicineId = $findMedicine->fetchColumn();

            if ($existingMedicineId !== false) {
                $medicineId = (int) $existingMedicineId;
            } else {
                $insertMedicine = $pdo->prepare(
                    'INSERT INTO MEDICINE (medicine_name, category, manufacturer)
                     VALUES (?, ?, ?)'
                );
                $insertMedicine->execute([
                    $old['medicine_name'],
                    $old['category'] !== '' ? $old['category'] : 'Uncategorized',
                    'Not specified',
                ]);
                $medicineId = (int) $pdo->lastInsertId();
            }

            $insertDonation = $pdo->prepare(
                "INSERT INTO DONATION (donor_id, donation_date, donation_status)
                 VALUES (?, NOW(), 'Pending')"
            );
            $insertDonation->execute([(int) $_SESSION['donor_id']]);
            $donationId = (int) $pdo->lastInsertId();

            $insertBatch = $pdo->prepare(
                'INSERT INTO MEDICINE_BATCH
                    (donation_id, medicine_id, batch_number, manufacturing_date,
                     expiry_date, quantity_received, quantity_available)
                 VALUES (?, ?, ?, ?, ?, ?, 0)'
            );
            $insertBatch->execute([
                $donationId,
                $medicineId,
                $old['batch_number'],
                $old['manufacturing_date'],
                $old['expiry_date'],
                $quantity,
            ]);

            $pdo->commit();

            $_SESSION['donation_submitted'] = true;
            header('Location: donate.php?submitted=1');
            exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[MediCycle donate] ' . $e->getMessage());
        $formError = pdo_error_message($e);
    }
}

$pageTitle    = 'Donate Medicine';
$pageSubtitle = 'Submit a medicine batch for administrator approval';
$activePage   = 'donate';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-hand-holding-heart me-2 text-primary"></i>Medicine details
            </div>
            <div class="card-body">
                <form method="post" action="donate.php" id="donationForm">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="medicine_name" class="form-label">Medicine name</label>
                            <input type="text" class="form-control" id="medicine_name" name="medicine_name"
                                   value="<?= htmlspecialchars($old['medicine_name']) ?>"
                                   placeholder="e.g., Paracetamol 500mg" maxlength="150" required autofocus>
                            <div class="form-hint">New medicines are added to the catalogue automatically.</div>
                        </div>

                        <div class="col-md-5">
                            <label for="category" class="form-label">
                                Category <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input type="text" class="form-control" id="category" name="category"
                                   value="<?= htmlspecialchars($old['category']) ?>"
                                   placeholder="e.g., Painkiller, Antibiotic" maxlength="100">
                        </div>

                        <div class="col-md-6">
                            <label for="batch_number" class="form-label">Batch number</label>
                            <input type="text" class="form-control" id="batch_number" name="batch_number"
                                   value="<?= htmlspecialchars($old['batch_number']) ?>"
                                   placeholder="e.g. PCM-2501" maxlength="50" required>
                        </div>

                        <div class="col-md-6">
                            <label for="quantity_received" class="form-label">Quantity received</label>
                            <input type="number" class="form-control" id="quantity_received" name="quantity_received"
                                   value="<?= htmlspecialchars($old['quantity_received']) ?>"
                                   placeholder="e.g. 500" min="1" step="1" required>
                        </div>

                        <div class="col-md-6">
                            <label for="manufacturing_date" class="form-label">Manufacturing date</label>
                            <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date"
                                   value="<?= htmlspecialchars($old['manufacturing_date']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="expiry_date" class="form-label">Expiry date</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date"
                                   value="<?= htmlspecialchars($old['expiry_date']) ?>" required>
                        </div>

                        <div class="col-12">
                            <p class="form-hint mb-0">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                The expiry date must be later than the manufacturing date.
                            </p>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary" id="donationSubmit">
                            <i class="fa-solid fa-paper-plane me-2"></i>Submit Donation
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fa-solid fa-list-check me-2 text-primary"></i>What happens next?
            </div>
            <div class="card-body">
                <ol class="request-steps mb-0">
                    <li>
                        <span class="step-title">Submit the batch</span>
                        <span class="step-text">Your donation is recorded as Pending.</span>
                    </li>
                    <li>
                        <span class="step-title">Administrator review</span>
                        <span class="step-text">The system administrator checks the medicine details.</span>
                    </li>
                    <li>
                        <span class="step-title">Added to stock</span>
                        <span class="step-text">Once accepted, the batch becomes available to organizations.</span>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('donationForm');
    const manufacturing = document.getElementById('manufacturing_date');
    const expiry = document.getElementById('expiry_date');
    const submitButton = document.getElementById('donationSubmit');

    <?php if ($justSubmitted): ?>
    Swal.fire({
        icon: 'success',
        title: 'Donation submitted!',
        text: 'Waiting for Admin approval.',
        confirmButtonText: 'Go to dashboard',
        confirmButtonColor: '#0d9488',
        color: '#111827',
        timer: 3200,
        timerProgressBar: true,
        allowOutsideClick: false
    }).then(function () {
        window.location.href = 'dashboard.php';
    });
    <?php elseif ($formError !== null): ?>
    Swal.fire({
        icon: 'error',
        title: 'Could not submit donation',
        text: <?= json_encode($formError) ?>,
        confirmButtonText: 'Fix and retry',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
    <?php endif; ?>

    form.addEventListener('submit', function (event) {
        if (manufacturing.value && expiry.value && expiry.value <= manufacturing.value) {
            event.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid dates',
                text: 'Expiry date must be later than the manufacturing date.',
                confirmButtonColor: '#0d9488',
                color: '#111827'
            });
            return;
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
