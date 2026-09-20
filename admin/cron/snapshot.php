<?php
/**
 * admin/cron/snapshot.php : capture periodique de l'etat du service, en ligne de commande seulement.
 * A declarer dans hPanel, Advanced, Cron Jobs, une fois par jour :
 *   /usr/bin/php /home/<utilisateur>/domains/monrevenu.xyz/public_html/admin/cron/snapshot.php
 *
 * Enregistre une ligne dans health_snapshots, verifie la chaine du journal, et ecrit une ancre
 * (dernier identifiant et dernier hachage) dans storage/audit/ancre-journal.json : une suppression
 * de lignes recentes devient visible meme si la table audit_chain_head est modifiee.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $racine;
require_once $racine . '/basse_de_donner/monrevenu_bd.php';
require_once $racine . '/includs/audit.php';
require_once $racine . '/includs/sante.php';
require_once $racine . '/includs/rapprochement.php';
require_once $racine . '/includs/affiliation_helpers.php';

$debut = microtime(true);
$controles = array_merge(santeApplication(), santeBase($pdo), santeIntegrite($pdo), santeServices($pdo));
$stockage = santeStockage($pdo);
$controles = array_merge($controles, $stockage['controles']);
$chaine = auditVerifierChaine($pdo);
if (!$chaine['ok']) {
    $controles[] = santeControle('journal_chaine', 'Chaîne du journal', 'rupture à la ligne ' . $chaine['rupture'], 'critique', $chaine['raison']);
} else {
    $controles[] = santeControle('journal_chaine', 'Chaîne du journal', $chaine['lignes'] . ' lignes conformes', 'ok');
}
$rapprochement = rapprochementSoldes($pdo, 100);
$anomaliesArgent = count($rapprochement['ecarts']) + count($rapprochement['negatifs']) + count($rapprochement['doublons']);
$controles[] = santeControle('argent_rapprochement', 'Rapprochement des soldes',
    $anomaliesArgent === 0 ? 'aucun écart' : $anomaliesArgent . ' anomalie(s)', $anomaliesArgent === 0 ? 'ok' : 'critique');

$niveau = santeNiveauGlobal($controles);
$charge = [
    'controles' => array_map(fn($c) => ['cle' => $c['cle'], 'valeur' => (string) $c['valeur'], 'niveau' => $c['niveau']], $controles),
    'duree_ms' => (int) round((microtime(true) - $debut) * 1000),
];
$pdo->prepare("INSERT INTO health_snapshots (kind, status, payload_json) VALUES ('quotidien', ?, ?)")
    ->execute([$niveau, json_encode($charge, JSON_UNESCAPED_UNICODE)]);

// Ancre du journal : hors base, pour detecter une suppression de lignes recentes
$tete = $pdo->query("SELECT last_id, last_hash FROM audit_chain_head WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$dossier = $racine . '/storage/audit';
if (is_dir($dossier) || @mkdir($dossier, 0755, true)) {
    $ancres = [];
    $fichier = $dossier . '/ancre-journal.json';
    if (is_file($fichier)) $ancres = json_decode((string) file_get_contents($fichier), true) ?: [];
    $ancres[] = ['date' => date('c'), 'last_id' => (int) $tete['last_id'], 'last_hash' => $tete['last_hash']];
    $ancres = array_slice($ancres, -90); // 90 dernieres captures
    file_put_contents($fichier, json_encode($ancres, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

auditInfo($pdo, ['category' => 'systeme', 'action' => 'tache_planifiee', 'result' => $niveau === 'critique' ? 'echec' : 'ok',
    'actor_id' => null, 'actor_role' => null,
    'meta' => ['niveau' => $niveau, 'duree_ms' => $charge['duree_ms'], 'chaine_ok' => $chaine['ok'], 'lignes_journal' => $chaine['lignes']]]);

fwrite(STDOUT, 'Capture enregistrée : ' . $niveau . ' en ' . $charge['duree_ms'] . " ms\n");
foreach ($controles as $c) {
    if (in_array($c['niveau'], ['critique', 'attention'], true)) {
        fwrite(STDOUT, '  ' . strtoupper($c['niveau']) . ' : ' . $c['libelle'] . ' = ' . $c['valeur'] . "\n");
    }
}
exit($niveau === 'critique' ? 1 : 0);
