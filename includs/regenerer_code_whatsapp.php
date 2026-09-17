<?php
/**
 * Endpoint AJAX : régénère le code de vérification WhatsApp de l'utilisateur connecté.
 * Appelé en POST par sections/banniere_verification_whatsapp.php.
 *
 * Adapter le require du CSRF/session au mécanisme exact du projet si différent
 * (le contexte mentionne un meta csrf-token déjà présent sur dashboard.php).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php'; // fournit $pdo
require_once __DIR__ . '/whatsapp_verif_helpers.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expirée, reconnectez-vous.']);
    exit;
}

// Vérification CSRF — adapter au token déjà généré ailleurs dans le projet.
$csrfRecu = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfRecu)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}

// Si déjà vérifié, rien à régénérer
$stmt = $pdo->prepare("SELECT phone_verified FROM users_monrevenu WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ((int) $stmt->fetchColumn() === 1) {
    echo json_encode(['success' => false, 'message' => 'Compte déjà vérifié.']);
    exit;
}

$verif = genererOuRecupererCodeVerificationWhatsapp($pdo, (int) $_SESSION['user_id'], forcer: true);

if (!$verif['regenere']) {
    echo json_encode([
        'success' => false,
        'message' => 'Merci de patienter quelques secondes avant de régénérer à nouveau.',
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'code' => $verif['code'],
    'expire_at_ms' => strtotime($verif['expire_at']) * 1000,
]);