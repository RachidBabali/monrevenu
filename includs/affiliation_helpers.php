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
require_once __DIR__ . '/config_marche.php';
require_once __DIR__ . '/commission.php';

if (!defined('SECRET_AFFILIATION')) {
    // A definir dans .env (cle AFFILIATION_SECRET) avec une valeur aleatoire longue et unique.
    // Valeur absente ou vide : cle de repli (liens existants preserves), signalee en critique
    // par la page Sante et journalisee une fois par jour.
    $secretAffiliation = (string) env('AFFILIATION_SECRET', '');
    define('SECRET_AFFILIATION_REPLI', $secretAffiliation === '');
    define('SECRET_AFFILIATION', SECRET_AFFILIATION_REPLI ? 'change-moi-avec-une-longue-cle-aleatoire-unique' : $secretAffiliation);
    unset($secretAffiliation);

    if (SECRET_AFFILIATION_REPLI) {
        error_log('[affiliation_helpers] AFFILIATION_SECRET absent du .env : clé de repli utilisée.');
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            require_once __DIR__ . '/audit.php';
            auditInfoLimite($GLOBALS['pdo'], 'env_affiliation_secret', 86400, ['category' => 'systeme', 'action' => 'variable_env_manquante',
                'result' => 'echec', 'actor_id' => null, 'actor_role' => null, 'meta' => ['variable' => 'AFFILIATION_SECRET', 'repli' => true]]);
        }
    }
}

if (!function_exists('calculerCommission')) {
    /**
     * Gain de l'affilie pour un produit vendu au prix AFFICHE $prix : sa part du supplement, selon le
     * bareme en base (includs/commission.php). Ne renvoie que le montant, jamais le detail du calcul.
     */
    function calculerCommission(float $prix, ?string $marche = null): int
    {
        $code = marcheValide($marche) ?? marche($marche)['code'];
        return commissionDepuisPrixFinal($prix, commissionConfigMarche($code))['gain_affilie'];
    }
}

if (!function_exists('quantiteMaxCommande')) {
    // Plafond de quantite par commande depuis un lien d'affiliation (QUANTITE_MAX_COMMANDE dans .env, 10 par defaut)
    function quantiteMaxCommande(): int
    {
        $max = (int) env('QUANTITE_MAX_COMMANDE', 10);
        return $max >= 1 ? $max : 10;
    }
}
