<?php
/**
 * Envoi de notifications push (Web Push / PWA), via la librairie
 * minishlink/web-push (installée par Composer, voir composer.json).
 *
 * Variables .env requises :
 *   VAPID_PUBLIC_KEY   (clé publique VAPID, format base64url)
 *   VAPID_PRIVATE_KEY  (clé privée VAPID, format base64url)
 *   VAPID_SUBJECT       (ex: mailto:contact@monrevenu.xyz)
 *
 * Les clés se génèrent une seule fois avec :
 *   php includs/generer_cles_vapid.php
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

if (!function_exists('envoyerNotificationPush')) {
    /**
     * Envoie une notification push à TOUS les abonnements enregistrés pour un
     * utilisateur (il peut en avoir plusieurs : téléphone + ordinateur...).
     * Ne bloque jamais l'exécution en cas d'échec (log seulement), une
     * notification push est un bonus, pas une opération critique.
     */
    function envoyerNotificationPush(PDO $pdo, int $userId, string $titre, string $corps, string $lien = '/dashboard.php'): void
    {
        $publicKey  = env('VAPID_PUBLIC_KEY');
        $privateKey = env('VAPID_PRIVATE_KEY');
        $subject    = env('VAPID_SUBJECT', 'mailto:contact@monrevenu.xyz');

        if (!$publicKey || !$privateKey) {
            // Clés VAPID pas encore configurées : on ignore silencieusement le push,
            // la notification "in-app" (table messages) a déjà été enregistrée.
            return;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $abonnements = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('envoyerNotificationPush (lecture abonnements) : ' . $e->getMessage());
            return;
        }

        if (!$abonnements) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $subject,
                    'publicKey'  => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('envoyerNotificationPush (init WebPush, clés VAPID invalides ?) : ' . $e->getMessage());
            return;
        }

        $payload = json_encode([
            'title' => $titre,
            'body'  => $corps,
            'url'   => $lien,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($abonnements as $abo) {
            $subscription = Subscription::create([
                'endpoint' => $abo['endpoint'],
                'keys' => [
                    'p256dh' => $abo['p256dh'],
                    'auth'   => $abo['auth'],
                ],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $rapport) {
            if (!$rapport->isSuccess() && $rapport->isSubscriptionExpired()) {
                // L'abonnement n'est plus valide (désinstallé, permission retirée...) : on le supprime.
                try {
                    $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")
                        ->execute([$rapport->getRequest()->getUri()->__toString()]);
                } catch (PDOException $e) {
                    error_log('envoyerNotificationPush (nettoyage abonnement expiré) : ' . $e->getMessage());
                }
            } elseif (!$rapport->isSuccess()) {
                error_log('envoyerNotificationPush (échec) : ' . $rapport->getReason());
            }
        }
    }
}