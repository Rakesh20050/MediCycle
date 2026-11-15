<?php
/**
 * Session guards. Call after session_start() from pages inside
 * /admin, /organization or /donor (one level below the project root).
 */
declare(strict_types=1);

function require_admin(): void
{
    if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
        header('Location: ../index.php');
        exit;
    }
}

function require_organization(): void
{
    if (($_SESSION['role'] ?? '') !== 'org' || empty($_SESSION['organization_id'])) {
        header('Location: ../index.php');
        exit;
    }
}

function require_donor(): void
{
    if (($_SESSION['role'] ?? '') !== 'donor' || empty($_SESSION['donor_id'])) {
        header('Location: ../index.php');
        exit;
    }
}
