<?php
/**
 * tools/corriger-entites-html.php : remet en texte clair les valeurs enregistrees echappees.
 *
 * Avant le lot 3, l'administration appliquait htmlspecialchars() avant l'INSERT : les lignes
 * creees a cette epoque contiennent &amp;, &#039; ou &quot; et s'affichent tels quels, puisque
 * l'affichage echappe de nouveau. Ce script les decode une bonne fois.
 *
 * Usage (en ligne de commande seulement, apres un export de la base) :
 *   php tools/corriger-entites-html.php             simulation : liste, ne modifie rien
 *   php tools/corriger-entites-html.php --appliquer corrige et journalise avant et apres
 *   Options : --limite=200 (nombre de lignes par table), --table=vendeur_produits
 *
 * Chaque correction ecrit une ligne de journal (categorie systeme, action entites_correction)
 * avec la valeur avant et la valeur apres.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$racine = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $racine;
require_once $racine . '/basse_de_donner/monrevenu_bd.php';
require_once $racine . '/includs/audit.php';

$appliquer = in_array('--appliquer', $argv, true);
$limite = 500;
$tableChoisie = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--limite=')) $limite = max(1, (int) substr($a, 9));
    if (str_starts_with($a, '--table=')) $tableChoisie = substr($a, 8);
}

// Colonnes de texte libre ecrites par l'administration ou par un commercant.
$cibles = [
    ['vendeur_produits', 'id', ['nom_produit', 'description']],
    ['produits_stock', 'id', ['nom_produit']],
    ['transactions_monrevenu', 'id', ['description']],
    ['commercants_profils', 'user_id', ['nom_boutique', 'ville', 'description']],
];

/** Decode tant que la valeur change, au plus trois fois (cas des doubles echappements). */
function decoderEntites(string $v): string
{
    for ($i = 0; $i < 3; $i++) {
        $d = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($d === $v) break;
        $v = $d;
    }
    return $v;
}

$motif = '/&(?:amp|lt|gt|quot|apos|nbsp|#0*3[49]|#x0*2[27]);/i';
$total = 0;
$corrigees = 0;

echo ($appliquer ? "MODE REEL : les lignes seront corrigees et journalisees.\n" : "SIMULATION : aucune ecriture. Ajoutez --appliquer pour corriger.\n");

foreach ($cibles as [$table, $cle, $colonnes]) {
    if ($tableChoisie !== null && $table !== $tableChoisie) continue;
    try {
        $conditions = implode(' OR ', array_map(fn($c) => "$c REGEXP '&(amp|lt|gt|quot|apos|nbsp|#0*3[49]|#x0*2[27]);'", $colonnes));
        $st = $pdo->query("SELECT $cle, " . implode(', ', $colonnes) . " FROM $table WHERE $conditions LIMIT " . (int) $limite);
        $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        echo "  $table : table absente ou illisible, ignoree (" . get_class($t) . ")\n";
        continue;
    }
    echo "\n== $table : " . count($lignes) . " ligne(s) a corriger\n";
    foreach ($lignes as $l) {
        $avant = [];
        $apres = [];
        foreach ($colonnes as $c) {
            $v = (string) ($l[$c] ?? '');
            if ($v === '' || !preg_match($motif, $v)) continue;
            $d = decoderEntites($v);
            if ($d === $v) continue;
            $avant[$c] = $v;
            $apres[$c] = $d;
        }
        if (!$apres) continue;
        $total++;
        echo "  $cle=" . $l[$cle] . "\n";
        foreach ($apres as $c => $d) {
            echo "    $c : " . mb_substr($avant[$c], 0, 70) . "\n";
            echo "    " . str_repeat(' ', mb_strlen($c)) . " > " . mb_substr($d, 0, 70) . "\n";
        }
        if (!$appliquer) continue;

        $pdo->beginTransaction();
        try {
            $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($apres)));
            $st = $pdo->prepare("UPDATE $table SET $set WHERE $cle = ?");
            $st->execute([...array_values($apres), $l[$cle]]);
            auditCritique($pdo, [
                'category' => 'systeme', 'action' => 'entites_correction', 'entity_type' => $table,
                'entity_id' => (string) $l[$cle], 'before' => $avant, 'after' => $apres,
                'actor_id' => null, 'actor_role' => 'outil',
            ]);
            $pdo->commit();
            $corrigees++;
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "    ECHEC : " . get_class($t) . " (ligne laissee telle quelle)\n";
        }
    }
}

echo "\n$total ligne(s) concernee(s), $corrigees corrigee(s).\n";
echo $appliquer ? "Verifiez quelques fiches dans l'interface, puis l'onglet Journal (action entites_correction).\n"
                : "Relancez avec --appliquer apres un export de la base.\n";
