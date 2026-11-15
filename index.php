<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Already signed in? Go straight to the matching dashboard.
if (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}
if (($_SESSION['role'] ?? '') === 'org' && isset($_SESSION['organization_id'])) {
    header('Location: organization/dashboard.php');
    exit;
}
if (($_SESSION['role'] ?? '') === 'donor' && isset($_SESSION['donor_id'])) {
    header('Location: donor/dashboard.php');
    exit;
}

const LOGIN_ROLES = ['donor', 'organization', 'admin'];

$isPost        = $_SERVER['REQUEST_METHOD'] === 'POST';
$loginError    = null;
$loginEmail    = '';
$loginRole     = 'donor';

if ($isPost) {
    $postedRole = (string) ($_POST['role'] ?? '');
    $loginRole  = in_array($postedRole, LOGIN_ROLES, true) ? $postedRole : 'donor';
    $loginEmail = trim((string) ($_POST['email'] ?? ''));
    $password   = (string) ($_POST['password'] ?? '');

    if ($loginEmail === '' || $password === '') {
        $loginError = 'Please enter both your email address and password.';
    } else {
        try {
            if ($loginRole === 'donor') {
                $stmt = $pdo->prepare(
                    'SELECT donor_id, donor_name, email, password_hash
                     FROM DONOR
                     WHERE email = ?
                     LIMIT 1'
                );
            } elseif ($loginRole === 'organization') {
                $stmt = $pdo->prepare(
                    'SELECT organization_id, organization_name, email, password_hash
                     FROM ORGANIZATION
                     WHERE email = ?
                     LIMIT 1'
                );
            } else {
                $stmt = $pdo->prepare(
                    'SELECT admin_id, admin_name, email, password_hash
                     FROM ADMIN
                     WHERE email = ?
                     LIMIT 1'
                );
            }

            $stmt->execute([$loginEmail]);
            $account = $stmt->fetch();
            $hash    = $account['password_hash'] ?? null;

            // Guest donor rows keep a NULL hash until they register a portal account.
            if (is_string($hash) && $hash !== '' && password_verify($password, $hash)) {
                session_regenerate_id(true);

                if ($loginRole === 'donor') {
                    $_SESSION['role']      = 'donor';
                    $_SESSION['donor_id']  = (int) $account['donor_id'];
                    $_SESSION['user_name'] = $account['donor_name'];
                    $redirectTo = 'donor/dashboard.php';
                } elseif ($loginRole === 'organization') {
                    $_SESSION['role']            = 'org';
                    $_SESSION['organization_id'] = (int) $account['organization_id'];
                    $_SESSION['user_name']       = $account['organization_name'];
                    $redirectTo = 'organization/dashboard.php';
                } else {
                    $_SESSION['role']      = 'admin';
                    $_SESSION['admin_id']  = (int) $account['admin_id'];
                    $_SESSION['user_name'] = $account['admin_name'];
                    $redirectTo = 'admin/dashboard.php';
                }

                $_SESSION['email'] = $account['email'];

                header('Location: ' . $redirectTo);
                exit;
            }

            $loginError = 'The email or password you entered is incorrect.';
        } catch (PDOException $e) {
            error_log('[MediCycle login] ' . $e->getMessage());
            $loginError = pdo_error_message($e);
        }
    }
} else {
    $requestedRole = (string) ($_GET['role'] ?? '');
    if (in_array($requestedRole, LOGIN_ROLES, true)) {
        $loginRole = $requestedRole;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In · MediCycle</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">

<main class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-mark"><i class="fa-solid fa-notes-medical"></i></span>
            <span class="brand-text">
                <strong>MediCycle</strong>
                <small>Medicine Donation Platform</small>
            </span>
        </div>

        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-subtitle">Sign in as a donor, organization, or system administrator.</p>

        <form method="post" action="index.php">
            <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($loginRole) ?>">

            <div class="role-switch" role="group" aria-label="Sign in as">
                <button type="button"
                        class="role-btn<?= $loginRole === 'donor' ? ' active' : '' ?>"
                        data-role="donor"
                        aria-pressed="<?= $loginRole === 'donor' ? 'true' : 'false' ?>">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                    Donor
                </button>
                <button type="button"
                        class="role-btn<?= $loginRole === 'organization' ? ' active' : '' ?>"
                        data-role="organization"
                        aria-pressed="<?= $loginRole === 'organization' ? 'true' : 'false' ?>">
                    <i class="fa-solid fa-hospital-user"></i>
                    Organization
                </button>
                <button type="button"
                        class="role-btn<?= $loginRole === 'admin' ? ' active' : '' ?>"
                        data-role="admin"
                        aria-pressed="<?= $loginRole === 'admin' ? 'true' : 'false' ?>">
                    <i class="fa-solid fa-user-shield"></i>
                    Admin
                </button>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($loginEmail) ?>"
                           placeholder="you@example.com"
                           autocomplete="email" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="btn password-toggle" id="togglePassword" aria-label="Show password">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 auth-submit">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
            </button>
        </form>

        <p class="auth-register">
            New here? <a href="register.php">Register as a Donor or Organization</a>
        </p>
    </div>

    <p class="auth-meta">&copy; <?= date('Y') ?> MediCycle &middot; DBMS Mini Project</p>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<?php if ($loginError !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'Invalid Credentials',
        text: <?= json_encode($loginError) ?>,
        confirmButtonText: 'Try again',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
});
</script>
<?php endif; ?>
<script>
(function () {
    const roleInput = document.getElementById('roleInput');
    const roleButtons = document.querySelectorAll('.role-btn');

    roleButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            roleInput.value = btn.dataset.role;

            roleButtons.forEach(function (b) {
                const isActive = b === btn;
                b.classList.toggle('active', isActive);
                b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        });
    });

    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');

    toggle.addEventListener('click', function () {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.innerHTML = show
            ? '<i class="fa-regular fa-eye-slash"></i>'
            : '<i class="fa-regular fa-eye"></i>';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        password.focus();
    });
})();
</script>
</body>
</html>
