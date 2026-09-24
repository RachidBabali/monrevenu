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
    function envoyerNotificationPush(PDO $pdo, int $userId, string $titre, string $corps, string $lien = '/dashboard.php', array $options = []): void
    {
        $publicKey  = env('VAPID_PUBLIC_KEY');
        $privateKey = env('VAPID_PRIVATE_KEY');
        $subject    = env('VAPID_SUBJECT', 'mailto:contact@monrevenu.xyz');

        require_once __DIR__ . '/audit.php';
        if (!$publicKey || !$privateKey) {
            // Clés VAPID absentes : la notification in-app (table messages) est enregistrée, le push est ignoré.
            // Une ligne de journal par heure au plus, pour que l'absence de push reste visible.
            auditInfoLimite($pdo, 'push_sans_cles', 3600, ['category' => 'systeme', 'action' => 'push_ignore_sans_cles', 'result' => 'echec',
                'meta' => ['user_id' => $userId]]);
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
            auditInfo($pdo, ['category' => 'systeme', 'action' => 'push_ignore_sans_abonnement', 'result' => 'echec', 'meta' => ['user_id' => $userId]]);
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $subject,
                    'publicKey'  => $publicKey,
                    'privateKey' => $privateKey,
                ],
            // Temps reel : urgence haute (le service de push reveille l'appareil sans attendre), duree de vie
            // courte (une alerte vieille d'une heure n'a plus d'interet) et delai maximal court pour ne pas
            // retenir la requete de l'utilisateur si le service de push repond lentement.
            ], ['TTL' => 3600, 'urgency' => 'high'], 8);
        } catch (\Throwable $e) {
            error_log('envoyerNotificationPush (init WebPush, clés VAPID invalides ?) : ' . $e->getMessage());
            return;
        }

        $payload = construirePayloadPush($titre, $corps, $lien, $options, compterNonLusPush($pdo, $userId));

        foreach ($abonnements as $abo) {
          try {
            // Cles illisibles : WebPush echouerait sur tout l'envoi, on ecarte l'abonnement des maintenant
            $cleBrute = base64_decode(strtr((string) $abo['p256dh'], '-_', '+/'), true);
            $authBrut = base64_decode(strtr((string) $abo['auth'], '-_', '+/'), true);
            if ($cleBrute === false || strlen($cleBrute) !== 65 || $authBrut === false || strlen($authBrut) < 12) {
                throw new InvalidArgumentException('cles d\'abonnement invalides');
            }
            $subscription = Subscription::create([
                'endpoint' => $abo['endpoint'],
                'keys' => [
                    'p256dh' => $abo['p256dh'],
                    'auth'   => $abo['auth'],
                ],
            ]);
            $webPush->queueNotification($subscription, $payload);
          } catch (\Throwable $e) {
            // Abonnement illisible (cles corrompues) : on le retire au lieu de faire echouer l'envoi
            error_log('envoyerNotificationPush (abonnement invalide) : ' . get_class($e));
            try { $pdo->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$abo['id']]); } catch (PDOException $ignore) {}
            auditInfo($pdo, ['category' => 'systeme', 'action' => 'push_abonnement_invalide', 'result' => 'echec',
                'meta' => ['user_id' => $userId]]);
          }
        }

        $envoyes = 0; $echecs = 0; $expires = 0;
        try {
        foreach ($webPush->flush() as $rapport) {
            if ($rapport->isSuccess()) $envoyes++; elseif ($rapport->isSubscriptionExpired()) $expires++; else $echecs++;
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
        } catch (\Throwable $e) {
            error_log('envoyerNotificationPush (envoi) : ' . get_class($e) . ' ' . $e->getMessage());
            $echecs++;
        }
        auditInfo($pdo, ['category' => 'systeme', 'action' => $envoyes > 0 ? 'push_envoye' : 'push_echec', 'result' => $envoyes > 0 ? 'ok' : 'echec',
            'meta' => ['user_id' => $userId, 'envoyes' => $envoyes, 'echecs' => $echecs, 'abonnements_expires_supprimes' => $expires]]);
    }
}

if (!function_exists('construirePayloadPush')) {
    /**
     * Charge utile d'une notification push : titre court, apercu en une ligne, lien profond,
     * etiquette par type d'evenement (les notifications d'un meme type se remplacent au lieu de s'empiler),
     * image quand elle est utile, compteur pour l'icone de l'application.
     * Aucune donnee sensible : le texte s'affiche sur un ecran verrouille.
     */
    function construirePayloadPush(string $titre, string $corps, string $lien, array $options = [], int $nonLus = 0): string
    {
        return json_encode(array_filter([
            'title'          => mb_substr(trim($titre), 0, 60),
            'body'           => mb_substr(trim(preg_replace('/\s+/u', ' ', $corps)), 0, 160),
            'url'            => $lien !== '' ? $lien : '/dashboard.php',
            'tag'            => $options['tag'] ?? ($options['type'] ?? 'monrevenu'),
            'renotify'       => $options['renotify'] ?? true,
            'timestamp'      => (int) round(microtime(true) * 1000),
            'image'          => $options['image'] ?? null,
            'badge_compteur' => $nonLus,
        ], static fn($v) => $v !== null), JSON_UNESCAPED_UNICODE);
    }

    function compterNonLusPush(PDO $pdo, int $userId): int
    {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND statut = 'non_lu'");
            $st->execute([$userId]);
            return (int) $st->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}
