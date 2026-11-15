<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$requestId    = (int) (filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT) ?: 0);
$medicineId   = (int) (filter_input(INPUT_GET, 'medicine_id', FILTER_VALIDATE_INT) ?: 0);
$requestedQty = (int) (filter_input(INPUT_GET, 'qty', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_GET, 'requested_qty', FILTER_VALIDATE_INT)
    ?: 0);

$actionError   = null;
$insufficient  = false;
$availableQty  = 0;

if ($requestId < 1 || $medicineId < 1 || $requestedQty < 1) {
    $actionError = 'Invalid distribution request. Please return to the request list and try again.';
} else {
    try {
        $pdo->beginTransaction();

        // Lock the request so the same request cannot be distributed twice.
        $lockRequest = $pdo->prepare(
            'SELECT request_status FROM MEDICINE_REQUEST WHERE request_id = ? FOR UPDATE'
        );
        $lockRequest->execute([$requestId]);
        $requestStatus = $lockRequest->fetchColumn();

        if ($requestStatus === false) {
            throw new RuntimeException('That request no longer exists.');
        }
        if ($requestStatus !== 'Pending') {
            throw new RuntimeException('That request has already been processed.');
        }

        $lockItem = $pdo->prepare(
            'SELECT requested_quantity FROM REQUEST_ITEM
             WHERE request_id = ? AND medicine_id = ?
             LIMIT 1'
        );
        $lockItem->execute([$requestId, $medicineId]);
        if ($lockItem->fetchColumn() === false) {
            throw new RuntimeException('That medicine is not part of this request.');
        }

        // Lock only usable batches; the lock holds until commit or rollback.
        $batchesStmt = $pdo->prepare(
            'SELECT batch_id, quantity_available
             FROM MEDICINE_BATCH
             WHERE medicine_id = ?
               AND expiry_date > CURRENT_DATE
               AND quantity_available > 0
             ORDER BY expiry_date ASC
             FOR UPDATE'
        );
        $batchesStmt->execute([$medicineId]);
        $batches = $batchesStmt->fetchAll();

        foreach ($batches as $batch) {
            $availableQty += (int) $batch['quantity_available'];
        }

        if ($availableQty < $requestedQty) {
            throw new RuntimeException('Insufficient Stock');
        }

        // FEFO allocation: earliest-expiring batches are consumed first.
        $remaining   = $requestedQty;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $batch['quantity_available']);
            if ($take <= 0) {
                continue;
            }

            $allocations[] = [
                'batch_id' => (int) $batch['batch_id'],
                'quantity' => $take,
            ];
            $remaining -= $take;
        }

        // Zero or negative availability would have been caught above; this guards
        // against any rounding or stale read leaving the request partly fulfilled.
        if ($remaining > 0) {
            throw new RuntimeException('Insufficient Stock');
        }

        $deduct = $pdo->prepare(
            'UPDATE MEDICINE_BATCH
             SET quantity_available = quantity_available - ?
             WHERE batch_id = ? AND quantity_available >= ?'
        );

        foreach ($allocations as $allocation) {
            $deduct->execute([
                $allocation['quantity'],
                $allocation['batch_id'],
                $allocation['quantity'],
            ]);

            if ($deduct->rowCount() !== 1) {
                throw new RuntimeException('Stock changed while distributing. Please try again.');
            }
        }

        $insertDistribution = $pdo->prepare(
            "INSERT INTO DISTRIBUTION (request_id, approved_by, distribution_date, distribution_status)
             VALUES (?, ?, NOW(), 'Completed')"
        );
        $insertDistribution->execute([$requestId, (int) $_SESSION['admin_id']]);
        $distributionId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO DISTRIBUTION_ITEM (distribution_id, batch_id, distributed_quantity)
             VALUES (?, ?, ?)'
        );
        foreach ($allocations as $allocation) {
            $insertItem->execute([
                $distributionId,
                $allocation['batch_id'],
                $allocation['quantity'],
            ]);
        }

        $approve = $pdo->prepare(
            "UPDATE MEDICINE_REQUEST SET request_status = 'Approved' WHERE request_id = ?"
        );
        $approve->execute([$requestId]);

        $pdo->commit();

        $_SESSION['distribution_success'] = [
            'request_id'      => $requestId,
            'distribution_id' => $distributionId,
            'quantity'        => $requestedQty,
        ];

        header('Location: review_requests.php?distributed=1');
        exit;
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $insufficient = $e->getMessage() === 'Insufficient Stock';
        $actionError  = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[MediCycle distribute] ' . $e->getMessage());
        $actionError = pdo_error_message($e);
    }
}

$pageTitle    = 'Distribute Medicine';
$pageSubtitle = 'Fulfil a pending request with FEFO batch allocation';
$activePage   = 'requests';

include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Distribution could not be completed
            </div>
            <div class="card-body">
                <div class="alert alert-danger mb-3">
                    <?= htmlspecialchars($actionError ?? 'Transaction failed.') ?>
                    <?php if ($insufficient): ?>
                        <div class="small mt-1">
                            Requested <?= number_format($requestedQty) ?> units,
                            only <?= number_format($availableQty) ?> valid units are available right now.
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($availableQty > 0 || $insufficient): ?>
                    <p class="text-muted small">
                        The transaction was rolled back. No stock was deducted and the request remains pending.
                    </p>
                <?php endif; ?>
                <a href="review_requests.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to requests
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: <?= json_encode($insufficient ? 'Transaction Failed: Insufficient Stock' : 'Transaction Failed') ?>,
        text: <?= json_encode($actionError ?? 'Transaction failed.') ?>,
        confirmButtonText: 'OK',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
