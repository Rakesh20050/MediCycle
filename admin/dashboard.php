<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$dashError           = null;
$totalDonors         = 0;
$totalOrganizations  = 0;
$totalAvailableStock = 0;
$pendingRequestCount = 0;
$pendingDonations    = [];
$pendingRequests     = [];

try {
    $totalDonors = (int) $pdo->query('SELECT COUNT(*) FROM DONOR')->fetchColumn();

    $totalOrganizations = (int) $pdo->query('SELECT COUNT(*) FROM ORGANIZATION')->fetchColumn();

    $totalAvailableStock = (int) $pdo->query(
        'SELECT COALESCE(SUM(quantity_available), 0) FROM MEDICINE_BATCH'
    )->fetchColumn();

    $pendingRequestCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM MEDICINE_REQUEST WHERE request_status = 'Pending'"
    )->fetchColumn();

    $pendingDonations = $pdo->query(
        "SELECT d.donation_id, d.donation_date,
                dn.donor_name, dn.email, dn.phone
         FROM DONATION d
         JOIN DONOR dn ON dn.donor_id = d.donor_id
         WHERE d.donation_status = 'Pending'
         ORDER BY d.donation_date DESC, d.donation_id DESC
         LIMIT 10"
    )->fetchAll();

    $pendingRequests = $pdo->query(
        "SELECT r.request_id, r.request_date,
                o.organization_name, o.organization_type, o.email, o.contact
         FROM MEDICINE_REQUEST r
         JOIN ORGANIZATION o ON o.organization_id = r.organization_id
         WHERE r.request_status = 'Pending'
         ORDER BY r.request_date DESC, r.request_id DESC
         LIMIT 10"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle admin dashboard] ' . $e->getMessage());
    $dashError = pdo_error_message($e);
}

$pageTitle    = 'Admin Dashboard';
$pageSubtitle = 'A snapshot of donors, organizations, stock and pending work';
$activePage   = 'dashboard';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($dashError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($dashError) ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="fa-solid fa-users"></i></span>
                <div>
                    <div class="text-muted small">Total Donors</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format($totalDonors) ?></div>
                    <small class="text-muted">Registered in the system</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="fa-solid fa-building-circle-check"></i></span>
                <div>
                    <div class="text-muted small">Total Organizations</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format($totalOrganizations) ?></div>
                    <small class="text-muted">Hospitals, NGOs and clinics</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                <div>
                    <div class="text-muted small">Total Available Stock</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format($totalAvailableStock) ?></div>
                    <small class="text-muted">Units ready to distribute</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon stat-icon-warning"><i class="fa-solid fa-clipboard-list"></i></span>
                <div>
                    <div class="text-muted small">Pending Requests</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format($pendingRequestCount) ?></div>
                    <small class="text-muted">
                        <a href="review_requests.php" class="text-decoration-none">Open review queue</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>
            <i class="fa-solid fa-hand-holding-heart me-2 text-primary"></i>Pending donations
        </span>
        <a href="verify_donation.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>Verification queue
        </a>
    </div>

    <?php if (!$pendingDonations): ?>
        <div class="card-body">
            <div class="alert alert-info mb-0">
                <i class="fa-solid fa-circle-info me-1"></i>No pending data found.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="pendingDonationsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Donor</th>
                        <th>Contact</th>
                        <th>Donated on</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingDonations as $row): ?>
                        <tr>
                            <td class="fw-semibold">#<?= (int) $row['donation_id'] ?></td>
                            <td><?= htmlspecialchars($row['donor_name']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($row['email']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['phone']) ?></small>
                            </td>
                            <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['donation_date']))) ?></td>
                            <td class="text-end">
                                <a href="verify_donation.php" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-check me-1"></i>Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-4">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>
            <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Pending requests
        </span>
        <a href="review_requests.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>Request queue
        </a>
    </div>

    <?php if (!$pendingRequests): ?>
        <div class="card-body">
            <div class="alert alert-info mb-0">
                <i class="fa-solid fa-circle-info me-1"></i>No pending data found.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="pendingRequestsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Organization</th>
                        <th>Contact</th>
                        <th>Requested on</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRequests as $row): ?>
                        <tr>
                            <td class="fw-semibold">#<?= (int) $row['request_id'] ?></td>
                            <td>
                                <div><?= htmlspecialchars($row['organization_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['organization_type']) ?></small>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($row['email']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['contact']) ?></small>
                            </td>
                            <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['request_date']))) ?></td>
                            <td class="text-end">
                                <a href="review_requests.php" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-check me-1"></i>Review
                                </a>
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
    if (!window.jQuery || !jQuery.fn.DataTable) {
        return;
    }

    if (jQuery('#pendingDonationsTable').length) {
        jQuery('#pendingDonationsTable').DataTable({
            pageLength: 10,
            order: [],
            columnDefs: [{ orderable: false, targets: [2, 4] }]
        });
    }

    if (jQuery('#pendingRequestsTable').length) {
        jQuery('#pendingRequestsTable').DataTable({
            pageLength: 10,
            order: [],
            columnDefs: [{ orderable: false, targets: [2, 4] }]
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
