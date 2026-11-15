<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_organization();

$organizationId = (int) $_SESSION['organization_id'];
$historyError   = null;
$history        = [];

try {
    // LEFT JOINs keep a request visible even if it somehow has no items.
    $stmt = $pdo->prepare(
        'SELECT r.request_id, r.request_date, r.request_status,
                ri.requested_quantity,
                m.medicine_name
         FROM MEDICINE_REQUEST r
         LEFT JOIN REQUEST_ITEM ri ON ri.request_id = r.request_id
         LEFT JOIN MEDICINE m     ON m.medicine_id = ri.medicine_id
         WHERE r.organization_id = ?
         ORDER BY r.request_date DESC, r.request_id DESC, m.medicine_name ASC'
    );
    $stmt->execute([$organizationId]);
    $history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle organization history] ' . $e->getMessage());
    $historyError = pdo_error_message($e);
}

$requestCount = count(array_unique(array_column($history, 'request_id')));

$pageTitle    = 'Request History';
$pageSubtitle = 'Every medicine request your organization has submitted';
$activePage   = 'history';

$statusClass = static fn (string $status): string => match ($status) {
    'Approved', 'Completed' => 'badge-soft-success',
    'Rejected', 'Cancelled' => 'badge-soft-danger',
    default                 => 'badge-soft-warning',
};

include __DIR__ . '/../includes/header.php';
?>

<?php if ($historyError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($historyError) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>
            <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Your request history
            <span class="badge rounded-pill badge-soft-info ms-1">
                <?= $requestCount ?> <?= $requestCount === 1 ? 'request' : 'requests' ?>
            </span>
        </span>
        <a href="request.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-circle-plus me-1"></i>New Request
        </a>
    </div>

    <?php if (!$history && $historyError === null): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <h5>No requests yet</h5>
            <p>Requests you submit will appear here with their review status.</p>
            <a href="request.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-circle-plus me-1"></i>Create your first request
            </a>
        </div>
    <?php elseif ($history): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="requestHistoryTable">
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
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td class="fw-semibold text-nowrap">#<?= (int) $row['request_id'] ?></td>
                            <td data-order="<?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($row['request_date']))) ?>">
                                <span class="text-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($row['request_date']))) ?></span>
                                <div><small class="text-muted"><?= htmlspecialchars(date('h:i A', strtotime($row['request_date']))) ?></small></div>
                            </td>
                            <td>
                                <?php if ($row['medicine_name'] === null): ?>
                                    <span class="text-muted fst-italic">No items recorded</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($row['medicine_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?= $row['requested_quantity'] === null
                                    ? '<span class="text-muted">&mdash;</span>'
                                    : number_format((int) $row['requested_quantity']) ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $statusClass((string) $row['request_status']) ?>">
                                    <?= htmlspecialchars($row['request_status']) ?>
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
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#requestHistoryTable').length) {
        jQuery('#requestHistoryTable').DataTable({
            pageLength: 10,
            order: [[1, 'desc']],
            columnDefs: [{ orderable: false, targets: [4] }],
            language: { search: 'Search history:' }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
