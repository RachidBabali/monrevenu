<?php
/**
 * Aide centralisée pour l'envoi de notifications aux utilisateurs.
 * Enregistre la notification en base (table `messages`, déjà utilisée par
 * la Messagerie) ET tente un envoi push navigateur/PWA si l'utilisateur a
 * au moins un abonnement actif.
 *
 * Utilisation :
 *   require_once __DIR__ . '/../includs/notifications.php';
 *   envoyerNotification($pdo, $vendeur['id'], "Nouvelle vente en attente...", 'Nouvelle vente', '/page/historique.php',
 *       'MonRevenu', ['type' => 'commande', 'image' => $urlImageProduit]);
 *
 * $options : type (sert d'etiquette de regroupement), image (URL absolue), tag (sinon type), renotify.
 * Aucun nom de client ni numero complet dans le texte : la notification s'affiche sur un ecran verrouille.
 */

require_once __DIR__ . '/webpush_sender.php';

if (!function_exists('envoyerNotification')) {
    function envoyerNotification(
        PDO $pdo,
        int $userId,
        string $message,
        ?string $titrePush = null,
        ?string $lienPush = null,
        string $expediteur = 'MonRevenu',
        array $options = []
    ): void {
        try {
            $stmt = $pdo->prepare(
                // audit:exclu journalise par l action appelante
                "INSERT INTO messages (user_id, expediteur, message, statut) VALUES (?, ?, ?, 'non_lu')"
            );
            $stmt->execute([$userId, $expediteur, $message]);
        } catch (PDOException $e) {
            error_log('envoyerNotification (in-app) : ' . $e->getMessage());
        }

        envoyerNotificationPush(
            $pdo,
            $userId,
            $titrePush ?? 'MonRevenu',
            $message,
            $lienPush ?? '/dashboard.php',
            $options
        );
    }
}

if (!function_exists('compterNotificationsNonLues')) {
    function compterNotificationsNonLues(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND statut = 'non_lu'");
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}