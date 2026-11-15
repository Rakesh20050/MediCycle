<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_organization();

$organizationId     = (int) $_SESSION['organization_id'];
$verificationStatus = null;

try {
    $statusStmt = $pdo->prepare(
        'SELECT verification_status
         FROM ORGANIZATION
         WHERE organization_id = ?
         LIMIT 1'
    );
    $statusStmt->execute([$organizationId]);
    $verificationStatus = $statusStmt->fetchColumn();
} catch (PDOException $e) {
    error_log('[MediCycle new request verification] ' . $e->getMessage());
}

if ($verificationStatus !== 'Verified') {
    $pageTitle    = 'New Medicine Request';
    $pageSubtitle = 'Request medicines for the people you serve';
    $activePage   = 'new-request';

    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="access-wrap">
        <div class="card access-card">
            <div class="card-body text-center py-5 px-4">
                <div class="access-icon">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <span class="badge rounded-pill badge-soft-warning mb-3">Pending Verification</span>
                <h2 class="h4 fw-bold mb-2">Access Denied</h2>
                <p class="text-muted mb-4">
                    Account Pending Verification. Please wait for the Admin to approve your account.
                </p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fa-solid fa-arrow-left me-2"></i>Go back to Dashboard
                </a>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$formError = null;

$old = [
    'medicine_name'      => '',
    'requested_quantity' => '',
];

$submittedRequestId = null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['submitted'])) {
    if (isset($_SESSION['request_submitted'])) {
        $submittedRequestId = (int) $_SESSION['request_submitted'];
        unset($_SESSION['request_submitted']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['medicine_name']      = trim((string) ($_POST['medicine_name'] ?? ''));
    $old['requested_quantity'] = trim((string) ($_POST['requested_quantity'] ?? ''));

    $quantity = filter_var($old['requested_quantity'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($old['medicine_name'] === '') {
        $formError = 'Please enter the medicine name.';
    } elseif ($quantity === false) {
        $formError = 'Quantity must be a positive whole number.';
    } else {
        try {
            // Escape LIKE wildcards so the typed name is matched literally (case-insensitively).
            $likePattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $old['medicine_name']) . '%';

            $findMedicine = $pdo->prepare(
                "SELECT medicine_id
                 FROM MEDICINE
                 WHERE medicine_name LIKE ? ESCAPE '!'
                 ORDER BY (medicine_name = ?) DESC, CHAR_LENGTH(medicine_name) ASC, medicine_name ASC
                 LIMIT 1"
            );
            $findMedicine->execute([$likePattern, $old['medicine_name']]);
            $medicineId = $findMedicine->fetchColumn();

            if ($medicineId === false) {
                $formError = 'Medicine not found in our database. Please check the spelling.';
            } else {
                $medicineId = (int) $medicineId;

                $stockStmt = $pdo->prepare(
                    'SELECT COALESCE(SUM(quantity_available), 0) AS total_stock
                     FROM MEDICINE_BATCH
                     WHERE medicine_id = ?
                       AND expiry_date > CURRENT_DATE'
                );
                $stockStmt->execute([$medicineId]);
                $totalStock = (int) $stockStmt->fetchColumn();

                if ($quantity > $totalStock) {
                    $formError = 'Requested quantity exceeds available stock (Only '
                        . number_format($totalStock) . ' available).';
                } else {
                    // Request header and item must be saved together, so wrap them in one transaction.
                    $pdo->beginTransaction();

                    $insertRequest = $pdo->prepare(
                        "INSERT INTO MEDICINE_REQUEST
                            (organization_id, request_date, request_status, reviewed_by)
                         VALUES (?, NOW(), 'Pending', NULL)"
                    );
                    $insertRequest->execute([$organizationId]);
                    $requestId = (int) $pdo->lastInsertId();

                    $insertItem = $pdo->prepare(
                        'INSERT INTO REQUEST_ITEM (request_id, medicine_id, requested_quantity)
                         VALUES (?, ?, ?)'
                    );
                    $insertItem->execute([$requestId, $medicineId, $quantity]);

                    $pdo->commit();

                    $_SESSION['request_submitted'] = $requestId;
                    header('Location: request.php?submitted=1');
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[MediCycle new request] ' . $e->getMessage());
            $formError = pdo_error_message($e);
        }
    }
}

$pageTitle    = 'New Medicine Request';
$pageSubtitle = 'Request medicines for the people you serve';
$activePage   = 'new-request';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-file-medical me-2 text-primary"></i>Request details
            </div>
            <div class="card-body">
                <form method="post" action="request.php" id="medicineRequestForm">
                    <div class="mb-3">
                        <label for="medicine_name" class="form-label fw-semibold">Medicine</label>
                        <input type="text" class="form-control" id="medicine_name" name="medicine_name"
                               value="<?= htmlspecialchars($old['medicine_name']) ?>"
                               placeholder="Enter medicine name e.g., Paracetamol"
                               maxlength="150" required autofocus>
                        <div class="form-hint">We check the name against our database and current stock.</div>
                    </div>

                    <div class="mb-3">
                        <label for="requested_quantity" class="form-label fw-semibold">Quantity needed</label>
                        <input type="number" class="form-control" id="requested_quantity" name="requested_quantity"
                               value="<?= htmlspecialchars($old['requested_quantity']) ?>"
                               min="1" step="1" placeholder="e.g. 100" required>
                        <div class="form-hint">
                            Your request is reviewed by an administrator before stock is allocated.
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-paper-plane me-1"></i>Submit Request
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-route me-2 text-primary"></i>How it works
            </div>
            <div class="card-body">
                <ol class="request-steps mb-0">
                    <li>
                        <span class="step-title">Check</span>
                        <p class="step-text mb-0">
                            We match the medicine name you type and confirm unexpired stock is available.
                        </p>
                    </li>
                    <li>
                        <span class="step-title">Submit</span>
                        <p class="step-text mb-0">
                            Your request is saved with the status <strong>Pending</strong>.
                        </p>
                    </li>
                    <li>
                        <span class="step-title">Review &amp; fulfilment</span>
                        <p class="step-text mb-0">
                            An administrator approves or rejects it; approved requests are distributed
                            from the earliest-expiring batches first (FEFO).
                        </p>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($submittedRequestId !== null): ?>
    Swal.fire({
        icon: 'success',
        title: 'Request submitted',
        text: 'Request #<?= (int) $submittedRequestId ?> is pending administrator review.',
        confirmButtonText: 'Go to dashboard',
        confirmButtonColor: '#0d9488',
        color: '#111827',
        timer: 3200,
        timerProgressBar: true,
        allowOutsideClick: false
    }).then(function () {
        window.location.href = 'dashboard.php';
    });
    <?php elseif ($formError !== null): ?>
    Swal.fire({
        icon: 'error',
        title: 'Request not submitted',
        text: <?= json_encode($formError) ?>,
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
    <?php endif; ?>

    const form = document.getElementById('medicineRequestForm');
    if (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('button[type=submit]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
