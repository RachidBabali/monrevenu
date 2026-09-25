<?php
/**
 *               AUTH MIDDLEWARE, Mon Revenu               
 *   Sécurité centralisée : sessions, CSRF, rôles           
 */

//  Session : reglages poses par includs/session.php (avant session_start)
require_once __DIR__ . '/../includs/session.php';
require_once __DIR__ . '/../includs/ip_client.php';
demarrerSession();

//  En-têtes sécurité 
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// Filtre XSS legacy des navigateurs : desactive explicitement (valeur 0) suivant la
// recommandation OWASP actuelle ; "1; mode=block" est deprecie et peut introduire des
// failles. La vraie protection est la CSP + l'echappement de sortie.
header('X-XSS-Protection: 0');
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

    // Verrouillage par reseau (/48 IPv6, /24 IPv4) : une IP qui change dans le meme reseau (IPv6 a extensions
    // temporaires, Wi-Fi vers 4G du meme operateur) est acceptee, un autre reseau est refuse.
    $ipCourante = ipClient();
    if (isset($_SESSION['ip'])) {
        $etat = reseauSessionCompatible((string) $_SESSION['ip'], $ipCourante);
        if ($etat === 'different') {
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                require_once __DIR__ . '/../includs/audit.php';
                auditInfo($GLOBALS['pdo'], ['category' => 'auth', 'action' => 'session_ip_changee', 'result' => 'refus',
                    'meta' => ['ip_precedente' => prefixeReseau($_SESSION['ip']), 'ip_actuelle' => prefixeReseau($ipCourante)]]);
            }
            session_destroy();
            header("Location: {$redirect}?error=session"); exit();
        }
        if ($etat === 'meme_reseau') {
            session_regenerate_id(true);
            $_SESSION['regenerated'] = time();
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                require_once __DIR__ . '/../includs/audit.php';
                auditInfo($GLOBALS['pdo'], ['category' => 'auth', 'action' => 'session_ip_meme_reseau', 'result' => 'ok',
                    'meta' => ['reseau' => prefixeReseau($ipCourante)]]);
            }
        }
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
    $_SESSION['ip']         = $ipCourante;
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