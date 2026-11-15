<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId     = (int) $_SESSION['admin_id'];
$formError   = null;
$justUpdated = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['updated'])) {
    $justUpdated = (bool) ($_SESSION['admin_password_updated'] ?? false);
    unset($_SESSION['admin_password_updated']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword     = (string) ($_POST['old_password'] ?? '');
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    try {
        $stmt = $pdo->prepare(
            'SELECT password_hash
             FROM ADMIN
             WHERE admin_id = ?
             LIMIT 1'
        );
        $stmt->execute([$adminId]);
        $currentHash = $stmt->fetchColumn();

        if (!is_string($currentHash) || $currentHash === '' || !password_verify($oldPassword, $currentHash)) {
            $formError = 'Incorrect current password.';
        } elseif ($newPassword !== $confirmPassword) {
            $formError = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            $formError = 'New password must be between 8 and 72 characters.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $update = $pdo->prepare(
                'UPDATE ADMIN
                 SET password_hash = ?
                 WHERE admin_id = ?'
            );
            $update->execute([$newHash, $adminId]);

            if ($update->rowCount() !== 1) {
                $formError = 'Could not update the password for this admin account.';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_password_updated'] = true;
                header('Location: change_password.php?updated=1');
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log('[MediCycle admin change password] ' . $e->getMessage());
        $formError = pdo_error_message($e);
    }
}

$pageTitle    = 'Change Password';
$pageSubtitle = 'Update your administrator account password';
$activePage   = 'change-password';

include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-key me-2 text-primary"></i>Update your password
            </div>
            <div class="card-body">
                <form method="post" action="change_password.php" id="adminChangePasswordForm">
                    <div class="mb-3">
                        <label for="old_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="old_password" name="old_password"
                               autocomplete="current-password" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password"
                               autocomplete="new-password" minlength="8" maxlength="72" required>
                        <div class="form-hint">Use 8 to 72 characters.</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               autocomplete="new-password" minlength="8" maxlength="72" required>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary" id="adminPasswordSubmit">
                            <i class="fa-solid fa-shield-halved me-2"></i>Update Password
                        </button>
                        <a href="/medicycle/admin/profile.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <p class="text-muted small mt-3 mb-0">
            <i class="fa-solid fa-circle-info me-1"></i>
            Changing your password keeps you signed in on this device.
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('adminChangePasswordForm');
    const submitButton = document.getElementById('adminPasswordSubmit');

    <?php if ($justUpdated): ?>
    Swal.fire({
        icon: 'success',
        title: 'Password updated successfully!',
        text: 'Redirecting to your profile…',
        confirmButtonText: 'Go to profile',
        confirmButtonColor: '#0d9488',
        color: '#111827',
        timer: 3000,
        timerProgressBar: true,
        allowOutsideClick: false
    }).then(function () {
        window.location.href = '/medicycle/admin/profile.php';
    });
    <?php elseif ($formError !== null): ?>
    Swal.fire({
        icon: 'error',
        title: 'Password not changed',
        text: <?= json_encode($formError) ?>,
        confirmButtonText: 'Try again',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
    <?php endif; ?>

    form.addEventListener('submit', function () {
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating…';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
