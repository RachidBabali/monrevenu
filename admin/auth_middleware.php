<?php
/**
 *               AUTH MIDDLEWARE, Mon Revenu               
 *   Sécurité centralisée : sessions, CSRF, rôles           
 */

//  Configuration sécurisée du cookie de session (avant session_start) 
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // true en HTTPS, false en local
        'httponly' => true,      // empêche le JS de lire le cookie (protection XSS)
        'samesite' => 'Lax',     // empêche l'envoi du cookie depuis un site tiers (protection CSRF)
    ]);
    session_start();
}

//  En-têtes sécurité 
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Permitted-Cross-Domain-Policies: none');

//  CSRF token 
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

//  Vérification session de base 
function requireLogin(string $redirect = '/index.php'): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirect"); exit();
    }

    // Verrouillage IP
    if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            require_once __DIR__ . '/../includs/audit.php';
            auditInfo($GLOBALS['pdo'], ['category' => 'auth', 'action' => 'session_ip_changee', 'result' => 'refus',
                'meta' => ['ip_precedente' => tronquerIp($_SESSION['ip'])]]);
        }
        session_destroy();
        header("Location: {$redirect}?error=session"); exit();
    }

    // Expiration 2h
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 7200)) {
        session_destroy();
        header("Location: {$redirect}?error=expired"); exit();
    }

    // Régénération ID toutes les 5 min
    if (!isset($_SESSION['regenerated']) || $_SESSION['regenerated'] < time() - 300) {
        session_regenerate_id(true);
        $_SESSION['regenerated'] = time();
    }

    $_SESSION['login_time'] = time();
    $_SESSION['ip']         = $_SERVER['REMOTE_ADDR'];
}

//  Vérification de rôle 
function requireRole(PDO $pdo, string $role, string $redirect = '/index.php'): array {
    requireLogin($redirect);

    $stmt = $pdo->prepare("SELECT id, fullname, role FROM users_monrevenu WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== $role) {
        require_once __DIR__ . '/../includs/audit.php';
        auditInfo($pdo, ['category' => 'systeme', 'action' => 'acces_refuse', 'result' => 'refus', 'entity_type' => 'page',
            'entity_id' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), 'actor_id' => $user ? (int) $user['id'] : null,
            'actor_role' => $user['role'] ?? null, 'meta' => ['role_requis' => $role]]);
        session_destroy();
        header("Location: $redirect"); exit();
    }

    return $user;
}

//  Vérification CSRF 
function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

//  Helpers globaux 
function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt(mixed $n): string {
    return number_format((float)$n, 0, ',', ' ');
}

function jsonError(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit();
}

function jsonOk(array $data = []): never {
    echo json_encode(array_merge(['ok' => true], $data));
    exit();
}