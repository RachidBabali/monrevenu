<?php
/**
 * includs/moyen_paiement.php : operateur mobile money et numero de reception des commissions d'un affilie.
 * Paiement MANUEL : ces informations disent a l'administration ou effectuer le virement. Aucune integration
 * API ; les colonnes fournisseur_api / reference_externe sont reservees a une integration future.
 * L'operateur doit appartenir a la liste du marche du compte (includs/config_marche.php) et le numero doit
 * etre un numero valide de ce marche.
 */
require_once __DIR__ . '/config_marche.php';
require_once __DIR__ . '/audit.php';

if (!function_exists('moyenPaiementDuCompte')) {
    /** Moyen enregistre (operateur, numero) ou null. */
    function moyenPaiementDuCompte(PDO $pdo, int $userId): ?array
    {
        $st = $pdo->prepare("SELECT operateur, numero, marche FROM affilie_moyens_paiement WHERE user_id = ? AND statut = 'actif' LIMIT 1");
        $st->execute([$userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('moyenPaiementValider')) {
    /** Retourne [operateur, numero normalise] ou leve InvalidArgumentException (message lisible). */
    function moyenPaiementValider(string $marche, string $operateur, string $numero): array
    {
        $operateur = trim($operateur);
        if ($operateur === '' || !in_array($operateur, moyensRetrait($marche), true)) {
            throw new InvalidArgumentException('Choisissez un opérateur proposé pour votre pays.');
        }
        if (trim($numero) === '') {
            throw new InvalidArgumentException('Indiquez le numéro qui recevra vos commissions.');
        }
        $norm = normaliserNumero($numero, $marche);
        if ($norm === null || marcheDeNumero($norm) !== $marche) {
            $m = marche($marche);
            throw new InvalidArgumentException('Numéro invalide : indiquez un numéro +' . $m['indicatif'] . ' de ' . $m['longueur_nationale'] . ' chiffres (exemple : ' . $m['exemple_numero'] . ').');
        }
        return [$operateur, $norm];
    }
}

if (!function_exists('moyenPaiementSauvegarder')) {
    /** Enregistre ou remplace le moyen de paiement du compte ; journalise avant / apres. */
    function moyenPaiementSauvegarder(PDO $pdo, int $userId, string $marche, string $operateur, string $numero): void
    {
        [$operateur, $norm] = moyenPaiementValider($marche, $operateur, $numero);
        $avant = moyenPaiementDuCompte($pdo, $userId);
        $pdo->prepare(
            "INSERT INTO affilie_moyens_paiement (user_id, marche, operateur, numero, statut) VALUES (?, ?, ?, ?, 'actif')
             ON DUPLICATE KEY UPDATE marche = VALUES(marche), operateur = VALUES(operateur), numero = VALUES(numero), statut = 'actif'"
        )->execute([$userId, $marche, $operateur, $norm]);
        auditInfo($pdo, ['category' => 'compte', 'action' => $avant ? 'moyen_paiement_modification' : 'moyen_paiement_creation',
            'entity_type' => 'utilisateur', 'entity_id' => $userId,
            'before' => $avant ? ['operateur' => $avant['operateur'], 'numero' => $avant['numero']] : null,
            'after' => ['operateur' => $operateur, 'numero' => $norm]]);
    }
}
