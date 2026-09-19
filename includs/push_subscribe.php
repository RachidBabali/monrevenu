<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/audit.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non connecté.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input   = json_decode(file_get_contents('php://input'), true) ?? [];

if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
    auditCsrf($pdo, 'push_subscribe');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expirée.']);
    exit();
}

$action = $input['action'] ?? '';

if ($action === 'subscribe') {
    $endpoint = trim($input['endpoint'] ?? '');
    $p256dh   = trim($input['keys']['p256dh'] ?? '');
    $auth     = trim($input['keys']['auth'] ?? '');
    $ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Abonnement incomplet.']);
        exit();
    }

    try {
        // ON DUPLICATE KEY : si cet endpoint est déjà enregistré (même appareil
        // qui se réabonne, ou par un autre utilisateur sur un appareil partagé),
        // on rattache l'abonnement au user_id courant.
        $stmt = $pdo->prepare(
            "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh),
                                     auth = VALUES(auth), user_agent = VALUES(user_agent)"
        );
        $stmt->execute([$user_id, $endpoint, $p256dh, $auth, $ua]);
        // Service de push seulement (hote du point de terminaison), jamais l'adresse complete ni les cles
        auditInfo($pdo, ['category' => 'compte', 'action' => 'push_abonnement', 'entity_type' => 'utilisateur', 'entity_id' => $user_id,
            'meta' => ['service' => parse_url($endpoint, PHP_URL_HOST)]]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        error_log('push_subscribe.php (subscribe) : ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Erreur serveur.']);
    }
    exit();
}

if ($action === 'unsubscribe') {
    $endpoint = trim($input['endpoint'] ?? '');
    if ($endpoint === '') {
        http_response_code(400);
        echo json_encode(['ok' => false]);
        exit();
    }
    try {
        $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?")
            ->execute([$endpoint, $user_id]);
        auditInfo($pdo, ['category' => 'compte', 'action' => 'push_desabonnement', 'entity_type' => 'utilisateur', 'entity_id' => $user_id,
            'meta' => ['service' => parse_url($endpoint, PHP_URL_HOST)]]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false]);
    }
    exit();
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Action inconnue.']);