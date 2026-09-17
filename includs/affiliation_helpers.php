<?php
/**
 * includs/affiliation_helpers.php
 * Constantes et règle de commission partagées entre services/boutique.php
 * (génération des liens) et produit.php (vérification des liens). Centralisé
 * ici pour éviter que les deux fichiers divergent (secret différent = tous
 * les liens deviennent invalides, formule différente = commission incohérente).
 *
 * Requiert env_loader.php (déjà inclus par basse_de_donner/monrevenu_bd.php,
 * mais on s'assure d'avoir env() disponible même si ce fichier est inclus seul).
 */

require_once __DIR__ . '/env_loader.php';

if (!defined('SECRET_AFFILIATION')) {
    // À définir dans .env (clé AFFILIATION_SECRET) avec une valeur aléatoire
    // longue et unique. La valeur ci-dessous n'est qu'un filet de sécurité
    // pour ne pas casser les liens existants si .env n'est pas encore réglé.
    define('SECRET_AFFILIATION', env('AFFILIATION_SECRET', 'change-moi-avec-une-longue-cle-aleatoire-unique'));

    if (env('AFFILIATION_SECRET') === null) {
        error_log('[affiliation_helpers] AFFILIATION_SECRET absent du .env — clé de repli utilisée, à corriger avant mise en production.');
    }
}

if (!defined('SEUIL_PRIX_COMMISSION')) {
    define('SEUIL_PRIX_COMMISSION', 10000);
}
if (!defined('COMMISSION_BASSE')) {
    define('COMMISSION_BASSE', 500);
}
if (!defined('COMMISSION_HAUTE')) {
    define('COMMISSION_HAUTE', 1000);
}

if (!function_exists('calculerCommission')) {
    function calculerCommission(float $prix): int
    {
        return $prix <= SEUIL_PRIX_COMMISSION ? COMMISSION_BASSE : COMMISSION_HAUTE;
    }
}
