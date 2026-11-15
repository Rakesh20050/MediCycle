<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_organization();

$organizationId = (int) $_SESSION['organization_id'];
$formError      = null;
$justUpdated    = false;
$organization   = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['updated'])) {
    $justUpdated = (bool) ($_SESSION['org_profile_updated'] ?? false);
    unset($_SESSION['org_profile_updated']);
}

try {
    $stmt = $pdo->prepare('SELECT * FROM ORGANIZATION WHERE organization_id = ? LIMIT 1');
    $stmt->execute([$organizationId]);
    $organization = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[MediCycle organization profile] ' . $e->getMessage());
    $formError = pdo_error_message($e);
}

if ($organization === false) {
    $organization = null;
    $formError    = $formError ?? 'Your organization record could not be loaded.';
}

$organizationTypes = ['Hospital', 'NGO', 'Charity', 'Clinic', 'Other'];
$currentType       = (string) ($organization['organization_type'] ?? '');
if ($currentType !== '' && !in_array($currentType, $organizationTypes, true)) {
    $organizationTypes[] = $currentType;
}

$old = [
    'organization_name' => (string) ($organization['organization_name'] ?? ''),
    'organization_type' => $currentType,
    'contact'           => (string) ($organization['contact'] ?? ''),
    'address'           => (string) ($organization['address'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $organization !== null) {
    foreach (array_keys($old) as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $errors = [];

    if (strlen($old['organization_name']) < 2 || strlen($old['organization_name']) > 150) {
        $errors[] = 'Please enter the organization name (2-150 characters).';
    }
    if (!in_array($old['organization_type'], $organizationTypes, true)) {
        $errors[] = 'Please choose an organization type.';
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $old['contact'])) {
        $errors[] = 'Please enter a valid contact number.';
    }
    if (strlen($old['address']) < 5 || strlen($old['address']) > 255) {
        $errors[] = 'Please enter your address (5-255 characters).';
    }

    if ($errors) {
        $formError = implode(' ', $errors);
    } else {
        try {
            $update = $pdo->prepare(
                'UPDATE ORGANIZATION
                 SET organization_name = ?, organization_type = ?, contact = ?, address = ?
                 WHERE organization_id = ?'
            );
            $update->execute([
                $old['organization_name'],
                $old['organization_type'],
                $old['contact'],
                $old['address'],
                $organizationId,
            ]);

            // Keep the navbar chip in sync with the saved name.
            $_SESSION['user_name'] = $old['organization_name'];
            $_SESSION['org_profile_updated'] = true;

            header('Location: profile.php?updated=1');
            exit;
        } catch (PDOException $e) {
            error_log('[MediCycle organization profile] ' . $e->getMessage());
            $formError = pdo_error_message($e);
        }
    }
}

$verificationStatus = (string) ($organization['verification_status'] ?? 'Pending');
$statusBadgeClass   = match ($verificationStatus) {
    'Verified' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    default    => 'badge-soft-warning',
};
$statusIcon = match ($verificationStatus) {
    'Verified' => 'fa-solid fa-circle-check',
    'Rejected' => 'fa-solid fa-circle-xmark',
    default    => 'fa-solid fa-hourglass-half',
};

$pageTitle    = 'Organization Profile';
$pageSubtitle = 'Review and update your organization details';
$activePage   = 'profile';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4 justify-content-center">
    <div class="col-lg-9 col-xl-8">

        <?php if ($organization === null): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                <?= htmlspecialchars($formError ?? 'Your organization record could not be loaded.') ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>
                        <i class="fa-solid fa-hospital-user me-2 text-primary"></i>Organization details
                    </span>
                    <span class="badge rounded-pill <?= $statusBadgeClass ?>">
                        <i class="<?= $statusIcon ?> me-1"></i><?= htmlspecialchars($verificationStatus) ?>
                    </span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Account status: <strong><?= htmlspecialchars($verificationStatus) ?></strong>.
                        <?php if ($verificationStatus === 'Pending'): ?>
                            An administrator still needs to verify your organization before you can request medicines.
                        <?php elseif ($verificationStatus === 'Verified'): ?>
                            Your organization can request medicines and receive stock.
                        <?php else: ?>
                            Please contact the administrator for more information.
                        <?php endif; ?>
                    </p>

                    <form method="post" action="profile.php" id="orgProfileForm">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="organization_name" class="form-label fw-semibold">Organization name</label>
                                <input type="text" class="form-control" id="organization_name" name="organization_name"
                                       value="<?= htmlspecialchars($old['organization_name']) ?>"
                                       minlength="2" maxlength="150" required>
                            </div>

                            <div class="col-md-5">
                                <label for="organization_type" class="form-label fw-semibold">Organization type</label>
                                <select class="form-select" id="organization_type" name="organization_type" required>
                                    <?php foreach ($organizationTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>"
                                            <?= $old['organization_type'] === $type ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="contact" class="form-label fw-semibold">Contact number</label>
                                <input type="tel" class="form-control" id="contact" name="contact"
                                       value="<?= htmlspecialchars($old['contact']) ?>"
                                       pattern="[0-9+\-\s()]{7,20}" required>
                                <div class="form-hint">Digits, spaces, and + ( ) - only.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">Email address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars((string) ($organization['email'] ?? '')) ?>"
                                       readonly aria-readonly="true">
                                <div class="form-hint">Email cannot be changed. Ask the administrator if it needs updating.</div>
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label fw-semibold">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"
                                          minlength="5" maxlength="255" required><?= htmlspecialchars($old['address']) ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" id="orgProfileSubmit">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Update Profile
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div>
                        <div class="fw-semibold">Sign out of MediCycle</div>
                        <div class="text-muted small">End your session on this device when you are finished.</div>
                    </div>
                    <a href="/medicycle/logout.php" class="btn btn-danger">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>Sign Out
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($organization !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('orgProfileForm');
    const submitButton = document.getElementById('orgProfileSubmit');

    <?php if ($justUpdated): ?>
    Swal.fire({
        icon: 'success',
        title: 'Profile updated',
        text: 'Your organization details have been saved.',
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
