<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$listError     = null;
$distributions = [];

try {
    $distributions = $pdo->query(
        'SELECT d.distribution_id, o.organization_name, m.medicine_name,
                b.batch_number, di.distributed_quantity, d.distribution_date
         FROM DISTRIBUTION d
         JOIN MEDICINE_REQUEST r ON d.request_id = r.request_id
         JOIN ORGANIZATION o ON r.organization_id = o.organization_id
         JOIN DISTRIBUTION_ITEM di ON d.distribution_id = di.distribution_id
         JOIN MEDICINE_BATCH b ON di.batch_id = b.batch_id
         JOIN MEDICINE m ON b.medicine_id = m.medicine_id
         ORDER BY d.distribution_date DESC'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle distributions] ' . $e->getMessage());
    $listError = pdo_error_message($e);
}

$pageTitle    = 'Distributions';
$pageSubtitle = 'Every batch handed out to organizations';
$activePage   = 'distributions';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($listError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($listError) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span>
            <i class="fa-solid fa-truck-medical me-2 text-primary"></i>All distributions
            <?php if ($distributions): ?>
                <span class="badge rounded-pill badge-soft-info ms-1"><?= count($distributions) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$distributions): ?>
        <?php if ($listError === null): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">No distributions found.</div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="distributionsTable">
                <thead>
                    <tr>
                        <th>Distribution</th>
                        <th>Organization</th>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th class="text-end">Quantity</th>
                        <th>Distributed On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distributions as $row): ?>
                        <tr>
                            <td class="fw-semibold">#<?= (int) $row['distribution_id'] ?></td>
                            <td><?= htmlspecialchars($row['organization_name']) ?></td>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><code><?= htmlspecialchars($row['batch_number']) ?></code></td>
                            <td class="text-end fw-semibold"><?= number_format((int) $row['distributed_quantity']) ?></td>
                            <td data-order="<?= htmlspecialchars((string) $row['distribution_date']) ?>">
                                <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string) $row['distribution_date']))) ?>
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
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#distributionsTable').length) {
        jQuery('#distributionsTable').DataTable({
            pageLength: 10,
            order: []
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
