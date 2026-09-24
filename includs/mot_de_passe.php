<?php
/**
 * includs/mot_de_passe.php : regle unique du mot de passe, pour tous les roles.
 * Inscription, changement depuis le profil et reinitialisation passent par erreurMotDePasse().
 * Regle : 8 a 64 caracteres, au moins une lettre, un chiffre et un caractere special (ni lettre ni
 * chiffre). La casse est conservee telle que saisie. Stockage : bcrypt (password_hash).
 */

if (!defined('MOT_DE_PASSE_AIDE')) {
    define('MOT_DE_PASSE_AIDE', '8 caractères minimum, avec au moins une lettre, un chiffre et un caractère spécial.');
}

if (!function_exists('erreurMotDePasse')) {
    /** Message d'erreur lisible, ou null si le mot de passe respecte la regle. */
    function erreurMotDePasse(string $mdp): ?string
    {
        if (mb_strlen($mdp) < 8) return 'Le mot de passe doit contenir au moins 8 caractères.';
        // bcrypt ignore tout ce qui depasse 72 octets : on refuse plutot que de tronquer en silence
        if (mb_strlen($mdp) > 64 || strlen($mdp) > 72) return 'Le mot de passe est trop long (64 caractères au maximum).';
        if (!preg_match('/\p{L}/u', $mdp) || !preg_match('/\p{Nd}/u', $mdp) || !preg_match('/[^\p{L}\p{N}]/u', $mdp)) {
            return 'Le mot de passe doit contenir au moins une lettre, un chiffre et un caractère spécial.';
        }
        return null;
    }
}
