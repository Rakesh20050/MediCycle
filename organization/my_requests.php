<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_organization();

$organizationId     = (int) $_SESSION['organization_id'];
$verificationStatus = '';

try {
    $statusStmt = $pdo->prepare(
        'SELECT verification_status
         FROM ORGANIZATION
         WHERE organization_id = ?
         LIMIT 1'
    );
    $statusStmt->execute([$organizationId]);
    $verificationStatus = (string) $statusStmt->fetchColumn();
} catch (PDOException $e) {
    error_log('[MediCycle my requests verification] ' . $e->getMessage());
}

if ($verificationStatus !== 'Verified') {
    $pageTitle     = 'My Requests';
    $pageSubtitle  = 'Track the medicines you have requested';
    $activePage    = 'my-requests';
    $statusDisplay = $verificationStatus !== '' ? $verificationStatus : 'unverified';

    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="access-wrap">
        <div class="card access-card">
            <div class="card-body text-center py-5 px-4">
                <div class="access-icon">
                    <i class="fa-solid fa-ban"></i>
                </div>
                <span class="badge rounded-pill <?= $verificationStatus === 'Rejected' ? 'badge-soft-danger' : 'badge-soft-warning' ?> mb-3">
                    <?= htmlspecialchars($statusDisplay) ?>
                </span>
                <h2 class="h4 fw-bold mb-2">Access Restricted</h2>
                <p class="text-muted mb-4">
                    Your account is currently <?= htmlspecialchars($statusDisplay) ?>.
                    You cannot view or make requests until an Admin verifies your organization.
                </p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fa-solid fa-arrow-left me-2"></i>Return to Dashboard
                </a>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$requests    = [];
$tableError  = null;

try {
    $stmt = $pdo->prepare(
        'SELECT r.request_id, r.request_date, r.request_status,
                m.medicine_name, ri.requested_quantity
         FROM MEDICINE_REQUEST r
         JOIN REQUEST_ITEM ri ON r.request_id = ri.request_id
         JOIN MEDICINE m ON ri.medicine_id = m.medicine_id
         WHERE r.organization_id = ?
         ORDER BY r.request_date DESC'
    );
    $stmt->execute([$organizationId]);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle my requests] ' . $e->getMessage());
    $tableError = pdo_error_message($e);
}

$requestCount = count(array_unique(array_column($requests, 'request_id')));

$pageTitle    = 'My Requests';
$pageSubtitle = 'Everything your organization has requested, in one place';
$activePage   = 'my-requests';

$statusClass = static fn (string $status): string => match ($status) {
    'Approved' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    default    => 'badge-soft-warning',
};

include __DIR__ . '/../includes/header.php';
?>

<?php if ($tableError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($tableError) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>
            <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>My requests
            <span class="badge rounded-pill badge-soft-info ms-1">
                <?= $requestCount ?> <?= $requestCount === 1 ? 'request' : 'requests' ?>
            </span>
        </span>
        <a href="request.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-circle-plus me-1"></i>New Request
        </a>
    </div>

    <?php if (!$requests && $tableError === null): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clipboard-list"></i>
            <h5>No requests yet</h5>
            <p>Once you request medicines, they will be listed here with their review status.</p>
            <a href="request.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-circle-plus me-1"></i>Create your first request
            </a>
        </div>
    <?php elseif ($requests): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="myRequestsTable">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Date</th>
                        <th>Medicine Name</th>
                        <th>Quantity Requested</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td class="fw-semibold text-nowrap">#<?= (int) $request['request_id'] ?></td>
                            <td data-order="<?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($request['request_date']))) ?>">
                                <span class="text-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($request['request_date']))) ?></span>
                                <div><small class="text-muted"><?= htmlspecialchars(date('h:i A', strtotime($request['request_date']))) ?></small></div>
                            </td>
                            <td><?= htmlspecialchars($request['medicine_name']) ?></td>
                            <td class="text-nowrap"><?= number_format((int) $request['requested_quantity']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= $statusClass((string) $request['request_status']) ?>">
                                    <?= htmlspecialchars($request['request_status']) ?>
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
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#myRequestsTable').length) {
        jQuery('#myRequestsTable').DataTable({
            pageLength: 10,
            order: [[1, 'desc']],
            columnDefs: [{ orderable: false, targets: [4] }],
            language: { search: 'Search requests:' }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
