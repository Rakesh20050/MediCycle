<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$actionError = null;
$pending     = [];

// Accept a donation (POST only, guarded so it can only move Pending -> Accepted).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'accept') {
    $donationId = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);

    if (!$donationId) {
        $actionError = 'Invalid donation selected.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "UPDATE DONATION
                 SET donation_status = 'Accepted', verified_by = ?
                 WHERE donation_id = ? AND donation_status = 'Pending'"
            );
            $stmt->execute([(int) $_SESSION['admin_id'], $donationId]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $actionError = 'That donation has already been reviewed.';
            } else {
                $batchStmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM MEDICINE_BATCH WHERE donation_id = ?'
                );
                $batchStmt->execute([$donationId]);
                $hasBatch = (int) $batchStmt->fetchColumn() > 0;

                if ($hasBatch) {
                    $release = $pdo->prepare(
                        'UPDATE MEDICINE_BATCH
                         SET quantity_available = quantity_received
                         WHERE donation_id = ?'
                    );
                    $release->execute([$donationId]);
                }

                $pdo->commit();

                if ($hasBatch) {
                    header('Location: verify_donation.php?accepted=1');
                } else {
                    header('Location: create_batch.php?donation_id=' . $donationId);
                }
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[MediCycle verify donation] ' . $e->getMessage());
            $actionError = pdo_error_message($e);
        }
    }
}

try {
    $pending = $pdo->query(
        "SELECT d.donation_id, d.donation_date, d.donation_status,
                dn.donor_name, dn.email, dn.phone, dn.address
         FROM DONATION d
         JOIN DONOR dn ON dn.donor_id = d.donor_id
         WHERE d.donation_status = 'Pending'
         ORDER BY d.donation_date ASC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle verify donation] ' . $e->getMessage());
    $actionError = $actionError ?? pdo_error_message($e);
}

$pageTitle    = 'Verify Donations';
$pageSubtitle = 'Approve incoming medicine donations and release their batches to stock';
$activePage   = 'donations';

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <span>
            <i class="fa-solid fa-hourglass-half me-2 text-warning"></i>Pending donations
            <span class="badge rounded-pill badge-soft-warning ms-1"><?= count($pending) ?></span>
        </span>
    </div>

    <?php if (!$pending): ?>
        <div class="empty-state">
            <i class="fa-regular fa-circle-check"></i>
            <h5>All caught up</h5>
            <p>There are no pending donations to verify right now.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="pendingDonationsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Donor</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Donated On</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $row): ?>
                        <tr>
                            <td class="fw-semibold">#<?= (int) $row['donation_id'] ?></td>
                            <td><?= htmlspecialchars($row['donor_name']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($row['email']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['phone']) ?></small>
                            </td>
                            <td><small><?= htmlspecialchars($row['address']) ?></small></td>
                            <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['donation_date']))) ?></td>
                            <td><span class="badge rounded-pill badge-soft-warning">Pending</span></td>
                            <td class="text-end">
                                <form method="post" action="verify_donation.php" class="d-inline">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="donation_id" value="<?= (int) $row['donation_id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-check me-1"></i>Accept
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#pendingDonationsTable').length) {
        jQuery('#pendingDonationsTable').DataTable({
            pageLength: 10,
            order: [[4, 'asc']],
            columnDefs: [{ orderable: false, targets: [3, 6] }]
        });
    }

    <?php if ($actionError !== null): ?>
    Swal.fire({
        icon: 'error',
        title: 'Cannot accept donation',
        text: <?= json_encode($actionError) ?>,
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
    <?php elseif (isset($_GET['accepted'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Donation accepted',
        text: 'The donated batch is now available in stock.',
        confirmButtonText: 'Continue',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
    <?php elseif (isset($_GET['created'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Batch created',
        text: 'The medicine batch has been added to stock successfully.',
        confirmButtonText: 'Continue',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
