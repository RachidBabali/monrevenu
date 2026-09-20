<?php
/**
 * tools/archiver-journal.php : conservation du journal d'audit (ligne de commande seulement).
 *   php tools/archiver-journal.php --tronquer-ip        efface l'IP complete des lignes de plus de 90 jours
 *   php tools/archiver-journal.php --archiver           ecrit les lignes de plus de 24 mois dans une archive compressee
 *   php tools/archiver-journal.php --archiver --purger  supprime ces lignes APRES verification de l'archive
 * Sans option : etat seulement, rien n'est modifie.
 *
 * L'archive va dans storage/audit/ (hors de la partie publique) et contient, en plus des lignes,
 * le dernier hachage conserve : la verification de chaine repart de cette ancre apres une purge.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$racine = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $racine;
require_once $racine . '/basse_de_donner/monrevenu_bd.php';
require_once $racine . '/includs/audit.php';

// Valeurs par defaut du lot 2, modifiables par le .env (JOURNAL_CONSERVATION_MOIS, JOURNAL_IP_JOURS)
define('CONSERVATION_MOIS', max(0, (int) env('JOURNAL_CONSERVATION_MOIS', 24)));
define('IP_JOURS', max(0, (int) env('JOURNAL_IP_JOURS', 90)));

$options = $argv;
$tronquer = in_array('--tronquer-ip', $options, true);
$archiver = in_array('--archiver', $options, true);
$purger   = in_array('--purger', $options, true);

$total = (int) $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$avecIp = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE ip IS NOT NULL AND occurred_at < DATE_SUB(NOW(), INTERVAL " . IP_JOURS . " DAY)")->fetchColumn();
$anciennes = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE occurred_at < DATE_SUB(NOW(), INTERVAL " . CONSERVATION_MOIS . " MONTH)")->fetchColumn();
fwrite(STDOUT, "Journal : $total ligne(s). IP à effacer : $avecIp. Lignes de plus de " . CONSERVATION_MOIS . " mois : $anciennes.\n");

if ($tronquer) {
    // L'IP complete est hors hachage : l'effacer ne casse pas la chaine (le prefixe /24 reste)
    $st = $pdo->prepare("UPDATE audit_log SET ip = NULL, user_agent = NULL
                         WHERE ip IS NOT NULL AND occurred_at < DATE_SUB(NOW(), INTERVAL " . IP_JOURS . " DAY)");
    $st->execute();
    $n = $st->rowCount();
    auditInfo($pdo, ['category' => 'systeme', 'action' => 'journal_ip_effacee', 'actor_id' => null, 'actor_role' => null,
        'meta' => ['lignes' => $n, 'age_jours' => IP_JOURS]]);
    fwrite(STDOUT, "IP et user-agent effacés sur $n ligne(s).\n");
    $r = auditVerifierChaine($pdo);
    fwrite(STDOUT, 'Chaîne après effacement : ' . ($r['ok'] ? 'valide' : 'ROMPUE à la ligne ' . $r['rupture']) . "\n");
    if (!$r['ok']) exit(1);
}

if ($archiver) {
    if ($anciennes === 0) { fwrite(STDOUT, "Rien à archiver.\n"); exit(0); }
    $dossier = $racine . '/storage/audit';
    if (!is_dir($dossier)) mkdir($dossier, 0755, true);
    $fichier = $dossier . '/journal-' . date('Y-m-d-His') . '.jsonl.gz';
    $flux = gzopen($fichier, 'wb9');
    if (!$flux) { fwrite(STDERR, "Archive impossible à créer.\n"); exit(1); }

    $dernierId = 0; $dernierHash = ''; $ecrites = 0;
    $st = $pdo->prepare("SELECT * FROM audit_log WHERE occurred_at < DATE_SUB(NOW(), INTERVAL " . CONSERVATION_MOIS . " MONTH) AND id > ? ORDER BY id LIMIT 2000");
    do {
        $st->execute([$dernierId]);
        $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lignes as $l) {
            gzwrite($flux, json_encode($l, JSON_UNESCAPED_UNICODE) . "\n");
            $dernierId = (int) $l['id'];
            $dernierHash = $l['row_hash'];
            $ecrites++;
        }
    } while (count($lignes) === 2000);
    gzclose($flux);

    // Verification : on relit l'archive et on compte les lignes
    $relues = 0;
    $flux = gzopen($fichier, 'rb');
    while (!gzeof($flux)) { if (trim((string) gzgets($flux)) !== '') $relues++; }
    gzclose($flux);
    $manifeste = ['fichier' => basename($fichier), 'date' => date('c'), 'lignes' => $ecrites, 'lignes_relues' => $relues,
        'dernier_id' => $dernierId, 'dernier_hash' => $dernierHash, 'sha256' => hash_file('sha256', $fichier)];
    file_put_contents($fichier . '.manifeste.json', json_encode($manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fwrite(STDOUT, "Archive : $fichier ($ecrites ligne(s), $relues relue(s)).\n");

    if ($relues !== $ecrites) { fwrite(STDERR, "Archive incomplète : rien n'est supprimé.\n"); exit(1); }
    auditInfo($pdo, ['category' => 'systeme', 'action' => 'journal_archive', 'actor_id' => null, 'actor_role' => null,
        'meta' => ['fichier' => basename($fichier), 'lignes' => $ecrites, 'dernier_id' => $dernierId]]);

    if ($purger) {
        $st = $pdo->prepare("DELETE FROM audit_log WHERE id <= ?");
        $st->execute([$dernierId]);
        $supprimees = $st->rowCount();
        auditInfo($pdo, ['category' => 'systeme', 'action' => 'journal_purge', 'actor_id' => null, 'actor_role' => null,
            'meta' => ['lignes' => $supprimees, 'jusqu_a_id' => $dernierId, 'archive' => basename($fichier)]]);
        fwrite(STDOUT, "$supprimees ligne(s) supprimée(s) de la base.\n");
        $r = auditVerifierChaine($pdo, $dernierHash);
        fwrite(STDOUT, 'Chaîne depuis l\'ancre de l\'archive : ' . ($r['ok'] ? 'valide' : 'ROMPUE à la ligne ' . $r['rupture']) . "\n");
        if (!$r['ok']) exit(1);
    } else {
        fwrite(STDOUT, "Aucune suppression (ajouter --purger après vérification de l'archive).\n");
    }
}
