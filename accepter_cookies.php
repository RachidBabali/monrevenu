<?php
$cookieName = 'cookies_accepted';
$expire = time() + 365 * 24 * 3600; // 1 an
setcookie($cookieName, '1', $expire, '/', '', true, true);

$redirect = $_GET['redirect'] ?? '/dashboard_jeu.php';
// N'accepter qu'un chemin interne (meme origine) : refuser les URL absolues (https://...),
// les protocol-relative (//hote) et les anti-slash (/\hote) qui redirigeraient hors du site.
if (!preg_match('#^/[^/\\\\]#', $redirect)) {
    $redirect = '/dashboard_jeu.php';
}
header("Location: $redirect");
exit;