<?php
declare(strict_types=1);

session_start();
$wasDonor = ($_SESSION['role'] ?? '') === 'donor';
session_unset();
session_destroy();

// Also expire the session cookie so the browser stops sending a dead session ID.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

header('Location: /medicycle/' . 'index.php');
exit;
