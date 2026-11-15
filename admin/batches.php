<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$listError = null;
$batches   = [];

try {
    $batches = $pdo->query(
        'SELECT b.batch_id, m.medicine_name, b.batch_number, b.expiry_date,
                b.quantity_received, b.quantity_available
         FROM MEDICINE_BATCH b
         JOIN MEDICINE m ON b.medicine_id = m.medicine_id
         ORDER BY b.expiry_date ASC'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle batches] ' . $e->getMessage());
    $listError = pdo_error_message($e);
}

$pageTitle    = 'Medicine Batches';
$pageSubtitle = 'Every donated batch, earliest expiry first';
$activePage   = 'batches';

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
            <i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>All batches
            <?php if ($batches): ?>
                <span class="badge rounded-pill badge-soft-info ms-1"><?= count($batches) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$batches): ?>
        <?php if ($listError === null): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">No batches found</div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="batchesTable">
                <thead>
                    <tr>
                        <th>Batch ID</th>
                        <th>Medicine</th>
                        <th>Batch Number</th>
                        <th>Expiry Date</th>
                        <th class="text-end">Quantity Received</th>
                        <th class="text-end">Quantity Available</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $row): ?>
                        <?php $available = (int) $row['quantity_available']; ?>
                        <tr>
                            <td class="text-muted">#<?= (int) $row['batch_id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><code><?= htmlspecialchars($row['batch_number']) ?></code></td>
                            <td data-order="<?= htmlspecialchars((string) $row['expiry_date']) ?>">
                                <?= htmlspecialchars(date('d M Y', strtotime((string) $row['expiry_date']))) ?>
                            </td>
                            <td class="text-end"><?= number_format((int) $row['quantity_received']) ?></td>
                            <td class="text-end">
                                <span class="badge rounded-pill <?= $available > 0 ? 'badge-soft-success' : 'bg-secondary' ?>">
                                    <?= number_format($available) ?>
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
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#batchesTable').length) {
        jQuery('#batchesTable').DataTable({
            pageLength: 10,
            order: []
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
