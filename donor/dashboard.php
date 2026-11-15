<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_donor();

$donorId   = (int) $_SESSION['donor_id'];
$donorName = (string) ($_SESSION['user_name'] ?? 'Donor');

$dashError       = null;
$donationStats   = ['total' => 0, 'pending' => 0, 'accepted' => 0];
$recentDonations = [];

try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(donation_status = 'Pending'), 0)  AS pending,
                COALESCE(SUM(donation_status = 'Accepted'), 0) AS accepted
         FROM DONATION
         WHERE donor_id = ?"
    );
    $stmt->execute([$donorId]);
    $donationStats = $stmt->fetch();

    // LEFT JOIN keeps pending donations visible even before the admin records their batches.
    $stmt = $pdo->prepare(
        'SELECT d.donation_id, d.donation_date, d.donation_status,
                m.medicine_name, b.quantity_received
         FROM DONATION d
         LEFT JOIN MEDICINE_BATCH b ON b.donation_id = d.donation_id
         LEFT JOIN MEDICINE m ON m.medicine_id = b.medicine_id
         WHERE d.donor_id = ?
         ORDER BY d.donation_date DESC, d.donation_id DESC, b.batch_id ASC
         LIMIT 100'
    );
    $stmt->execute([$donorId]);
    $recentDonations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle donor dashboard] ' . $e->getMessage());
    $dashError = pdo_error_message($e);
}

$pageTitle    = 'Donor Dashboard';
$pageSubtitle = 'Track your donations and their verification status';
$activePage   = 'dashboard';

$statusClass = static fn (string $status): string => match ($status) {
    'Accepted' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    default    => 'badge-soft-warning',
};

include __DIR__ . '/../includes/header.php';
?>

<?php if ($dashError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($dashError) ?>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h4 mb-1">Welcome back, <?= htmlspecialchars($donorName) ?></h2>
        <p class="text-muted mb-0">Here is how your medicine donations are progressing.</p>
    </div>
    <a href="donate.php" class="btn btn-primary">
        <i class="fa-solid fa-hand-holding-heart me-2"></i>Donate Medicine Now
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="fa-solid fa-hand-holding-heart"></i></span>
                <div>
                    <div class="text-muted small">Total Donations</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format((int) $donationStats['total']) ?></div>
                    <small class="text-muted">All submissions from your account</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon stat-icon-warning"><i class="fa-solid fa-hourglass-half"></i></span>
                <div>
                    <div class="text-muted small">Pending Donations</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format((int) $donationStats['pending']) ?></div>
                    <small class="text-muted">Awaiting administrator review</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="fa-solid fa-circle-check"></i></span>
                <div>
                    <div class="text-muted small">Accepted Donations</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format((int) $donationStats['accepted']) ?></div>
                    <small class="text-muted">Verified and added to stock</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>
            <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent donation history
            <span class="badge rounded-pill badge-soft-info ms-1">latest 100 entries</span>
        </span>
    </div>

    <?php if (!$recentDonations): ?>
        <div class="empty-state">
            <i class="fa-solid fa-hand-holding-heart"></i>
            <h5>No donations yet</h5>
            <p>Submit your first donation and we will take it from there.</p>
            <a href="donate.php" class="btn btn-primary btn-sm mt-2">
                <i class="fa-solid fa-hand-holding-heart me-1"></i>Donate Medicine Now
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="donorDonationsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Medicine Name</th>
                        <th>Quantity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDonations as $row): ?>
                        <tr>
                            <td class="text-nowrap" data-order="<?= (int) strtotime($row['donation_date']) ?>">
                                <?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['donation_date']))) ?>
                            </td>
                            <td>
                                <?php if ($row['medicine_name'] !== null): ?>
                                    <?= htmlspecialchars($row['medicine_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Not recorded yet</span>
                                <?php endif; ?>
                            </td>
                            <td<?= $row['quantity_received'] !== null ? ' data-order="' . (int) $row['quantity_received'] . '"' : '' ?>>
                                <?= $row['quantity_received'] !== null ? number_format((int) $row['quantity_received']) : '—' ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $statusClass($row['donation_status']) ?>">
                                    <?= htmlspecialchars($row['donation_status']) ?>
                                </span>
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
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#donorDonationsTable').length) {
        jQuery('#donorDonationsTable').DataTable({
            pageLength: 10,
            order: [],
            columnDefs: [{ orderable: false, targets: [1, 2, 3] }]
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
