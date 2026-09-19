<?php
/**
 * tools/couverture-audit.php : liste chaque ecriture SQL du code et indique si elle emet une ligne de journal.
 * Usage (ligne de commande seulement) : php tools/couverture-audit.php [--markdown]
 *
 * Une ecriture est couverte si un appel de journal (auditCritique, auditInfo, auditCsrf, auditEnvoi,
 * auditInfoLimite, mouvementSolde) se trouve dans la meme fonction ou le meme bloc, au plus 60 lignes
 * apres ou 15 lignes avant. Une ecriture volontairement hors journal porte le commentaire
 * `audit:exclu <raison>` sur sa ligne ou dans les 2 lignes precedentes.
 * Code de sortie 1 si une ecriture n'est ni couverte ni exclue.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$racine = dirname(__DIR__);
$markdown = in_array('--markdown', $argv, true);
$ignores = ['vendor/', 'lib/', 'dev/', 'tools/', 'node_modules/'];

$fichiers = [];
exec('git -C ' . escapeshellarg($racine) . ' ls-files "*.php"', $fichiers);
if (!$fichiers) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) $fichiers[] = substr($f->getPathname(), strlen($racine) + 1);
}

// Les fichiers du journal lui-meme ecrivent dans audit_log / audit_chain_head / transactions_monrevenu par construction
$internes = ['includs/audit.php', 'includs/argent.php'];
$motifEcriture = '/\b(INSERT\s+(?:IGNORE\s+)?INTO|DELETE\s+FROM|REPLACE\s+INTO|UPDATE)\s+`?([a-z_{}$\[\]\']+)`?(\s+SET\b|\s*$|\s*\(|\s+\()?/i';
$motifJournal = '/\b(auditCritique|auditInfo|auditCsrf|auditEnvoi|auditInfoLimite|mouvementSolde)\s*\(/';

$resultats = [];
foreach ($fichiers as $rel) {
    foreach ($ignores as $i) if (str_starts_with($rel, $i)) continue 2;
    if (in_array($rel, $internes, true)) continue;
    $lignes = file($racine . '/' . $rel, FILE_IGNORE_NEW_LINES);
    foreach ($lignes as $n => $ligne) {
        if (!preg_match_all($motifEcriture, $ligne, $m, PREG_SET_ORDER)) continue;
        foreach ($m as $t) {
            $op = strtoupper(strtok($t[1], " \t"));
            $table = strtolower(trim($t[2], "`'"));
            // Faux positifs : FOR UPDATE, ON DUPLICATE KEY UPDATE, mots du texte
            if ($op === 'UPDATE') {
                $avant = strtoupper(substr($ligne, 0, strpos($ligne, $t[0])));
                if (preg_match('/(FOR|KEY)\s*$/', rtrim($avant))) continue;
                if (in_array($table, ['set', 'key', 'de', 'du', 'le', 'la', 'les', 'votre', 'un', 'une'], true)) continue;
                $suite = $ligne . ' ' . ($lignes[$n + 1] ?? '');
                if (!preg_match('/UPDATE\s+`?' . preg_quote($t[2], '/') . '`?\s+SET\b/i', $suite) && !preg_match('/UPDATE\s*$/i', rtrim($ligne))) continue;
            }
            if (!preg_match('/["\']\s*$|["\']?\s*(INSERT|UPDATE|DELETE|REPLACE)/i', $ligne) && !preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)/i', $ligne)) continue;

            $contexte = implode("\n", array_slice($lignes, max(0, $n - 2), 3));
            if (preg_match('/audit:exclu\s+(.+)/', $contexte, $ex)) {
                $resultats[] = [$rel, $n + 1, $op, $table, 'exclue', trim($ex[1])];
                continue;
            }
            $fenetre = implode("\n", array_slice($lignes, max(0, $n - 15), 76));
            $couvert = (bool) preg_match($motifJournal, $fenetre);
            $resultats[] = [$rel, $n + 1, $op, $table, $couvert ? 'couverte' : 'NON COUVERTE', ''];
        }
    }
}

$nonCouvertes = array_filter($resultats, fn($r) => $r[4] === 'NON COUVERTE');
if ($markdown) {
    echo "| Fichier:ligne | Operation | Table | Etat |\n|---|---|---|---|\n";
    foreach ($resultats as $r) echo "| `{$r[0]}:{$r[1]}` | {$r[2]} | {$r[3]} | {$r[4]}" . ($r[5] ? " ({$r[5]})" : '') . " |\n";
} else {
    foreach ($resultats as $r) printf("%-14s %-7s %-26s %s:%d%s\n", $r[4], $r[2], $r[3], $r[0], $r[1], $r[5] ? '  ' . $r[5] : '');
}
printf("\n%d ecriture(s) : %d couverte(s), %d exclue(s), %d non couverte(s)\n", count($resultats),
    count(array_filter($resultats, fn($r) => $r[4] === 'couverte')), count(array_filter($resultats, fn($r) => $r[4] === 'exclue')), count($nonCouvertes));
exit($nonCouvertes ? 1 : 0);
