<?php
/**
 * includs/auth_middleware.php
 * Vérifications d'accès réutilisables, à appeler en tout début de page
 * (après session_start() et le require de monrevenu_bd.php).
 */

if (!function_exists('exigerConnexion')) {
    function exigerConnexion(): void
    {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            header('Location: /index.php');
            exit();
        }
    }
}

if (!function_exists('exigerTelephoneVerifie')) {
    /**
     * Bloque l'accès à une page tant que le compte n'a pas de téléphone
     * vérifié — notamment les comptes créés via Google Sign-In, qui
     * n'ont jamais de téléphone à l'inscription et pourraient sinon
     * contourner totalement la vérification de pays/numéro.
     * Redirige vers la page de complétion du profil.
     */
    function exigerTelephoneVerifie(PDO $pdo): void
    {
        exigerConnexion();

        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            header('Location: /index.php');
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT phone_verified FROM users_monrevenu WHERE id = ?");
            $stmt->execute([$user_id]);
            $verifie = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('exigerTelephoneVerifie : ' . $e->getMessage());
            return; // en cas d'erreur DB, on ne bloque pas l'utilisateur par précaution
        }

        if ($verifie !== 1) {
            header('Location: /completer-telephone.php');
            exit();
        }
    }
}