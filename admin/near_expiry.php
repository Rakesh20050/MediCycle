<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$listError = null;
$nearExpiry = [];

try {
    $nearExpiry = $pdo->query(
        'SELECT m.medicine_name, b.batch_number, b.expiry_date, b.quantity_available,
                DATEDIFF(b.expiry_date, CURDATE()) AS days_left
         FROM MEDICINE_BATCH b
         JOIN MEDICINE m ON b.medicine_id = m.medicine_id
         WHERE b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           AND b.expiry_date >= CURDATE()
           AND b.quantity_available > 0
         ORDER BY b.expiry_date ASC'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle near expiry] ' . $e->getMessage());
    $listError = pdo_error_message($e);
}

$pageTitle    = 'Near Expiry';
$pageSubtitle = 'Batches expiring within the next 30 days';
$activePage   = 'expiring';

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
            <i class="fa-solid fa-hourglass-half me-2 text-warning"></i>Expiring soon
            <?php if ($nearExpiry): ?>
                <span class="badge rounded-pill badge-soft-warning ms-1"><?= count($nearExpiry) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$nearExpiry): ?>
        <?php if ($listError === null): ?>
            <div class="card-body">
                <div class="alert alert-success mb-0">
                    <i class="fa-solid fa-circle-check me-1"></i>No medicines are expiring in the next 30 days.
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="nearExpiryListTable">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th class="text-end">Quantity Available</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nearExpiry as $row): ?>
                        <?php $daysLeft = (int) $row['days_left']; ?>
                        <tr<?= $daysLeft < 10 ? ' class="table-danger"' : '' ?>>
                            <td class="fw-semibold"><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><code><?= htmlspecialchars($row['batch_number']) ?></code></td>
                            <td data-order="<?= htmlspecialchars((string) $row['expiry_date']) ?>">
                                <?= htmlspecialchars(date('d M Y', strtotime((string) $row['expiry_date']))) ?>
                            </td>
                            <td data-order="<?= $daysLeft ?>">
                                <span class="badge rounded-pill <?= $daysLeft < 10 ? 'bg-danger' : 'badge-soft-warning' ?>">
                                    <?= $daysLeft ?> <?= $daysLeft === 1 ? 'day' : 'days' ?>
                                </span>
                                <?php if ($daysLeft < 10): ?>
                                    <span class="badge rounded-pill bg-danger ms-1">Urgent</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold"><?= number_format((int) $row['quantity_available']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#nearExpiryListTable').length) {
        jQuery('#nearExpiryListTable').DataTable({
            pageLength: 10,
            order: []
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
