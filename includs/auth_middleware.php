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
        // Un commercant n'utilise pas les pages d'affiliation : seules son espace, son compte et ses messages lui sont ouverts
        if (($_SESSION['user_role'] ?? '') === 'commercant') {
            $page = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '';
            if (!in_array($page, ['/page/profil.php', '/page/messagerie.php', '/completer-telephone.php'], true)) {
                header('Location: /commercant/index.php');
                exit();
            }
        }
    }
}

if (!function_exists('compteVerifie')) {
    /**
     * Le compte a-t-il verifie son numero de telephone (phone_verified = 1) ? Source de verite : la base,
     * jamais la session ni le client. Fail-closed : toute erreur donne false.
     * Un compte non verifie peut se connecter et naviguer, mais ne voit ni prix ni lien d'affiliation
     * et ne peut rien faire (retrait, stock, signalement) tant qu'il n'a pas verifie son numero.
     */
    function compteVerifie(PDO $pdo, $userId): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT phone_verified FROM users_monrevenu WHERE id = ?");
            $stmt->execute([(int) $userId]);
            return (int) $stmt->fetchColumn() === 1;
        } catch (PDOException $e) {
            error_log('compteVerifie (fail-closed) : ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('exigerAffiliationDebloquee')) {
    /**
     * Bloque l'accès aux pages/actions d'affiliation (boutique, stock, vente)
     * tant que le téléphone n'est pas vérifié. Contrairement à l'ancien
     * exigerTelephoneVerifie(), ne redirige jamais vers completer-telephone.php
     * (retiré du flux) : le compte reste sur le dashboard, où la bannière
     * WhatsApp (sections/banniere_verification_whatsapp.php) explique quoi faire.
     *
     * Fail-closed : toute erreur DB bloque l'accès (contrairement à l'ancienne
     * fonction qui laissait passer l'utilisateur en cas d'exception PDO).
     */
    function exigerAffiliationDebloquee(PDO $pdo): void
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
            error_log('exigerAffiliationDebloquee (fail-closed) : ' . $e->getMessage());
            $_SESSION['flash_error'] = "Une erreur est survenue, merci de réessayer dans un instant.";
            header('Location: /dashboard.php');
            exit();
        }

        if ($verifie !== 1) {
            $_SESSION['flash_error'] = "Vérifiez votre numéro WhatsApp depuis votre tableau de bord pour accéder à cette page.";
            header('Location: /dashboard.php');
            exit();
        }
    }
}