<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pending   = [];
$listError = null;

$distributedNotice = null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['distributed'])) {
    $distributedNotice = $_SESSION['distribution_success'] ?? null;
    unset($_SESSION['distribution_success']);
}

try {
    $pending = $pdo->query(
        "SELECT r.request_id, o.organization_name, m.medicine_name, m.medicine_id,
                ri.requested_quantity
         FROM MEDICINE_REQUEST r
         JOIN ORGANIZATION o ON o.organization_id = r.organization_id
         JOIN REQUEST_ITEM ri ON ri.request_id = r.request_id
         JOIN MEDICINE m ON m.medicine_id = ri.medicine_id
         WHERE r.request_status = 'Pending'
         ORDER BY r.request_id ASC, m.medicine_name ASC"
    )->fetchAll();

    // Valid stock = available units in batches that have not expired yet.
    $stockStmt  = $pdo->prepare(
        'SELECT COALESCE(SUM(quantity_available), 0)
         FROM MEDICINE_BATCH
         WHERE medicine_id = ? AND expiry_date > CURRENT_DATE'
    );
    $stockCache = [];

    foreach ($pending as &$row) {
        $medicineId = (int) $row['medicine_id'];

        if (!array_key_exists($medicineId, $stockCache)) {
            $stockStmt->execute([$medicineId]);
            $stockCache[$medicineId] = (int) $stockStmt->fetchColumn();
        }

        $row['valid_stock'] = $stockCache[$medicineId];
    }
    unset($row);
} catch (PDOException $e) {
    error_log('[MediCycle review requests] ' . $e->getMessage());
    $listError = pdo_error_message($e);
}

$pageTitle    = 'Review Requests';
$pageSubtitle = 'Check stock coverage and approve pending medicine requests';
$activePage   = 'requests';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($listError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($listError) ?>
    </div>
<?php endif; ?>

<?php if (!$pending): ?>
    <?php if ($listError === null): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card text-center">
                    <div class="card-body py-5">
                        <i class="fa-regular fa-circle-check fa-3x text-success mb-3"></i>
                        <h5 class="fw-semibold">All caught up! No pending requests.</h5>
                        <p class="text-muted mb-0">New requests from organizations will appear here.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <span>
                <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Pending request items
                <span class="badge rounded-pill badge-soft-warning ms-1"><?= count($pending) ?></span>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="pendingRequestsTable">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Organization</th>
                        <th>Medicine</th>
                        <th class="text-end">Requested Qty</th>
                        <th class="text-end">Valid Stock</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $row): ?>
                        <?php
                            $requestId = (int) $row['request_id'];
                            $requested = (int) $row['requested_quantity'];
                            $valid     = (int) $row['valid_stock'];
                            $covered   = $valid >= $requested;
                            $distributeUrl = 'distribute.php?' . http_build_query([
                                'request_id'  => $requestId,
                                'medicine_id' => (int) $row['medicine_id'],
                                'qty'         => $requested,
                            ]);
                        ?>
                        <tr>
                            <td class="fw-semibold">#<?= $requestId ?></td>
                            <td><?= htmlspecialchars($row['organization_name']) ?></td>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td class="text-end"><?= number_format($requested) ?></td>
                            <td class="text-end">
                                <span class="fw-semibold <?= $covered ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($valid) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (!$covered): ?>
                                    <span class="badge bg-danger">Insufficient Valid Stock</span>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($distributeUrl) ?>" class="btn btn-success btn-sm text-nowrap">
                                        <i class="fa-solid fa-truck-fast me-1"></i>Approve &amp; Distribute
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (is_array($distributedNotice)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Distribution completed',
        text: <?= json_encode(
            'Request #' . (int) ($distributedNotice['request_id'] ?? 0)
            . ' was approved and ' . (int) ($distributedNotice['quantity'] ?? 0)
            . ' units were deducted from stock.'
        ) ?>,
        confirmButtonText: 'Continue',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    }).then(function () {
        history.replaceState({}, '', 'review_requests.php');
    });
    <?php endif; ?>

    if (window.jQuery && jQuery.fn.DataTable && jQuery('#pendingRequestsTable').length) {
        jQuery('#pendingRequestsTable').DataTable({
            pageLength: 10,
            order: [],
            columnDefs: [{ orderable: false, targets: [5] }]
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
