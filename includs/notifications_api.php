<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/audit.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Marquer toutes les notifications comme lues
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
        auditCsrf($pdo, 'notifications');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Session expirée.']);
        exit();
    }
    if (($input['action'] ?? '') === 'marquer_tout_lu') {
        try {
            $st = $pdo->prepare("UPDATE messages SET statut = 'lu' WHERE user_id = ? AND statut = 'non_lu'");
            $st->execute([$user_id]);
            auditInfo($pdo, ['category' => 'compte', 'action' => 'messages_tout_lu', 'entity_type' => 'utilisateur', 'entity_id' => $user_id,
                'meta' => ['nombre' => $st->rowCount()]]);
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false]);
        }
        exit();
    }
    // Un seul message : le WHERE user_id = ? empeche de toucher au message d'un autre utilisateur
    if (($input['action'] ?? '') === 'marquer_lu') {
        $message_id = (int) ($input['id'] ?? 0);
        try {
            $st = $pdo->prepare("UPDATE messages SET statut = 'lu' WHERE id = ? AND user_id = ?");
            $st->execute([$message_id, $user_id]);
            if ($st->rowCount() > 0) {
                auditInfo($pdo, ['category' => 'compte', 'action' => 'message_lu', 'entity_type' => 'message', 'entity_id' => $message_id]);
            }
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false]);
        }
        exit();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Action inconnue.']);
    exit();
}

// GET : liste des dernières notifications + compteur non lu
try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND statut = 'non_lu'");
    $stmtCount->execute([$user_id]);
    $unread = (int) $stmtCount->fetchColumn();

    $stmtListe = $pdo->prepare(
        "SELECT id, expediteur, message, statut, created_at
         FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 15"
    );
    $stmtListe->execute([$user_id]);
    $notifications = array_map(static function ($n) {
        return [
            'id'         => (int) $n['id'],
            'expediteur' => $n['expediteur'],
            'message'    => $n['message'],
            'non_lu'     => $n['statut'] === 'non_lu',
            'date'       => date('d/m/Y à H:i', strtotime($n['created_at'])),
        ];
    }, $stmtListe->fetchAll());

    echo json_encode(['ok' => true, 'unread' => $unread, 'notifications' => $notifications]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}