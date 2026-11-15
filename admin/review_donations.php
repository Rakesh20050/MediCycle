<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$justAccepted = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['accepted'])) {
    $justAccepted = (bool) ($_SESSION['donation_accepted'] ?? false);
    unset($_SESSION['donation_accepted']);
}

$reviewError = (string) ($_SESSION['donation_review_error'] ?? '');
unset($_SESSION['donation_review_error']);

$pending   = [];
$listError = null;

try {
    $pending = $pdo->query(
        "SELECT d.donation_id, d.donation_date,
                dn.donor_name, dn.email, dn.phone,
                m.medicine_name, b.batch_number, b.quantity_received
         FROM DONATION d
         JOIN DONOR dn ON dn.donor_id = d.donor_id
         LEFT JOIN MEDICINE_BATCH b ON b.donation_id = d.donation_id
         LEFT JOIN MEDICINE m ON m.medicine_id = b.medicine_id
         WHERE d.donation_status = 'Pending'
         ORDER BY d.donation_date ASC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle review donations] ' . $e->getMessage());
    $listError = pdo_error_message($e);
}

$pageTitle    = 'Review Donations';
$pageSubtitle = 'Accept pending donations and add their batches to stock';
$activePage   = 'donations';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($listError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($listError) ?>
    </div>
<?php endif; ?>

<?php if (!$pending): ?>
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card text-center">
                <div class="card-body py-5">
                    <i class="fa-regular fa-circle-check fa-3x text-success mb-3"></i>
                    <h5 class="fw-semibold">No pending donations to review.</h5>
                    <p class="text-muted mb-0">New donor submissions will appear here for review.</p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <span>
                <i class="fa-solid fa-hourglass-half me-2 text-warning"></i>Pending donations
                <span class="badge rounded-pill badge-soft-warning ms-1"><?= count($pending) ?></span>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="pendingDonationsReviewTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Donor</th>
                        <th>Contact</th>
                        <th>Medicine / Batch</th>
                        <th>Donated On</th>
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
                            <td>
                                <div><?= htmlspecialchars($row['medicine_name'] ?? '—') ?></div>
                                <small class="text-muted">
                                    Batch <?= htmlspecialchars($row['batch_number'] ?? '—') ?>
                                    · <?= (int) ($row['quantity_received'] ?? 0) ?> units
                                </small>
                            </td>
                            <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['donation_date']))) ?></td>
                            <td class="text-end">
                                <a href="create_batch.php?donation_id=<?= (int) $row['donation_id'] ?>"
                                   class="btn btn-primary btn-sm text-nowrap">
                                    <i class="fa-solid fa-boxes-stacked me-1"></i>Accept &amp; Create Batch
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($justAccepted): ?>
    Swal.fire({
        icon: 'success',
        title: 'Donation accepted',
        text: 'The batch quantity is now available in stock.',
        confirmButtonText: 'Great',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    }).then(function () {
        history.replaceState({}, '', 'review_donations.php');
    });
    <?php elseif ($reviewError !== ''): ?>
    Swal.fire({
        icon: 'error',
        title: 'Donation not accepted',
        text: <?= json_encode($reviewError) ?>,
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
