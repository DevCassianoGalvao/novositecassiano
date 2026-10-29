<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
session_boot();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') redirect('index.php');
csrf_check();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => (bool) $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
}
session_destroy();
redirect('login.php');
