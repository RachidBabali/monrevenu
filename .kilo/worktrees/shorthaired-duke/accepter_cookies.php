<?php
$cookieName = 'cookies_accepted';
$expire = time() + 365 * 24 * 3600; // 1 an
setcookie($cookieName, '1', $expire, '/', '', true, true);

$redirect = $_GET['redirect'] ?? '/dashboard_jeu.php';
header("Location: $redirect");
exit;