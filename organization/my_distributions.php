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
    error_log('[MediCycle my distributions verification] ' . $e->getMessage());
}

if ($verificationStatus !== 'Verified') {
    $pageTitle     = 'My Distributions';
    $pageSubtitle  = 'Medicines your organization has received';
    $activePage    = 'my-distributions';
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

$distributions = [];
$tableError    = null;

try {
    $stmt = $pdo->prepare(
        'SELECT d.distribution_id, d.distribution_date,
                m.medicine_name, b.batch_number, b.expiry_date,
                di.distributed_quantity
         FROM DISTRIBUTION d
         JOIN MEDICINE_REQUEST r ON d.request_id = r.request_id
         JOIN DISTRIBUTION_ITEM di ON d.distribution_id = di.distribution_id
         JOIN MEDICINE_BATCH b ON di.batch_id = b.batch_id
         JOIN MEDICINE m ON b.medicine_id = m.medicine_id
         WHERE r.organization_id = ?
         ORDER BY d.distribution_date DESC'
    );
    $stmt->execute([$organizationId]);
    $distributions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle my distributions] ' . $e->getMessage());
    $tableError = pdo_error_message($e);
}

$distributionCount = count(array_unique(array_column($distributions, 'distribution_id')));
$totalUnits        = array_sum(array_map('intval', array_column($distributions, 'distributed_quantity')));

$pageTitle    = 'My Distributions';
$pageSubtitle = 'Medicines your organization has received, batch by batch';
$activePage   = 'my-distributions';

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
            <i class="fa-solid fa-truck-medical me-2 text-primary"></i>Received stock
            <span class="badge rounded-pill badge-soft-info ms-1">
                <?= $distributionCount ?> <?= $distributionCount === 1 ? 'distribution' : 'distributions' ?>
            </span>
            <span class="badge rounded-pill badge-soft-success ms-1">
                <?= number_format($totalUnits) ?> units
            </span>
        </span>
        <a href="my_requests.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-clipboard-list me-1"></i>View Requests
        </a>
    </div>

    <?php if (!$distributions && $tableError === null): ?>
        <div class="empty-state">
            <i class="fa-solid fa-truck-medical"></i>
            <h5>No distributions yet</h5>
            <p>Once an approved request is fulfilled, each batch you receive will be listed here.</p>
            <a href="my_requests.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-clipboard-list me-1"></i>View your requests
            </a>
        </div>
    <?php elseif ($distributions): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="myDistributionsTable">
                <thead>
                    <tr>
                        <th>Distribution Date</th>
                        <th>Medicine Name</th>
                        <th>Batch Number</th>
                        <th>Expiry Date</th>
                        <th>Quantity Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distributions as $row): ?>
                        <tr>
                            <td data-order="<?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($row['distribution_date']))) ?>">
                                <span class="text-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($row['distribution_date']))) ?></span>
                                <div><small class="text-muted"><?= htmlspecialchars(date('h:i A', strtotime($row['distribution_date']))) ?></small></div>
                            </td>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><span class="badge rounded-pill badge-soft-info"><?= htmlspecialchars($row['batch_number']) ?></span></td>
                            <td data-order="<?= htmlspecialchars(date('Y-m-d', strtotime($row['expiry_date']))) ?>">
                                <span class="text-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($row['expiry_date']))) ?></span>
                            </td>
                            <td class="text-nowrap fw-semibold"><?= number_format((int) $row['distributed_quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#myDistributionsTable').length) {
        jQuery('#myDistributionsTable').DataTable({
            pageLength: 10,
            order: [[0, 'desc']],
            language: { search: 'Search distributions:' }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
