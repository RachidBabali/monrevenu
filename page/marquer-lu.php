<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

$user_id    = $_SESSION['user_id'];
$message_id = (int) ($_GET['id'] ?? 0);

if ($message_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit();
}

try {
    // Le WHERE user_id = ? empêche de marquer comme lu un message qui n'appartient pas à cet utilisateur
    $stmt = $pdo->prepare("UPDATE messages SET statut = 'lu' WHERE id = ? AND user_id = ?");
    $stmt->execute([$message_id, $user_id]);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}