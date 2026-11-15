<?php
/**
 * MediCycle — global header: sidebar + top navbar.
 *
 * Optional variables set before including this file:
 *   $pageTitle, $pageSubtitle, $activePage,
 *   $userRole ('admin' | 'org' | 'donor'), $userName, $userEmail
 */

// Absolute URL path of the application. Assets and the logout link are built
// from it; sidebar and dropdown links are written as absolute paths so they
// never resolve relative to the folder of the including page.
$appBase = '/medicycle';

$pageTitle    = $pageTitle    ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$activePage   = $activePage   ?? 'dashboard';

$userRole  = $userRole  ?? ($_SESSION['role']      ?? 'admin');
$userName  = $userName  ?? ($_SESSION['user_name'] ?? 'System Admin');
$userEmail = $userEmail ?? ($_SESSION['email']     ?? 'admin@medicycle.com');

$userInitial  = strtoupper(substr($userName, 0, 1));
$roleLabel    = match ($userRole) {
    'org'   => 'Organization',
    'donor' => 'Donor',
    default => 'Administrator',
};
$roleBadgeCls = match ($userRole) {
    'org'   => 'role-org',
    'donor' => 'role-donor',
    default => 'role-admin',
};
$profileUrl = match ($userRole) {
    'org'   => '/medicycle/organization/profile.php',
    'donor' => null,
    default => '/medicycle/admin/profile.php',
};
$changePasswordUrl = match ($userRole) {
    'org'   => '/medicycle/organization/change_password.php',
    'donor' => '/medicycle/change_password.php',
    default => '/medicycle/admin/change_password.php',
};

$navSections = match ($userRole) {
    'org' => [
        'Overview' => [
            ['key' => 'dashboard',  'label' => 'Dashboard',     'icon' => 'fa-solid fa-chart-pie',        'href' => '/medicycle/organization/dashboard.php'],
        ],
        'Requests' => [
            ['key' => 'new-request',   'label' => 'New Request',     'icon' => 'fa-solid fa-circle-plus',  'href' => '/medicycle/organization/request.php'],
            ['key' => 'my-requests',   'label' => 'My Requests',     'icon' => 'fa-solid fa-clipboard-list','href' => '/medicycle/organization/my_requests.php'],
            ['key' => 'history',       'label' => 'Request History', 'icon' => 'fa-solid fa-clock-rotate-left','href' => '/medicycle/organization/history.php'],
            ['key' => 'my-distributions', 'label' => 'My Distributions', 'icon' => 'fa-solid fa-truck-medical', 'href' => '/medicycle/organization/my_distributions.php'],
        ],
        'Account' => [
            ['key' => 'profile', 'label' => 'Organization Profile', 'icon' => 'fa-solid fa-hospital-user', 'href' => '/medicycle/organization/profile.php'],
        ],
    ],
    'donor' => [
        'Overview' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-solid fa-chart-pie', 'href' => '/medicycle/donor/dashboard.php'],
        ],
        'Donations' => [
            ['key' => 'donate', 'label' => 'Make a Donation', 'icon' => 'fa-solid fa-hand-holding-heart', 'href' => '/medicycle/donor/donate.php'],
        ],
    ],
    default => [
        'Overview' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-solid fa-chart-pie', 'href' => '/medicycle/admin/dashboard.php'],
        ],
        'Inventory' => [
            ['key' => 'donations',  'label' => 'Donations',        'icon' => 'fa-solid fa-hand-holding-heart', 'href' => '/medicycle/admin/donations.php'],
            ['key' => 'medicines',  'label' => 'Medicines',        'icon' => 'fa-solid fa-pills',              'href' => '/medicycle/admin/medicines.php'],
            ['key' => 'batches',    'label' => 'Medicine Batches', 'icon' => 'fa-solid fa-boxes-stacked',      'href' => '/medicycle/admin/batches.php'],
            ['key' => 'expiring',   'label' => 'Near Expiry',      'icon' => 'fa-solid fa-hourglass-half',     'href' => '/medicycle/admin/near_expiry.php'],
        ],
        'Workflow' => [
            ['key' => 'organizations', 'label' => 'Organizations', 'icon' => 'fa-solid fa-building-circle-check', 'href' => '/medicycle/admin/verify_org.php'],
            ['key' => 'requests',      'label' => 'Requests',      'icon' => 'fa-solid fa-clipboard-list',        'href' => '/medicycle/admin/review_requests.php'],
            ['key' => 'distributions', 'label' => 'Distributions', 'icon' => 'fa-solid fa-truck-medical',         'href' => '/medicycle/admin/distributions.php'],
        ],
        'Insights' => [
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'fa-solid fa-chart-column', 'href' => '/medicycle/admin/reports.php'],
        ],
    ],
};

ob_start();
foreach ($navSections as $sectionTitle => $navItems):
    ?>
    <div class="nav-section">
        <p class="nav-section-title"><?= htmlspecialchars($sectionTitle) ?></p>
        <?php foreach ($navItems as $navItem):
            $isActive = $activePage === $navItem['key'];
            ?>
            <a class="sidebar-link<?= $isActive ? ' active' : '' ?>"
               href="<?= htmlspecialchars($navItem['href']) ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <i class="<?= htmlspecialchars($navItem['icon']) ?>"></i>
                <span><?= htmlspecialchars($navItem['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
endforeach;
$sidebarNavHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> · MediCycle</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/assets/css/style.css">
</head>
<body>

<div class="app-wrapper">

    <!-- Desktop sidebar -->
    <aside class="sidebar d-none d-lg-flex flex-column">
        <div class="sidebar-brand">
            <span class="brand-mark"><i class="fa-solid fa-notes-medical"></i></span>
            <span class="brand-text">
                <strong>MediCycle</strong>
                <small>Medicine Donation Platform</small>
            </span>
        </div>

        <nav class="sidebar-nav" aria-label="Main navigation">
            <?= $sidebarNavHtml ?>
        </nav>

        <div class="sidebar-footer">
            <span class="badge rounded-pill <?= $roleBadgeCls ?>">
                <i class="fa-solid fa-user-shield me-1"></i><?= htmlspecialchars($roleLabel) ?>
            </span>
            <small class="sidebar-version">MediCycle v1.0</small>
        </div>
    </aside>

    <!-- Mobile sidebar (offcanvas) -->
    <div class="offcanvas offcanvas-start sidebar sidebar-mobile" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
        <div class="offcanvas-header pb-0">
            <div class="sidebar-brand" id="mobileSidebarLabel">
                <span class="brand-mark"><i class="fa-solid fa-notes-medical"></i></span>
                <span class="brand-text">
                    <strong>MediCycle</strong>
                    <small>Medicine Donation Platform</small>
                </span>
            </div>
            <button type="button" class="btn icon-btn ms-auto" data-bs-dismiss="offcanvas" aria-label="Close menu">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0 pt-3">
            <nav class="sidebar-nav flex-grow-1" aria-label="Mobile navigation">
                <?= $sidebarNavHtml ?>
            </nav>
            <div class="sidebar-footer">
                <span class="badge rounded-pill <?= $roleBadgeCls ?>">
                    <i class="fa-solid fa-user-shield me-1"></i><?= htmlspecialchars($roleLabel) ?>
                </span>
                <small class="sidebar-version">MediCycle v1.0</small>
            </div>
        </div>
    </div>

    <div class="app-main">

        <!-- Top navbar -->
        <header class="topbar sticky-top">
            <div class="topbar-inner">
                <button class="btn icon-btn d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"
                        aria-controls="mobileSidebar" aria-label="Open menu">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="topbar-heading">
                    <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                    <?php if ($pageSubtitle !== ''): ?>
                        <p class="page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>

                <div class="ms-auto d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar"><?= htmlspecialchars($userInitial) ?></span>
                            <span class="user-meta d-none d-sm-flex">
                                <span class="user-name"><?= htmlspecialchars($userName) ?></span>
                                <span class="user-role"><?= htmlspecialchars($roleLabel) ?></span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end user-menu">
                            <li class="px-3 py-2">
                                <div class="user-menu-name"><?= htmlspecialchars($userName) ?></div>
                                <div class="user-menu-email"><?= htmlspecialchars($userEmail) ?></div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($profileUrl !== null): ?>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($profileUrl) ?>">
                                    <i class="fa-regular fa-user me-2"></i>My Profile
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($changePasswordUrl) ?>">
                                    <i class="fa-solid fa-key me-2"></i>Change Password
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($appBase) ?>/logout.php">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <main class="app-content">
