<?php
/**
 * includs/rapprochement.php : rapprochement des soldes. Lecture seule, aucune correction automatique.
 *
 * Regle de calcul du solde attendu, par utilisateur :
 *   - lignes enrichies (balance_before et balance_after renseignes, depuis le lot 2) : on suit la chaine
 *     des mouvements, le dernier balance_after doit egaler le solde reel ;
 *   - lignes anciennes (sans soldes) : somme des credits (depot, commission, jeu_gain de statut complete)
 *     moins les debits (retrait, achat_service, jeu_perte non echoue). Un retrait refuse avant le lot 2
 *     etait recredite sans ecriture : la ligne passait a 'echoue', d'ou l'exclusion des lignes echouees.
 * Les deux methodes se rejoignent quand toutes les lignes d'un compte sont enrichies.
 */

if (!function_exists('rapprochementSoldes')) {
    function centimes($valeur): int { return (int) round(((float) $valeur) * 100); }
    function enFrancs(int $centimes): string { return number_format($centimes / 100, 2, '.', ''); }

    /** @return array{ecarts: array, negatifs: array, doublons: array, ventes_sans_commission: array, commissions_sans_vente: array, retraits_anciens: array, comptes_bloques_avec_liens: array, total_comptes: int} */
    function rapprochementSoldes(PDO $pdo, int $limite = 500): array
    {
        $resultat = ['ecarts' => [], 'negatifs' => [], 'doublons' => [], 'ventes_sans_commission' => [],
            'commissions_sans_vente' => [], 'retraits_anciens' => [], 'comptes_bloques_avec_liens' => [], 'total_comptes' => 0];

        $comptes = $pdo->query(
            "SELECT u.id, u.fullname, u.balance, u.role, u.is_active, u.status
             FROM users_monrevenu u
             WHERE EXISTS (SELECT 1 FROM transactions_monrevenu t WHERE t.user_id = u.id) OR u.balance <> 0
             ORDER BY u.id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $resultat['total_comptes'] = count($comptes);

        $mouvements = $pdo->prepare(
            "SELECT id, type, amount, status, reference, balance_before, balance_after, created_at
             FROM transactions_monrevenu WHERE user_id = ? ORDER BY id"
        );

        foreach ($comptes as $compte) {
            $solde = centimes($compte['balance']);
            if ($solde < 0) {
                $resultat['negatifs'][] = ['user_id' => (int) $compte['id'], 'nom' => $compte['fullname'], 'solde' => $compte['balance']];
            }
            $mouvements->execute([$compte['id']]);
            $lignes = $mouvements->fetchAll(PDO::FETCH_ASSOC);
            if (!$lignes) {
                if ($solde !== 0) {
                    $resultat['ecarts'][] = ['user_id' => (int) $compte['id'], 'nom' => $compte['fullname'],
                        'solde_reel' => $compte['balance'], 'solde_attendu' => '0.00', 'ecart' => $compte['balance'],
                        'raison' => 'solde sans aucun mouvement'];
                }
                continue;
            }

            $attendu = 0;
            $precedent = null;
            $incoherence = null;
            foreach ($lignes as $l) {
                if ($l['balance_after'] !== null && $l['balance_before'] !== null) {
                    if ($precedent !== null && centimes($l['balance_before']) !== $precedent) {
                        $incoherence = $incoherence ?? ('rupture de chaîne à la transaction ' . $l['id']);
                    }
                    $precedent = centimes($l['balance_after']);
                    $attendu = $precedent;
                    continue;
                }
                // Ligne ancienne : sens deduit du type
                $montant = centimes($l['amount']);
                if ($l['status'] === 'echoue') continue;
                $attendu += in_array($l['type'], ['depot', 'commission', 'jeu_gain'], true) ? $montant : -$montant;
                $precedent = null;
            }

            if ($attendu !== $solde || $incoherence) {
                $resultat['ecarts'][] = [
                    'user_id' => (int) $compte['id'], 'nom' => $compte['fullname'],
                    'solde_reel' => $compte['balance'], 'solde_attendu' => enFrancs($attendu),
                    'ecart' => enFrancs($solde - $attendu),
                    'raison' => $incoherence ?? 'somme des mouvements différente du solde',
                ];
            }
            if (count($resultat['ecarts']) >= $limite) break;
        }

        // References en double (la contrainte UNIQUE les empeche depuis toujours, on verifie quand meme)
        $resultat['doublons'] = $pdo->query(
            "SELECT reference, COUNT(*) AS n FROM transactions_monrevenu WHERE reference IS NOT NULL
             GROUP BY reference HAVING n > 1 LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Ventes validees sans commission creditee, et l'inverse
        $resultat['ventes_sans_commission'] = $pdo->query(
            "SELECT v.id, v.vendeur_id, v.commission_earn, v.created_at FROM vendeur_ventes v
             WHERE v.statut = 'validee'
               AND NOT EXISTS (SELECT 1 FROM transactions_monrevenu t WHERE t.reference = CONCAT('VENTE-', v.id))
             ORDER BY v.id DESC LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);
        $resultat['commissions_sans_vente'] = $pdo->query(
            "SELECT t.id, t.user_id, t.amount, t.reference, t.created_at FROM transactions_monrevenu t
             WHERE t.reference LIKE 'VENTE-%' AND t.reference NOT LIKE 'VENTE-STOCK-%'
               AND NOT EXISTS (SELECT 1 FROM vendeur_ventes v
                   WHERE CONCAT('VENTE-', v.id) = t.reference AND v.statut = 'validee')
             ORDER BY t.id DESC LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Retraits en attente depuis plus de 7 jours
        $resultat['retraits_anciens'] = $pdo->query(
            "SELECT w.id, w.user_id, w.amount, w.created_at FROM withdrawals w
             WHERE w.status IN ('en_attente', 'pending') AND w.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY w.created_at LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Comptes suspendus dont les liens d'affiliation sont refuses alors qu'ils ont des ventes en cours
        $resultat['comptes_bloques_avec_liens'] = $pdo->query(
            "SELECT u.id, u.fullname, u.is_active, u.status,
                    (SELECT COUNT(*) FROM vendeur_ventes v WHERE v.vendeur_id = u.id AND v.statut IN ('en_attente','contacte','colis_recu')) AS ventes_en_cours
             FROM users_monrevenu u
             WHERE (u.is_active = 0 OR u.status <> 'active') AND u.status <> 'deleted'
             HAVING ventes_en_cours > 0 LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $resultat;
    }

    /** Totaux par type et par jour (page Argent). */
    function totauxArgent(PDO $pdo, int $jours = 30): array
    {
        $st = $pdo->prepare(
            "SELECT DATE(created_at) AS jour, type, COUNT(*) AS n, SUM(amount) AS total
             FROM transactions_monrevenu WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY jour, type ORDER BY jour DESC, type"
        );
        $st->execute([$jours]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
