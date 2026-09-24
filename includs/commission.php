<?php
/**
 * includs/commission.php : supplement par tranches, repartition affilie / plateforme, snapshot.
 *
 * Le commercant fixe un prix net ; un supplement progressif (chaque tranche taxee a son propre
 * taux) est ajoute pour former le prix affiche. Le supplement est borne (plancher, plafond) puis
 * arrondi au multiple superieur ; l'affilie touche sa part du supplement (arrondie a l'inferieur).
 *
 * Bareme, repartition et regles viennent de la base (tables commission_*), jamais du code.
 * Le detail du calcul (retour complet de commissionCalculer) ne sort JAMAIS vers l'affilie :
 * il est reserve au commercant (prix final) et a l'administration. Chaque commande en garde une
 * copie figee (commissionSnapshot) qui n'est jamais recalculee.
 */

if (!function_exists('commissionConfig')) {
    /** Bareme et regles d'un marche, lus en base. */
    function commissionConfig(PDO $pdo, string $marche): array
    {
        $st = $pdo->prepare("SELECT borne_min, borne_max, taux FROM commission_brackets WHERE marche = ? ORDER BY ordre");
        $st->execute([$marche]);
        $brackets = array_map(fn($r) => [
            'min'  => (float) $r['borne_min'],
            'max'  => $r['borne_max'] === null ? null : (float) $r['borne_max'],
            'taux' => (float) $r['taux'],
        ], $st->fetchAll(PDO::FETCH_ASSOC));

        $st = $pdo->prepare("SELECT part_affilie, part_plateforme, supplement_min, supplement_max, arrondi FROM commission_settings WHERE marche = ?");
        $st->execute([$marche]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$brackets || !$r) {
            throw new RuntimeException("Barème de commission absent pour le marché $marche");
        }
        return [
            'marche'          => $marche,
            'brackets'        => $brackets,
            'part_affilie'    => (float) $r['part_affilie'],
            'part_plateforme' => (float) $r['part_plateforme'],
            'min'             => (float) $r['supplement_min'],
            'max'             => (float) $r['supplement_max'],
            'arrondi'         => max(1, (int) $r['arrondi']),
        ];
    }
}

if (!function_exists('commissionCalculer')) {
    /** Calcul pur (sans base) : reutilisable sur un snapshot. Reserve a l'interne. */
    function commissionCalculer(float $prixNet, array $cfg): array
    {
        $brut = 0.0;
        $detail = [];
        foreach ($cfg['brackets'] as $b) {
            if ($prixNet <= $b['min']) break;
            $haut = $b['max'] === null ? $prixNet : min($prixNet, $b['max']);
            $assiette = $haut - $b['min'];
            $montant = $assiette * $b['taux'] / 100;
            $detail[] = ['min' => $b['min'], 'max' => $b['max'], 'taux' => $b['taux'], 'assiette' => $assiette, 'montant' => $montant];
            $brut += $montant;
        }
        $borne = max($cfg['min'], min($cfg['max'], $brut));
        $supplement = (int) (ceil($borne / $cfg['arrondi']) * $cfg['arrondi']);
        $gainAffilie = (int) floor($supplement * $cfg['part_affilie'] / 100);
        return [
            'prix_net'         => $prixNet,
            'supplement_brut'  => $brut,
            'supplement'       => $supplement,
            'prix_final'       => $prixNet + $supplement,
            'gain_affilie'     => $gainAffilie,
            'part_plateforme'  => $supplement - $gainAffilie,
            'detail_tranches'  => $detail,
        ];
    }
}

if (!function_exists('commissionSnapshot')) {
    /** Copie figee du calcul et de la config utilisee, a stocker dans vendeur_ventes.calcul_snapshot. */
    function commissionSnapshot(float $prixNet, array $cfg): string
    {
        return json_encode(['config' => $cfg, 'resultat' => commissionCalculer($prixNet, $cfg), 'calcule_le' => date('c')],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

if (!function_exists('commissionDepuisPrixFinal')) {
    /**
     * Retrouve le calcul a partir du prix AFFICHE (prix net + supplement).
     * Le prix final croit strictement avec le prix net entier : recherche dichotomique du plus grand
     * prix net dont le prix final ne depasse pas le prix donne. Interne : ne jamais renvoyer le
     * resultat complet a un affilie, seulement gain_affilie.
     */
    function commissionDepuisPrixFinal(float $prixFinal, array $cfg): array
    {
        $bas = 0;
        $haut = (int) floor($prixFinal);
        while ($bas < $haut) {
            $mil = intdiv($bas + $haut + 1, 2);
            if (commissionCalculer((float) $mil, $cfg)['prix_final'] <= $prixFinal) $bas = $mil; else $haut = $mil - 1;
        }
        return commissionCalculer((float) $bas, $cfg);
    }
}

if (!function_exists('commissionConfigMarche')) {
    /** Config d'un marche, lue une seule fois par requete. */
    function commissionConfigMarche(string $marche): array
    {
        static $cache = [];
        if (!isset($cache[$marche])) {
            if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
                throw new RuntimeException('Connexion base indisponible pour le calcul de commission');
            }
            $cache[$marche] = commissionConfig($GLOBALS['pdo'], $marche);
        }
        return $cache[$marche];
    }
}

if (!function_exists('commissionRecalculerProduits')) {
    /**
     * Recalcule le prix affiche (prix_vente) des produits d'un marche a partir de leur prix net, apres
     * un changement de bareme. Les produits sans prix net prennent leur prix actuel comme prix net.
     * Les commandes deja passees ne sont jamais touchees (elles gardent leur snapshot).
     * Retourne le nombre de produits dont le prix affiche a change.
     */
    function commissionRecalculerProduits(PDO $pdo, string $marche): int
    {
        $cfg = commissionConfig($pdo, $marche);
        $iso = marche($marche)['devise'];
        $st = $pdo->prepare("SELECT vp.id, vp.prix_vente, vp.prix_net FROM vendeur_produits vp
            LEFT JOIN users_monrevenu u ON u.id = vp.vendeur_id
            WHERE " . deviseEffectiveSql('vp', 'u') . " = ?");
        $st->execute([$iso]);
        $maj = $pdo->prepare("UPDATE vendeur_produits SET prix_net = ?, prix_vente = ? WHERE id = ?");
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $net = $p['prix_net'] !== null ? (float) $p['prix_net'] : (float) $p['prix_vente'];
            $final = commissionCalculer($net, $cfg)['prix_final'];
            if ($p['prix_net'] === null || (float) $p['prix_vente'] !== (float) $final) {
                $maj->execute([$net, $final, $p['id']]);
                $n++;
            }
        }
        return $n;
    }
}
