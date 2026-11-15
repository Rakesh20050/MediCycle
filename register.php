<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Signed-in users do not need the registration page.
if (isset($_SESSION['role'])) {
    $dashboard = match ($_SESSION['role']) {
        'admin' => 'admin/dashboard.php',
        'org'   => 'organization/dashboard.php',
        'donor' => 'donor/dashboard.php',
        default => null,
    };

    if ($dashboard !== null) {
        header('Location: ' . $dashboard);
        exit;
    }
}

$registrationRoles = ['donor', 'organization'];
$organizationTypes = ['Hospital', 'NGO', 'Charity', 'Clinic', 'Other'];

$isPost  = $_SERVER['REQUEST_METHOD'] === 'POST';
$role    = 'donor';
$errors  = [];
$dbError = null;
$success = null;

$form = [
    'donor_name'        => '',
    'phone'             => '',
    'organization_name' => '',
    'organization_type' => '',
    'contact_person'    => '',
    'contact'           => '',
    'address'           => '',
    'email'             => '',
];

// Post/Redirect/Get: the success message is shown once, then the browser moves to sign-in.
if (!$isPost && isset($_GET['registered'])) {
    $success = $_SESSION['registration_success'] ?? null;
    unset($_SESSION['registration_success']);

    if (!is_array($success) || !in_array($success['role'] ?? '', $registrationRoles, true)) {
        header('Location: index.php');
        exit;
    }
}

if ($isPost) {
    $postedRole = (string) ($_POST['role'] ?? '');
    $role       = in_array($postedRole, $registrationRoles, true) ? $postedRole : 'donor';

    foreach (array_keys($form) as $field) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 150) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($form['address']) < 5 || strlen($form['address']) > 255) {
        $errors[] = 'Please enter your address (5-255 characters).';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Your password must be 8-72 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if ($role === 'donor') {
        if (strlen($form['donor_name']) < 2 || strlen($form['donor_name']) > 100) {
            $errors[] = 'Please enter your full name (2-100 characters).';
        }
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $form['phone'])) {
            $errors[] = 'Please enter a valid phone number.';
        }
    } else {
        if (strlen($form['organization_name']) < 2 || strlen($form['organization_name']) > 150) {
            $errors[] = 'Please enter the organization name (2-150 characters).';
        }
        if (!in_array($form['organization_type'], $organizationTypes, true)) {
            $errors[] = 'Please choose an organization type.';
        }
        if (strlen($form['contact_person']) < 2 || strlen($form['contact_person']) > 100) {
            $errors[] = 'Please enter the contact person name (2-100 characters).';
        }
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $form['contact'])) {
            $errors[] = 'Please enter a valid contact number.';
        }
    }

    if (!$errors) {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if ($role === 'donor') {
                $find = $pdo->prepare('SELECT donor_id, password_hash FROM DONOR WHERE email = ? LIMIT 1');
                $find->execute([$form['email']]);
                $existing = $find->fetch();

                if ($existing && $existing['password_hash'] !== null) {
                    $errors[] = 'A donor account already exists for this email. Please sign in instead.';
                } else {
                    if ($existing) {
                        // A guest donation already used this email: attach a portal login to that donor.
                        $update = $pdo->prepare(
                            'UPDATE DONOR
                             SET donor_name = ?, phone = ?, address = ?, password_hash = ?
                             WHERE donor_id = ?'
                        );
                        $update->execute([
                            $form['donor_name'],
                            $form['phone'],
                            $form['address'],
                            $passwordHash,
                            (int) $existing['donor_id'],
                        ]);
                    } else {
                        $insert = $pdo->prepare(
                            'INSERT INTO DONOR (donor_name, email, password_hash, phone, address)
                             VALUES (?, ?, ?, ?, ?)'
                        );
                        $insert->execute([
                            $form['donor_name'],
                            $form['email'],
                            $passwordHash,
                            $form['phone'],
                            $form['address'],
                        ]);
                    }

                    $_SESSION['registration_success'] = [
                        'role' => 'donor',
                        'name' => $form['donor_name'],
                    ];

                    header('Location: register.php?registered=1');
                    exit;
                }
            } else {
                $find = $pdo->prepare('SELECT organization_id FROM ORGANIZATION WHERE email = ? LIMIT 1');
                $find->execute([$form['email']]);

                if ($find->fetch()) {
                    $errors[] = 'An organization account already exists for this email. Please sign in instead.';
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO ORGANIZATION
                            (organization_name, organization_type, address, contact, contact_person, email, password_hash, verification_status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')"
                    );
                    $insert->execute([
                        $form['organization_name'],
                        $form['organization_type'],
                        $form['address'],
                        $form['contact'],
                        $form['contact_person'],
                        $form['email'],
                        $passwordHash,
                    ]);

                    $_SESSION['registration_success'] = [
                        'role' => 'organization',
                        'name' => $form['organization_name'],
                    ];

                    header('Location: register.php?registered=1');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('[MediCycle register] ' . $e->getMessage());
            $dbError = $e->getCode() === '23000'
                ? ($role === 'donor'
                    ? 'A donor account already exists for this email. Please sign in instead.'
                    : 'An organization account already exists for this email. Please sign in instead.')
                : pdo_error_message($e);
        }
    }
} elseif (!$success) {
    $requestedRole = (string) ($_GET['role'] ?? '');
    if (in_array($requestedRole, $registrationRoles, true)) {
        $role = $requestedRole;
    }
}

$successRole = $success['role'] ?? null;
$successName = (string) ($success['name'] ?? '');
$successText = $successRole === 'organization'
    ? ($successName !== '' ? '"' . $successName . '" ' : 'Your organization ')
        . 'has been registered with Pending verification. A system administrator will review it shortly.'
    : ($successName !== '' ? 'Your donor account is ready, ' . $successName . '.' : 'Your donor account is ready.')
        . ' Sign in to submit your first donation and track its status.';

$donorHidden = $role === 'donor' ? '' : ' d-none';
$orgHidden   = $role === 'organization' ? '' : ' d-none';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $success !== null ? 'Account Created' : 'Create Account' ?> · MediCycle</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">

<main class="auth-shell auth-shell-wide">
    <div class="auth-card">
        <div class="auth-header">
            <div class="d-flex align-items-center gap-2">
                <span class="brand-mark"><i class="fa-solid fa-notes-medical"></i></span>
                <span class="brand-text">
                    <strong>MediCycle</strong>
                    <small>Medicine Donation Platform</small>
                </span>
            </div>
            <a href="index.php" class="auth-back">
                <i class="fa-solid fa-arrow-left me-1"></i>Sign in
            </a>
        </div>

        <?php if ($success !== null): ?>
            <div class="d-flex justify-content-center my-3">
                <span class="stat-icon"><i class="fa-solid fa-check"></i></span>
            </div>
            <h1 class="auth-title">Account created</h1>
            <p class="auth-subtitle mb-4"><?= htmlspecialchars($successText) ?></p>
            <a href="index.php" class="btn btn-primary w-100 auth-submit">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Go to sign in
            </a>
        <?php else: ?>
            <h1 class="auth-title text-start">Create your account</h1>
            <p class="auth-subtitle text-start">
                Register as a donor to give medicines, or as an organization to request them.
            </p>

            <form method="post" action="register.php">
                <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($role) ?>">

                <div class="role-switch" role="group" aria-label="Register as">
                    <button type="button"
                            class="role-btn<?= $role === 'donor' ? ' active' : '' ?>"
                            data-role="donor"
                            aria-pressed="<?= $role === 'donor' ? 'true' : 'false' ?>">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                        Register as Donor
                    </button>
                    <button type="button"
                            class="role-btn<?= $role === 'organization' ? ' active' : '' ?>"
                            data-role="organization"
                            aria-pressed="<?= $role === 'organization' ? 'true' : 'false' ?>">
                        <i class="fa-solid fa-hospital-user"></i>
                        Register as Organization
                    </button>
                </div>

                <div class="row g-3">
                    <div class="col-12<?= $donorHidden ?>" data-role-fields="donor">
                        <label for="donor_name" class="form-label">Full name</label>
                        <input type="text" class="form-control" id="donor_name" name="donor_name"
                               value="<?= htmlspecialchars($form['donor_name']) ?>"
                               placeholder="e.g. Rahul Mehta" maxlength="100"
                               autocomplete="name" data-required="true">
                    </div>

                    <div class="col-12<?= $orgHidden ?>" data-role-fields="organization">
                        <label for="organization_name" class="form-label">Organization name</label>
                        <input type="text" class="form-control" id="organization_name" name="organization_name"
                               value="<?= htmlspecialchars($form['organization_name']) ?>"
                               placeholder="e.g. City Care Foundation" maxlength="150"
                               autocomplete="organization" data-required="true">
                    </div>

                    <div class="col-md-6<?= $orgHidden ?>" data-role-fields="organization">
                        <label for="organization_type" class="form-label">Organization type</label>
                        <select class="form-select" id="organization_type" name="organization_type" data-required="true">
                            <option value="" disabled <?= $form['organization_type'] === '' ? 'selected' : '' ?>>Choose a type</option>
                            <?php foreach ($organizationTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"
                                    <?= $form['organization_type'] === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6<?= $orgHidden ?>" data-role-fields="organization">
                        <label for="contact_person" class="form-label">Contact person name</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person"
                               value="<?= htmlspecialchars($form['contact_person']) ?>"
                               placeholder="e.g. Meera Joshi" maxlength="100"
                               autocomplete="name" data-required="true">
                    </div>

                    <div class="col-md-6<?= $donorHidden ?>" data-role-fields="donor">
                        <label for="phone" class="form-label">Phone number</label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               value="<?= htmlspecialchars($form['phone']) ?>"
                               placeholder="+91 98765 43210" maxlength="20"
                               autocomplete="tel" data-required="true">
                    </div>

                    <div class="col-md-6<?= $orgHidden ?>" data-role-fields="organization">
                        <label for="contact" class="form-label">Contact number</label>
                        <input type="tel" class="form-control" id="contact" name="contact"
                               value="<?= htmlspecialchars($form['contact']) ?>"
                               placeholder="+91 98200 11122" maxlength="20"
                               autocomplete="tel" data-required="true">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($form['email']) ?>"
                               placeholder="you@example.com" maxlength="150"
                               autocomplete="email" required>
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"
                                  maxlength="255" placeholder="Street, city, postal code"
                                  autocomplete="street-address" required><?= htmlspecialchars($form['address']) ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="8-72 characters" autocomplete="new-password" required>
                            <button type="button" class="btn password-toggle" id="togglePassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="password_confirm" class="form-label">Confirm password</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                               placeholder="Repeat your password" autocomplete="new-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit mt-4">
                    <i class="fa-solid fa-user-plus me-2"></i>Create Account
                </button>
            </form>

            <div class="auth-hint">
                <div>Already registered? <a href="index.php">Sign in to your account</a></div>
                <div>Organizations start with <strong>Pending</strong> verification until a system administrator reviews them.</div>
            </div>
        <?php endif; ?>
    </div>

    <p class="auth-meta">&copy; <?= date('Y') ?> MediCycle &middot; DBMS Mini Project</p>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<?php if ($success !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'success',
        title: 'Account created',
        text: <?= json_encode($successText) ?>,
        confirmButtonText: 'Go to sign in',
        confirmButtonColor: '#0d9488',
        color: '#111827',
        timer: 4500,
        timerProgressBar: true
    }).then(function () {
        window.location.href = 'index.php';
    });
});
</script>
<?php elseif ($errors || $dbError !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'Could not create account',
        text: <?= json_encode($dbError ?? implode(' ', $errors)) ?>,
        confirmButtonText: 'Fix and retry',
        confirmButtonColor: '#0d9488',
        color: '#111827'
    });
});
</script>
<?php endif; ?>
<?php if ($success === null): ?>
<script>
(function () {
    const roleInput = document.getElementById('roleInput');
    const roleButtons = document.querySelectorAll('.role-btn');
    const fieldBlocks = document.querySelectorAll('[data-role-fields]');

    function applyRole(role) {
        roleInput.value = role;

        roleButtons.forEach(function (btn) {
            const isActive = btn.dataset.role === role;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        fieldBlocks.forEach(function (block) {
            const isActive = block.dataset.roleFields === role;
            block.classList.toggle('d-none', !isActive);

            block.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !isActive;
                if (field.dataset.required === 'true') {
                    field.required = isActive;
                }
            });
        });
    }

    roleButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyRole(btn.dataset.role);
        });
    });

    applyRole(roleInput.value);

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
<?php endif; ?>
</body>
</html>
