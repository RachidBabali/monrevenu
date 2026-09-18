<?php
/**
 * Webhook Meta WhatsApp Cloud API.
 * URL à configurer dans Meta App Dashboard > WhatsApp > Configuration > Webhook :
 *   https://www.monrevenu.xyz/webhook_whatsapp.php
 * Verify token = valeur de WHATSAPP_WEBHOOK_VERIFY_TOKEN (variable d'environnement Hostinger).
 * S'abonner au champ "messages".
 *
 * IMPORTANT : ne dépend PAS du numéro sortant configuré (WHATSAPP_ACCESS_TOKEN / PHONE_NUMBER_ID
 * peuvent rester des placeholders), on ne fait ici QUE de la réception, qui est toujours
 * gratuite et ne nécessite ni template approuvé ni vérification business.
 *
 * RÈGLE CRITIQUE : le handshake GET (vérification Meta) doit rester la toute première
 * chose exécutée, avant tout require de BD/session/notifications, un plantage dans ces
 * fichiers (connexion BD indisponible, etc.) ne doit jamais empêcher de répondre le challenge.
 */

require_once __DIR__ . '/includs/env_loader.php'; // fournit env(), aucun effet de bord

// --- 1. Handshake de vérification (GET, une seule fois lors de la config du webhook) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? $_GET['hub.mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? $_GET['hub.challenge']    ?? '';

    $verifyTokenAttendu = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', '');

    header('Content-Type: text/plain; charset=utf-8');

    if ($mode === 'subscribe' && $verifyTokenAttendu !== '' && hash_equals($verifyTokenAttendu, $token)) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Verification failed';
    exit;
}

// --- 2. Réception des événements (POST), la BD/les helpers ne sont chargés qu'ici ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/basse_de_donner/monrevenu_bd.php'; // fournit $pdo
require_once __DIR__ . '/includs/whatsapp_verif_helpers.php';
require_once __DIR__ . '/includs/notifications.php'; // pour envoyerNotification()

header('Content-Type: application/json');

$raw = file_get_contents('php://input');

// Vérification de la signature Meta (CRITIQUE : sans ça, n'importe qui peut
// forger une requête POST vers cet endpoint et débloquer un compte à volonté).
$appSecret = env('WHATSAPP_APP_SECRET', '');
$signatureRecue = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$signatureAttendue = 'sha256=' . hash_hmac('sha256', $raw, $appSecret);

if ($appSecret === '' || !hash_equals($signatureAttendue, $signatureRecue)) {
    error_log('[webhook_whatsapp] Signature invalide, requête rejetée.');
    http_response_code(403);
    exit;
}

// Toujours répondre 200 rapidement après ce point, même en cas d'erreur interne,
// sinon Meta considère le webhook défaillant et peut le désactiver après des échecs répétés.
http_response_code(200);

$payload = json_decode($raw, true);
if (!$payload) {
    exit;
}

try {
    foreach ($payload['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];

            foreach ($value['messages'] ?? [] as $message) {
                if ($message['type'] !== 'text') {
                    continue; // on ignore images, réactions, etc.
                }

                $numeroExpediteur = $message['from'] ?? '';
                $texte = $message['text']['body'] ?? '';

                if (!$numeroExpediteur || $texte === '') {
                    continue;
                }

                $resultat = validerCodeWhatsapp($pdo, $texte, $numeroExpediteur);

                if ($resultat['success']) {
                    // Réutilise votre système de notifications existant (in-app + push).
                    // Aucun message WhatsApp sortant n'est envoyé, donc aucun coût/quota consommé.
                    // Ordre des arguments : (pdo, userId, message, titrePush, lienPush), voir includs/notifications.php.
                    envoyerNotification(
                        $pdo,
                        $resultat['user_id'],
                        'Votre numéro a été confirmé. Les fonctionnalités d\'affiliation sont maintenant débloquées.',
                        'Compte vérifié',
                        '/dashboard.php'
                    );
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('[webhook_whatsapp] Erreur : ' . $e->getMessage());
    // On a déjà répondu 200 plus haut, donc pas de retry Meta, normal, l'erreur est loguée.
}