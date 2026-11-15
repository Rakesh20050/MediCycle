<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$listError = null;
$medicines = [];

try {
    $medicines = $pdo->query(
        'SELECT medicine_id, medicine_name, category, manufacturer
         FROM MEDICINE
         ORDER BY medicine_name ASC'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle medicines] ' . $e->getMessage());
    $listError = pdo_error_message($e);
}

$pageTitle    = 'Medicines';
$pageSubtitle = 'Catalogue of medicines held by MediCycle';
$activePage   = 'medicines';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($listError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($listError) ?>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="text-muted small">
        <i class="fa-solid fa-circle-info me-1"></i>
        Medicines are added automatically when a donor submits a batch.
    </div>
    <button type="button" class="btn btn-primary" id="addMedicineBtn">
        <i class="fa-solid fa-plus me-1"></i>Add New Medicine
    </button>
</div>

<div class="card">
    <div class="card-header">
        <span>
            <i class="fa-solid fa-pills me-2 text-primary"></i>All medicines
            <?php if ($medicines): ?>
                <span class="badge rounded-pill badge-soft-info ms-1"><?= count($medicines) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$medicines): ?>
        <?php if ($listError === null): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">No medicines found</div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="medicinesTable">
                <thead>
                    <tr>
                        <th>Medicine ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Manufacturer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicines as $row): ?>
                        <tr>
                            <td class="text-muted">#<?= (int) $row['medicine_id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td>
                                <span class="badge rounded-pill badge-soft-info">
                                    <?= htmlspecialchars($row['category']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['manufacturer']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#medicinesTable').length) {
        jQuery('#medicinesTable').DataTable({
            pageLength: 10,
            order: []
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
