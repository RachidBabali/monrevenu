<?php
/**
 * includs/session.php : demarrage unique de la session, a utiliser a la place de session_start().
 *
 * Les reglages sont poses ici, avant session_start(), car ceux de l'hebergeur (hPanel) ne sont pas modifies :
 * mode strict, cookies seulement, HttpOnly, SameSite=Lax (le retour Google est un POST du meme site avec le jeton
 * CSRF de la session : Lax convient), Secure des que la requete arrive en HTTPS, y compris derriere Cloudflare.
 */
require_once __DIR__ . '/journal_erreurs.php';

if (!function_exists('requeteHttps')) {
    function requeteHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
        return str_contains((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"scheme":"https"');
    }
}

if (!function_exists('demarrerSession')) {
    function demarrerSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => requeteHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
