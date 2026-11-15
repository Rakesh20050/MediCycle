<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId     = (int) $_SESSION['admin_id'];
$formError   = null;
$justUpdated = false;
$admin       = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['updated'])) {
    $justUpdated = (bool) ($_SESSION['admin_profile_updated'] ?? false);
    unset($_SESSION['admin_profile_updated']);
}

try {
    $stmt = $pdo->prepare(
        'SELECT admin_id, admin_name, email
         FROM ADMIN
         WHERE admin_id = ?
         LIMIT 1'
    );
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[MediCycle admin profile] ' . $e->getMessage());
    $formError = pdo_error_message($e);
}

if ($admin === false) {
    $admin     = null;
    $formError = $formError ?? 'Your admin account could not be loaded.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin !== null) {
    $newName = trim((string) ($_POST['admin_name'] ?? ''));

    if (strlen($newName) < 2 || strlen($newName) > 100) {
        $formError = 'Please enter your name (2-100 characters).';
    } else {
        try {
            $update = $pdo->prepare(
                'UPDATE ADMIN
                 SET admin_name = ?
                 WHERE admin_id = ?'
            );
            $update->execute([$newName, $adminId]);

            // Keep the navbar chip in sync with the saved name.
            $_SESSION['user_name']             = $newName;
            $_SESSION['admin_profile_updated'] = true;

            header('Location: profile.php?updated=1');
            exit;
        } catch (PDOException $e) {
            error_log('[MediCycle admin profile] ' . $e->getMessage());
            $formError = pdo_error_message($e);
        }
    }
}

$currentName  = $admin !== null ? (string) $admin['admin_name'] : '';
$currentEmail = $admin !== null ? (string) $admin['email'] : '';
$initial      = strtoupper(substr($currentName !== '' ? $currentName : 'A', 0, 1));

$pageTitle    = 'My Profile';
$pageSubtitle = 'Review and update your administrator account';
$activePage   = 'profile';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4 justify-content-center">
    <div class="col-lg-9 col-xl-8">

        <?php if ($admin === null): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                <?= htmlspecialchars($formError ?? 'Your admin account could not be loaded.') ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>
                        <i class="fa-solid fa-user-shield me-2 text-primary"></i>Administrator details
                    </span>
                    <span class="badge rounded-pill role-admin">
                        <i class="fa-solid fa-user-shield me-1"></i>Admin
                    </span>
                </div>
                <div class="card-body">

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar avatar-lg"><?= htmlspecialchars($initial) ?></span>
                        <div>
                            <div class="fw-semibold fs-5"><?= htmlspecialchars($currentName) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($currentEmail) ?></div>
                        </div>
                    </div>

                    <form method="post" action="profile.php" id="adminProfileForm">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="admin_name" class="form-label fw-semibold">Full name</label>
                                <input type="text" class="form-control" id="admin_name" name="admin_name"
                                       value="<?= htmlspecialchars($currentName) ?>"
                                       minlength="2" maxlength="100" required>
                                <div class="form-hint">This name is shown in the top navigation bar.</div>
                            </div>

                            <div class="col-md-5">
                                <label for="admin_id" class="form-label fw-semibold">Admin ID</label>
                                <input type="text" class="form-control" id="admin_id"
                                       value="#<?= (int) $admin['admin_id'] ?>" readonly aria-readonly="true">
                            </div>

                            <div class="col-12">
                                <label for="email" class="form-label fw-semibold">Email address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($currentEmail) ?>"
                                       readonly aria-readonly="true">
                                <div class="form-hint">Email cannot be changed here because it is your login identifier.</div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" id="adminProfileSubmit">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Update Profile
                            </button>
                            <a href="/medicycle/admin/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <i class="fa-solid fa-lock me-2 text-primary"></i>Account &amp; security
                </div>
                <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div>
                        <div class="fw-semibold">Change your password</div>
                        <div class="text-muted small">Update the password used to sign in as an administrator.</div>
                    </div>
                    <a href="/medicycle/admin/change_password.php" class="btn btn-outline-primary">
                        <i class="fa-solid fa-key me-2"></i>Change Password
                    </a>
                </div>
                <hr class="my-0">
                <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div>
                        <div class="fw-semibold">Logout of MediCycle</div>
                        <div class="text-muted small">End your session on this device when you are finished.</div>
                    </div>
                    <a href="/medicycle/logout.php" class="btn btn-danger">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($admin !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('adminProfileForm');
    const submitButton = document.getElementById('adminProfileSubmit');

    <?php if ($justUpdated): ?>
    Swal.fire({
        icon: 'success',
        title: 'Profile updated',
        text: 'Your name has been saved.',
        confirmButtonText: 'Great',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    }).then(function () {
        history.replaceState({}, '', 'profile.php');
    });
    <?php elseif ($formError !== null): ?>
    Swal.fire({
        icon: 'error',
        title: 'Profile not updated',
        text: <?= json_encode($formError) ?>,
        confirmButtonText: 'Try again',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
    <?php endif; ?>

    form.addEventListener('submit', function () {
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
