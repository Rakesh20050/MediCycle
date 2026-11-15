<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_organization();

$organizationId = (int) $_SESSION['organization_id'];

$dashError    = null;
$requestStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$received     = ['units' => 0, 'distributions' => 0];
$recentRequests = [];

try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(request_status = 'Pending'), 0)  AS pending,
                COALESCE(SUM(request_status = 'Approved'), 0) AS approved,
                COALESCE(SUM(request_status = 'Rejected'), 0) AS rejected
         FROM MEDICINE_REQUEST
         WHERE organization_id = ?"
    );
    $stmt->execute([$organizationId]);
    $requestStats = $stmt->fetch();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(di.distributed_quantity), 0) AS units,
                COUNT(DISTINCT d.distribution_id) AS distributions
         FROM DISTRIBUTION d
         JOIN MEDICINE_REQUEST r ON r.request_id = d.request_id
         JOIN DISTRIBUTION_ITEM di ON di.distribution_id = d.distribution_id
         WHERE r.organization_id = ?
           AND d.distribution_status = 'Completed'"
    );
    $stmt->execute([$organizationId]);
    $received = $stmt->fetch();

    $stmt = $pdo->prepare(
        "SELECT r.request_id, r.request_date, r.request_status,
                COUNT(ri.request_item_id) AS item_count,
                COALESCE(SUM(ri.requested_quantity), 0) AS total_quantity,
                (SELECT d.distribution_status
                 FROM DISTRIBUTION d
                 WHERE d.request_id = r.request_id
                 ORDER BY d.distribution_id DESC
                 LIMIT 1) AS distribution_status
         FROM MEDICINE_REQUEST r
         LEFT JOIN REQUEST_ITEM ri ON ri.request_id = r.request_id
         WHERE r.organization_id = ?
         GROUP BY r.request_id, r.request_date, r.request_status
         ORDER BY r.request_date DESC, r.request_id DESC
         LIMIT 20"
    );
    $stmt->execute([$organizationId]);
    $recentRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle organization dashboard] ' . $e->getMessage());
    $dashError = pdo_error_message($e);
}

$pageTitle    = 'Organization Dashboard';
$pageSubtitle = 'Track your medicine requests and received stock';
$activePage   = 'dashboard';

$statusClass = static fn (string $status): string => match ($status) {
    'Approved', 'Completed' => 'badge-soft-success',
    'Rejected', 'Cancelled' => 'badge-soft-danger',
    default                 => 'badge-soft-warning',
};

include __DIR__ . '/../includes/header.php';
?>

<?php if ($dashError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($dashError) ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></span>
                <div>
                    <div class="text-muted small">Total requests</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format((int) $requestStats['total']) ?></div>
                    <small class="text-muted">
                        <?= (int) $requestStats['approved'] ?> approved
                        · <?= (int) $requestStats['rejected'] ?> rejected
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon stat-icon-warning"><i class="fa-solid fa-hourglass-half"></i></span>
                <div>
                    <div class="text-muted small">Awaiting approval</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format((int) $requestStats['pending']) ?></div>
                    <small class="text-muted">
                        <a href="request.php" class="text-decoration-none">Create a new request</a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="fa-solid fa-pills"></i></span>
                <div>
                    <div class="text-muted small">Medicines received</div>
                    <div class="fs-3 fw-bold lh-sm"><?= number_format((int) $received['units']) ?></div>
                    <small class="text-muted">
                        units across <?= (int) $received['distributions'] ?>
                        <?= (int) $received['distributions'] === 1 ? 'distribution' : 'distributions' ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>
            <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent requests
            <span class="badge rounded-pill badge-soft-info ms-1">latest 20</span>
        </span>
        <a href="request.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-circle-plus me-1"></i>New request
        </a>
    </div>

    <?php if (!$recentRequests): ?>
        <div class="empty-state">
            <i class="fa-regular fa-folder-open"></i>
            <h5>No requests yet</h5>
            <p>Request medicines and track the approval and delivery here.</p>
            <a href="request.php" class="btn btn-primary btn-sm mt-2">
                <i class="fa-solid fa-circle-plus me-1"></i>Create your first request
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="organizationRequestsTable">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Requested on</th>
                        <th>Items</th>
                        <th>Units requested</th>
                        <th>Status</th>
                        <th>Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRequests as $row): ?>
                        <tr>
                            <td class="fw-semibold">#<?= (int) $row['request_id'] ?></td>
                            <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['request_date']))) ?></td>
                            <td><?= (int) $row['item_count'] ?></td>
                            <td><?= number_format((int) $row['total_quantity']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= $statusClass($row['request_status']) ?>">
                                    <?= htmlspecialchars($row['request_status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['distribution_status'] !== null): ?>
                                    <span class="badge rounded-pill <?= $statusClass($row['distribution_status']) ?>">
                                        <?= htmlspecialchars($row['distribution_status']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
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
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#organizationRequestsTable').length) {
        jQuery('#organizationRequestsTable').DataTable({
            pageLength: 10,
            order: [],
            columnDefs: [{ orderable: false, targets: [2, 3, 4, 5] }]
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
