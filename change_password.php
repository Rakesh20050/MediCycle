<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db_connect.php';

$role    = $_SESSION['role'] ?? '';
$account = match ($role) {
    'admin' => [
        'table'     => 'ADMIN',
        'id_column' => 'admin_id',
        'user_id'   => (int) ($_SESSION['admin_id'] ?? 0),
        'dashboard' => 'admin/dashboard.php',
    ],
    'donor' => [
        'table'     => 'DONOR',
        'id_column' => 'donor_id',
        'user_id'   => (int) ($_SESSION['donor_id'] ?? 0),
        'dashboard' => 'donor/dashboard.php',
    ],
    'org' => [
        'table'     => 'ORGANIZATION',
        'id_column' => 'organization_id',
        'user_id'   => (int) ($_SESSION['organization_id'] ?? 0),
        'dashboard' => 'organization/dashboard.php',
    ],
    default => null,
};

if ($account === null || $account['user_id'] < 1) {
    header('Location: index.php');
    exit;
}

$formError = null;
$justUpdated = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['updated'])) {
    $justUpdated = (bool) ($_SESSION['password_updated'] ?? false);
    unset($_SESSION['password_updated']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword     = (string) ($_POST['old_password'] ?? '');
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    try {
        $stmt = $pdo->prepare(
            "SELECT password_hash
             FROM {$account['table']}
             WHERE {$account['id_column']} = ?
             LIMIT 1"
        );
        $stmt->execute([$account['user_id']]);
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
                "UPDATE {$account['table']}
                 SET password_hash = ?
                 WHERE {$account['id_column']} = ?"
            );
            $update->execute([$newHash, $account['user_id']]);

            if ($update->rowCount() !== 1) {
                $formError = 'Could not update the password for this account.';
            } else {
                session_regenerate_id(true);
                $_SESSION['password_updated'] = true;
                header('Location: change_password.php?updated=1');
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log('[MediCycle change password] ' . $e->getMessage());
        $formError = pdo_error_message($e);
    }
}

$pageTitle    = 'Change Password';
$pageSubtitle = 'Keep your MediCycle account secure';
$activePage   = 'change-password';

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-key me-2 text-primary"></i>Update your password
            </div>
            <div class="card-body">
                <form method="post" action="change_password.php" id="changePasswordForm">
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
                        <button type="submit" class="btn btn-primary" id="passwordSubmit">
                            <i class="fa-solid fa-shield-halved me-2"></i>Update Password
                        </button>
                        <a href="<?= htmlspecialchars($account['dashboard']) ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
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
    const form = document.getElementById('changePasswordForm');
    const submitButton = document.getElementById('passwordSubmit');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    <?php if ($justUpdated): ?>
    Swal.fire({
        icon: 'success',
        title: 'Password updated successfully!',
        text: 'Redirecting to your dashboard…',
        confirmButtonText: 'Go to dashboard',
        confirmButtonColor: '#0d9488',
        color: '#111827',
        timer: 3000,
        timerProgressBar: true,
        allowOutsideClick: false
    }).then(function () {
        window.location.href = <?= json_encode($account['dashboard']) ?>;
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

<?php include __DIR__ . '/includes/footer.php'; ?>
