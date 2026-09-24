<?php
/**
 * includs/commission_admin.php : modification du bareme, de la repartition et des regles de supplement
 * par un administrateur. Validation stricte, historique (qui, quand, avant / apres) et recalcul du prix
 * affiche des produits. Les commandes deja passees gardent leur snapshot : jamais recalculees.
 */
require_once __DIR__ . '/commission.php';
require_once __DIR__ . '/audit.php';

if (!function_exists('commissionValiderSaisie')) {
    /**
     * Valide et normalise la saisie du formulaire. Retourne [bareme, repartition, regles] ou leve
     * InvalidArgumentException avec un message lisible par l'administrateur.
     */
    function commissionValiderSaisie(array $post): array
    {
        $nombre = static function ($v, string $nom): float {
            $v = str_replace([' ', ','], ['', '.'], trim((string) $v));
            if ($v === '' || !is_numeric($v)) throw new InvalidArgumentException("Valeur numérique attendue : $nom.");
            return (float) $v;
        };

        $lignes = [];
        foreach ((array) ($post['tranche_min'] ?? []) as $i => $min) {
            if (!empty($post['tranche_suppr'][$i])) continue;
            $max = trim((string) ($post['tranche_max'][$i] ?? ''));
            $taux = trim((string) ($post['tranche_taux'][$i] ?? ''));
            if (trim((string) $min) === '' && $max === '' && $taux === '') continue; // ligne vide d'ajout
            $lignes[] = [
                'min'  => $nombre($min, 'borne minimale'),
                'max'  => $max === '' ? null : $nombre($max, 'borne maximale'),
                'taux' => $nombre($taux, 'taux'),
            ];
        }
        if (!$lignes) throw new InvalidArgumentException('Le barème doit contenir au moins une tranche.');
        if (count($lignes) > 20) throw new InvalidArgumentException('Vingt tranches au maximum.');
        usort($lignes, fn($a, $b) => $a['min'] <=> $b['min']);

        $n = count($lignes);
        foreach ($lignes as $i => $l) {
            if ($l['taux'] < 0 || $l['taux'] > 100) throw new InvalidArgumentException('Chaque taux doit être compris entre 0 et 100 %.');
            if ($l['min'] < 0) throw new InvalidArgumentException('Une borne ne peut pas être négative.');
            if ($i === 0 && $l['min'] != 0) throw new InvalidArgumentException('La première tranche doit commencer à 0.');
            $derniere = $i === $n - 1;
            if ($derniere && $l['max'] !== null) throw new InvalidArgumentException('La dernière tranche doit rester ouverte (borne maximale vide).');
            if (!$derniere) {
                if ($l['max'] === null) throw new InvalidArgumentException('Seule la dernière tranche peut être ouverte.');
                if ($l['max'] <= $l['min']) throw new InvalidArgumentException('Une borne maximale doit dépasser sa borne minimale.');
                if ($lignes[$i + 1]['min'] != $l['max']) throw new InvalidArgumentException('Les tranches doivent se suivre sans trou ni chevauchement.');
            }
        }

        $affilie = $nombre($post['part_affilie'] ?? '', 'part affilié');
        $plateforme = $nombre($post['part_plateforme'] ?? '', 'part plateforme');
        if ($affilie < 0 || $plateforme < 0 || abs($affilie + $plateforme - 100) > 0.001) {
            throw new InvalidArgumentException('La part affilié et la part plateforme doivent totaliser 100 %.');
        }

        $min = $nombre($post['supplement_min'] ?? '', 'plancher');
        $max = $nombre($post['supplement_max'] ?? '', 'plafond');
        $arrondi = (int) $nombre($post['arrondi'] ?? '', 'arrondi');
        if ($min < 0 || $max < $min) throw new InvalidArgumentException('Le plafond doit être supérieur ou égal au plancher.');
        if (!in_array($arrondi, [1, 50, 100], true)) throw new InvalidArgumentException("L'arrondi doit valoir 1, 50 ou 100.");

        return [
            'bareme'      => $lignes,
            'repartition' => ['part_affilie' => $affilie, 'part_plateforme' => $plateforme],
            'regles'      => ['supplement_min' => $min, 'supplement_max' => $max, 'arrondi' => $arrondi],
        ];
    }
}

if (!function_exists('commissionSauvegarder')) {
    /** Enregistre la nouvelle configuration d'un marche, historise et recalcule les prix affiches. Retourne le nb de produits recalcules. */
    function commissionSauvegarder(PDO $pdo, string $marche, array $saisie, int $adminId): int
    {
        $ancien = commissionConfig($pdo, $marche);
        $avant = [
            'bareme'      => $ancien['brackets'],
            'repartition' => ['part_affilie' => $ancien['part_affilie'], 'part_plateforme' => $ancien['part_plateforme']],
            'regles'      => ['supplement_min' => $ancien['min'], 'supplement_max' => $ancien['max'], 'arrondi' => $ancien['arrondi']],
        ];
        $bareme = array_map(fn($l) => ['min' => $l['min'], 'max' => $l['max'], 'taux' => $l['taux']], $saisie['bareme']);
        $avant['bareme'] = array_map(fn($l) => ['min' => $l['min'], 'max' => $l['max'], 'taux' => $l['taux']], $avant['bareme']);
        $apres = ['bareme' => $bareme, 'repartition' => $saisie['repartition'], 'regles' => $saisie['regles']];

        $pdo->beginTransaction();
        try {
            $historique = $pdo->prepare("INSERT INTO commission_history (admin_id, marche, cible, ancienne_valeur, nouvelle_valeur) VALUES (?, ?, ?, ?, ?)");
            foreach (['bareme', 'repartition', 'regles'] as $cible) {
                if (json_encode($avant[$cible]) !== json_encode($apres[$cible])) {
                    $historique->execute([$adminId, $marche, $cible, json_encode($avant[$cible]), json_encode($apres[$cible])]);
                }
            }
            $pdo->prepare("DELETE FROM commission_brackets WHERE marche = ?")->execute([$marche]);
            $ins = $pdo->prepare("INSERT INTO commission_brackets (marche, ordre, borne_min, borne_max, taux) VALUES (?, ?, ?, ?, ?)");
            foreach ($bareme as $i => $l) $ins->execute([$marche, $i + 1, $l['min'], $l['max'], $l['taux']]);
            $pdo->prepare("UPDATE commission_settings SET part_affilie = ?, part_plateforme = ?, supplement_min = ?, supplement_max = ?, arrondi = ? WHERE marche = ?")
                ->execute([$saisie['repartition']['part_affilie'], $saisie['repartition']['part_plateforme'],
                    $saisie['regles']['supplement_min'], $saisie['regles']['supplement_max'], $saisie['regles']['arrondi'], $marche]);
            $n = commissionRecalculerProduits($pdo, $marche);
            auditCritique($pdo, ['category' => 'admin', 'action' => 'bareme_modification', 'entity_type' => 'marche', 'entity_id' => 0,
                'before' => ['marche' => $marche] + $avant, 'after' => ['marche' => $marche, 'produits_recalcules' => $n] + $apres]);
            $pdo->commit();
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $t;
        }
        return $n;
    }
}
