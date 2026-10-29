<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
session_boot();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], true);
}
session_destroy();
redirect('login.php');
