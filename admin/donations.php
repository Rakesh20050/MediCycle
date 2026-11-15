<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$listError = null;
$donations = [];

try {
    $donations = $pdo->query(
        'SELECT d.donation_id, dn.donor_name, d.donation_date, d.donation_status
         FROM DONATION d
         JOIN DONOR dn ON d.donor_id = dn.donor_id
         ORDER BY d.donation_date DESC'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle donations] ' . $e->getMessage());
    $listError = pdo_error_message($e);
}

$statusClass = static fn (string $status): string => match ($status) {
    'Accepted' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    default    => 'badge-soft-warning',
};

$pageTitle    = 'Donations';
$pageSubtitle = 'Every medicine donation recorded in the system';
$activePage   = 'donations';

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
            <i class="fa-solid fa-hand-holding-heart me-2 text-primary"></i>All donations
            <?php if ($donations): ?>
                <span class="badge rounded-pill badge-soft-info ms-1"><?= count($donations) ?></span>
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$donations): ?>
        <?php if ($listError === null): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">No donations found</div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="donationsTable">
                <thead>
                    <tr>
                        <th>Donation</th>
                        <th>Donor</th>
                        <th>Donated On</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $row): ?>
                        <tr>
                            <td class="fw-semibold">#<?= (int) $row['donation_id'] ?></td>
                            <td><?= htmlspecialchars($row['donor_name']) ?></td>
                            <td data-order="<?= htmlspecialchars((string) $row['donation_date']) ?>">
                                <?= htmlspecialchars(date('d M Y, h:i A', strtotime((string) $row['donation_date']))) ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $statusClass((string) $row['donation_status']) ?>">
                                    <?= htmlspecialchars($row['donation_status']) ?>
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
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#donationsTable').length) {
        jQuery('#donationsTable').DataTable({
            pageLength: 10,
            order: []
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
