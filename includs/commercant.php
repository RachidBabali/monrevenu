<?php
/**
 * includs/commercant.php : regles communes de l'espace commercant.
 *
 * Proprietes (decisions du lot 2) :
 *   - un produit de commercant a vendeur_id = identifiant du commercant ;
 *   - le catalogue montre statut = 'actif' ET moderation = 'approuve', et, pour un produit de commercant,
 *     seulement si la boutique est validee (un compte suspendu ne vend plus) ;
 *   - vendeur_ventes.vendeur_id est l'AFFILIE qui touche la commission ; les commandes d'un commercant se
 *     retrouvent par vendeur_ventes.produit_id -> vendeur_produits.vendeur_id.
 */

require_once __DIR__ . '/env_loader.php';

/** Condition SQL du catalogue public ; l'alias du produit est vp, celui du profil cp (LEFT JOIN). */
if (!defined('CATALOGUE_JOINTURE')) {
    define('CATALOGUE_JOINTURE', "LEFT JOIN commercants_profils cp ON cp.user_id = vp.vendeur_id");
    define('CATALOGUE_CONDITION', "vp.statut = 'actif' AND vp.moderation = 'approuve' AND (cp.user_id IS NULL OR cp.statut = 'valide')");
}

/** Etats de commande que le commercant peut donner lui-meme (jamais 'validee'). */
if (!defined('COMMERCANT_TRANSITIONS')) {
    define('COMMERCANT_TRANSITIONS', [
        'en_attente' => ['contacte', 'annulee'],
        'contacte'   => ['colis_recu', 'annulee'],
        'colis_recu' => ['annulee'],
    ]);
}

if (!function_exists('commercantMaxProduitsJour')) {
function commercantMaxProduitsJour(): int { return max(1, (int) env('COMMERCANT_MAX_PRODUITS_JOUR', 20)); }
function commercantMaxProduits(): int { return max(1, (int) env('COMMERCANT_MAX_PRODUITS', 200)); }

/**
 * Exige un commercant connecte (role relu en base, pas seulement en session). Retourne le profil
 * (user_id, nom_boutique, ville, description, statut, motif, confiance, phone_verified).
 */
function exigerCommercant(PDO $pdo): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        header('Location: /index.php#connexion');
        exit();
    }
    $st = $pdo->prepare(
        "SELECT u.id AS user_id, u.role, u.is_active, u.status AS statut_compte, u.phone_verified, u.fullname,
                cp.nom_boutique, cp.ville, cp.description, cp.statut, cp.motif, cp.confiance
         FROM users_monrevenu u LEFT JOIN commercants_profils cp ON cp.user_id = u.id
         WHERE u.id = ? LIMIT 1"
    );
    $st->execute([(int) $_SESSION['user_id']]);
    $profil = $st->fetch(PDO::FETCH_ASSOC);
    if (!$profil || $profil['role'] !== 'commercant' || $profil['nom_boutique'] === null || (int) $profil['is_active'] !== 1) {
        require_once __DIR__ . '/audit.php';
        auditInfo($pdo, ['category' => 'systeme', 'action' => 'acces_refuse', 'result' => 'refus', 'entity_type' => 'page',
            'entity_id' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), 'meta' => ['role_requis' => 'commercant']]);
        http_response_code(403);
        header('Location: ' . (($profil['role'] ?? '') === 'admin' ? '/admin/dashboard_admin.php' : '/dashboard.php'));
        exit();
    }
    return $profil;
}

function commercantPeutPublier(array $profil): bool
{
    return $profil['statut'] === 'valide' && (int) $profil['phone_verified'] === 1;
}

/**
 * Dette de commission : commissions des ventes validees et creditees sur les produits du commercant,
 * moins les reglements enregistres. Montants en chaines decimales.
 */
function detteCommercant(PDO $pdo, int $commercantId): array
{
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(v.commission_earn), 0) FROM vendeur_ventes v
         JOIN vendeur_produits p ON p.id = v.produit_id
         WHERE p.vendeur_id = ? AND v.statut = 'validee' AND v.commission_creditee = 1"
    );
    $st->execute([$commercantId]);
    $du = (string) $st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) FROM commercant_reglements WHERE commercant_id = ?");
    $st->execute([$commercantId]);
    $regle = (string) $st->fetchColumn();
    // Calcul en centimes entiers, jamais en flottant
    $centimes = static fn(string $v): int => (int) round(((float) $v) * 100);
    $solde = $centimes($du) - $centimes($regle);
    return ['commissions' => $du, 'reglements' => $regle, 'solde' => ($solde < 0 ? '-' : '') . intdiv(abs($solde), 100) . '.' . str_pad((string) (abs($solde) % 100), 2, '0', STR_PAD_LEFT)];
}
}
