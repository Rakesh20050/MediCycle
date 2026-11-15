<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$actionError = null;
$pending     = [];

// Approve or reject an organization (POST only, guarded so it is reviewed once).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $orgId  = filter_input(INPUT_POST, 'organization_id', FILTER_VALIDATE_INT);

    if (!in_array($action, ['approve', 'reject'], true)) {
        $actionError = 'Unknown action.';
    } elseif (!$orgId) {
        $actionError = 'Invalid organization selected.';
    } else {
        $newStatus = $action === 'approve' ? 'Verified' : 'Rejected';

        try {
            $stmt = $pdo->prepare(
                'UPDATE ORGANIZATION
                 SET verification_status = ?
                 WHERE organization_id = ? AND verification_status = ?'
            );
            $stmt->execute([$newStatus, $orgId, 'Pending']);

            if ($stmt->rowCount() === 1) {
                header('Location: verify_org.php?updated=' . strtolower($newStatus));
                exit;
            }

            $actionError = 'That organization has already been reviewed.';
        } catch (PDOException $e) {
            error_log('[MediCycle verify organization] ' . $e->getMessage());
            $actionError = pdo_error_message($e);
        }
    }
}

try {
    $pending = $pdo->query(
        "SELECT organization_id, organization_name, organization_type,
                contact, email, address
         FROM ORGANIZATION
         WHERE verification_status = 'Pending'
         ORDER BY organization_name ASC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[MediCycle verify organization] ' . $e->getMessage());
    $actionError = $actionError ?? pdo_error_message($e);
}

$pageTitle    = 'Verify Organizations';
$pageSubtitle = 'Approve or reject organization registrations';
$activePage   = 'organizations';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($actionError !== null): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($actionError) ?>
    </div>
<?php endif; ?>

<?php if (!$pending): ?>
    <?php if ($actionError === null): ?>
        <div class="alert alert-info mb-0">No pending organizations to verify.</div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <span>
                <i class="fa-solid fa-hourglass-half me-2 text-warning"></i>Pending organizations
                <span class="badge rounded-pill badge-soft-warning ms-1"><?= count($pending) ?></span>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="pendingOrganizationsTable">
                <thead>
                    <tr>
                        <th>Organization Name</th>
                        <th>Type</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($row['organization_name']) ?></td>
                            <td>
                                <span class="badge rounded-pill badge-soft-info">
                                    <?= htmlspecialchars($row['organization_type']) ?>
                                </span>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($row['contact']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['email']) ?></small>
                            </td>
                            <td><small><?= htmlspecialchars($row['address']) ?></small></td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                    <form method="post" action="verify_org.php" class="org-verify-form"
                                          data-action="approve"
                                          data-name="<?= htmlspecialchars($row['organization_name'], ENT_QUOTES) ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="organization_id" value="<?= (int) $row['organization_id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm text-nowrap">
                                            <i class="fa-solid fa-check me-1"></i>Approve
                                        </button>
                                    </form>
                                    <form method="post" action="verify_org.php" class="org-verify-form"
                                          data-action="reject"
                                          data-name="<?= htmlspecialchars($row['organization_name'], ENT_QUOTES) ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="organization_id" value="<?= (int) $row['organization_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm text-nowrap">
                                            <i class="fa-solid fa-xmark me-1"></i>Reject
                                        </button>
                                    </form>
                                </div>
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
    if (window.jQuery && jQuery.fn.DataTable && jQuery('#pendingOrganizationsTable').length) {
        jQuery('#pendingOrganizationsTable').DataTable({
            pageLength: 10,
            order: [],
            columnDefs: [{ orderable: false, targets: 4 }]
        });
    }

    document.querySelectorAll('.org-verify-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const isApprove = form.dataset.action === 'approve';
            const orgName   = form.dataset.name || 'this organization';

            Swal.fire({
                icon: isApprove ? 'question' : 'warning',
                title: isApprove ? 'Approve organization?' : 'Reject organization?',
                text: (isApprove ? 'Approve ' : 'Reject ') + orgName + '?',
                showCancelButton: true,
                confirmButtonText: isApprove ? 'Yes, approve' : 'Yes, reject',
                cancelButtonText: 'Cancel',
                confirmButtonColor: isApprove ? '#0d9488' : '#dc3545',
                cancelButtonColor: '#6c757d',
                color: '#111827'
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    <?php if (isset($_GET['updated']) && in_array($_GET['updated'], ['verified', 'rejected'], true)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Organization <?= $_GET['updated'] === 'verified' ? 'approved' : 'rejected' ?>',
        text: <?= json_encode(
            $_GET['updated'] === 'verified'
                ? 'The organization has been verified and can now request medicines.'
                : 'The organization has been rejected and cannot request medicines.'
        ) ?>,
        confirmButtonText: 'Continue',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    }).then(function () {
        history.replaceState({}, '', 'verify_org.php');
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
