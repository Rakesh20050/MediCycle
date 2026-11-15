<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$reportError        = null;
$nearExpiry         = [];
$stockReport        = [];
$requestHistory     = [];
$distributionHistory = [];

try {
    $nearExpiry = $pdo->query(
        'SELECT m.medicine_name, b.batch_number, b.expiry_date, b.quantity_available
         FROM MEDICINE_BATCH b
         JOIN MEDICINE m ON b.medicine_id = m.medicine_id
         WHERE b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           AND b.expiry_date >= CURDATE()
           AND b.quantity_available > 0
         ORDER BY b.expiry_date ASC'
    )->fetchAll();

    $stockReport = $pdo->query(
        'SELECT m.medicine_name, m.category, SUM(b.quantity_available) AS total_stock
         FROM MEDICINE_BATCH b
         JOIN MEDICINE m ON b.medicine_id = m.medicine_id
         WHERE b.quantity_available > 0
         GROUP BY m.medicine_id, m.medicine_name, m.category'
    )->fetchAll();

    $requestHistory = $pdo->query(
        'SELECT r.request_id, o.organization_name, r.request_date, r.request_status,
                m.medicine_name, ri.requested_quantity
         FROM MEDICINE_REQUEST r
         JOIN ORGANIZATION o ON r.organization_id = o.organization_id
         JOIN REQUEST_ITEM ri ON r.request_id = ri.request_id
         JOIN MEDICINE m ON ri.medicine_id = m.medicine_id
         ORDER BY r.request_date DESC'
    )->fetchAll();

    $distributionHistory = $pdo->query(
        'SELECT d.distribution_id, o.organization_name, d.distribution_date,
                m.medicine_name, b.batch_number, di.distributed_quantity
         FROM DISTRIBUTION d
         JOIN MEDICINE_REQUEST r ON d.request_id = r.request_id
         JOIN ORGANIZATION o ON r.organization_id = o.organization_id
         JOIN DISTRIBUTION_ITEM di ON d.distribution_id = di.distribution_id
         JOIN MEDICINE_BATCH b ON di.batch_id = b.batch_id
         JOIN MEDICINE m ON b.medicine_id = m.medicine_id
         ORDER BY d.distribution_date DESC'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle reports] ' . $e->getMessage());
    $reportError = pdo_error_message($e);
}

$statusClass = static fn (string $status): string => match ($status) {
    'Approved' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    default    => 'badge-soft-warning',
};

$pageTitle    = 'Reports';
$pageSubtitle = 'Stock, expiry, request and distribution insights';
$activePage   = 'reports';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($reportError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($reportError) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span>
            <i class="fa-solid fa-hourglass-half me-2 text-warning"></i>Near-Expiry Stock (30 days)
            <?php if ($nearExpiry): ?>
                <span class="badge rounded-pill badge-soft-warning ms-1"><?= count($nearExpiry) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$nearExpiry): ?>
        <?php if ($reportError === null): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">No data available for this report.</div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="nearExpiryTable">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th>Expiry Date</th>
                        <th class="text-end">Quantity Available</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nearExpiry as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><code><?= htmlspecialchars($row['batch_number']) ?></code></td>
                            <td data-order="<?= htmlspecialchars((string) $row['expiry_date']) ?>">
                                <?= htmlspecialchars(date('d M Y', strtotime((string) $row['expiry_date']))) ?>
                            </td>
                            <td class="text-end fw-semibold"><?= number_format((int) $row['quantity_available']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-4">
    <div class="card-header">
        <span>
            <i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Total Available Stock
            <?php if ($stockReport): ?>
                <span class="badge rounded-pill badge-soft-info ms-1"><?= count($stockReport) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$stockReport): ?>
        <?php if ($reportError === null): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">No data available for this report.</div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="totalStockTable">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th class="text-end">Total Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stockReport as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((int) $row['total_stock']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-4">
    <div class="card-header">
        <span>
            <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Request History
            <?php if ($requestHistory): ?>
                <span class="badge rounded-pill badge-soft-info ms-1"><?= count($requestHistory) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$requestHistory): ?>
        <?php if ($reportError === null): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">No data available for this report.</div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="requestHistoryTable">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Organization</th>
                        <th>Requested On</th>
                        <th>Status</th>
                        <th>Medicine</th>
                        <th class="text-end">Requested Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requestHistory as $row): ?>
                        <tr>
                            <td class="fw-semibold">#<?= (int) $row['request_id'] ?></td>
                            <td><?= htmlspecialchars($row['organization_name']) ?></td>
                            <td data-order="<?= htmlspecialchars((string) $row['request_date']) ?>">
                                <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string) $row['request_date']))) ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $statusClass((string) $row['request_status']) ?>">
                                    <?= htmlspecialchars($row['request_status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td class="text-end"><?= number_format((int) $row['requested_quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-4">
    <div class="card-header">
        <span>
            <i class="fa-solid fa-truck-medical me-2 text-primary"></i>Distribution History
            <?php if ($distributionHistory): ?>
                <span class="badge rounded-pill badge-soft-info ms-1"><?= count($distributionHistory) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$distributionHistory): ?>
        <?php if ($reportError === null): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">No data available for this report.</div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="distributionHistoryTable">
                <thead>
                    <tr>
                        <th>Distribution</th>
                        <th>Organization</th>
                        <th>Distributed On</th>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th class="text-end">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distributionHistory as $row): ?>
                        <tr>
                            <td class="fw-semibold">#<?= (int) $row['distribution_id'] ?></td>
                            <td><?= htmlspecialchars($row['organization_name']) ?></td>
                            <td data-order="<?= htmlspecialchars((string) $row['distribution_date']) ?>">
                                <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string) $row['distribution_date']))) ?>
                            </td>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><code><?= htmlspecialchars($row['batch_number']) ?></code></td>
                            <td class="text-end fw-semibold"><?= number_format((int) $row['distributed_quantity']) ?></td>
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

    ['#nearExpiryTable', '#totalStockTable', '#requestHistoryTable', '#distributionHistoryTable']
        .forEach(function (selector) {
            if (jQuery(selector).length) {
                jQuery(selector).DataTable({
                    pageLength: 10,
                    order: []
                });
            }
        });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
