<?php
/** Onglet Vue d'ensemble : etat global, alertes par gravite, derniere capture de sante. */
$alertes = [];
$controles = array_merge(santeApplication(), santeBase($pdo), santeIntegrite($pdo), santeServices($pdo));

// Rapprochement des soldes : resume mis en cache 10 minutes (le detail est dans l'onglet Argent)
$resumeArgent = santeCache('rapprochement', SANTE_CACHE_SECONDES, static function () use ($pdo) {
    $r = rapprochementSoldes($pdo, 100);
    return ['ecarts' => count($r['ecarts']), 'negatifs' => count($r['negatifs']),
        'ventes_sans_commission' => count($r['ventes_sans_commission']), 'retraits_anciens' => count($r['retraits_anciens'])];
});
$anomaliesArgent = array_sum($resumeArgent);
$controles[] = santeControle('argent', 'Rapprochement des soldes',
    $anomaliesArgent === 0 ? 'aucun écart' : $anomaliesArgent . ' anomalie(s)', $anomaliesArgent === 0 ? 'ok' : 'critique',
    $anomaliesArgent === 0 ? '' : 'Détail dans l\'onglet Argent : ' . $resumeArgent['ecarts'] . ' écart(s) de solde, '
        . $resumeArgent['negatifs'] . ' solde(s) négatif(s), ' . $resumeArgent['ventes_sans_commission'] . ' vente(s) sans commission, '
        . $resumeArgent['retraits_anciens'] . ' retrait(s) en attente depuis plus de 7 jours.');
foreach ($controles as $c) {
    if (in_array($c['niveau'], ['critique', 'attention'], true)) $alertes[] = $c;
}
usort($alertes, fn($a, $b) => ($b['niveau'] === 'critique' ? 1 : 0) <=> ($a['niveau'] === 'critique' ? 1 : 0));

$tete = $pdo->query("SELECT last_id, last_hash FROM audit_chain_head WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$derniere = $pdo->query("SELECT occurred_at FROM audit_log ORDER BY id DESC LIMIT 1")->fetchColumn();
$capture = $pdo->query("SELECT taken_at, status FROM health_snapshots ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$parJour = $pdo->query(
    "SELECT DATE(occurred_at) AS jour, COUNT(*) AS n,
            SUM(result <> 'ok') AS anomalies
     FROM audit_log WHERE occurred_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY jour ORDER BY jour DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
    <dl class="indicateurs">
      <div class="indicateur">
        <dt class="indicateur-libelle">Lignes de journal</dt>
        <dd class="indicateur-valeur"><?= number_format((int) ($tete['last_id'] ?? 0), 0, ',', ' ') ?></dd>
        <dd class="indicateur-note">Dernière : <?= $derniere ? e(dateFr($derniere, 'court')) : 'aucune' ?></dd>
      </div>
      <div class="indicateur">
        <dt class="indicateur-libelle">Alertes</dt>
        <dd class="indicateur-valeur"><?= count($alertes) ?></dd>
        <dd class="indicateur-note"><?= count(array_filter($alertes, fn($a) => $a['niveau'] === 'critique')) ?> critique(s)</dd>
      </div>
      <div class="indicateur">
        <dt class="indicateur-libelle">Dernière capture de santé</dt>
        <dd class="indicateur-valeur"><?= $capture ? e(dateFr($capture['taken_at'], 'court')) : 'jamais' ?></dd>
        <dd class="indicateur-note"><?= $capture ? e($capture['status']) : 'Tâche planifiée à créer (voir Santé)' ?></dd>
      </div>
      <div class="indicateur">
        <dt class="indicateur-libelle">Événements sur 7 jours</dt>
        <dd class="indicateur-valeur"><?= number_format(array_sum(array_column($parJour, 'n')), 0, ',', ' ') ?></dd>
        <dd class="indicateur-note"><?= number_format(array_sum(array_column($parJour, 'anomalies')), 0, ',', ' ') ?> refus ou échecs</dd>
      </div>
    </dl>

    <section class="flex flex-col gap-3" aria-labelledby="t-alertes">
      <h2 id="t-alertes" class="section-titre">Alertes</h2>
      <div class="carte overflow-hidden">
        <?php if (!$alertes): ?>
          <div class="vide">
            <?= ico('circle-check', 'ico-40') ?>
            <p class="vide-titre">Aucune alerte</p>
            <p class="vide-texte">Application, base de données, intégrité et services externes : tous les contrôles passent.</p>
          </div>
        <?php else: ?>
          <table class="tableau tableau-empile">
            <thead><tr><th scope="col">Gravité</th><th scope="col">Contrôle</th><th scope="col">Valeur</th><th scope="col">À faire</th></tr></thead>
            <tbody>
            <?php foreach ($alertes as $a): ?>
              <tr>
                <td data-label="Gravité"><span class="pastille pastille-<?= $a['niveau'] === 'critique' ? 'danger' : 'attente' ?>"><?= $a['niveau'] === 'critique' ? 'Critique' : 'À surveiller' ?></span></td>
                <td data-label="Contrôle" class="font-medium"><?= e($a['libelle']) ?></td>
                <td data-label="Valeur" class="text-text-2"><?= e((string) $a['valeur']) ?></td>
                <td data-label="À faire" class="text-text-2"><?= e($a['note']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

    <section class="flex flex-col gap-3" aria-labelledby="t-activite-journal">
      <h2 id="t-activite-journal" class="section-titre">Activité du journal (7 jours)</h2>
      <div class="carte overflow-hidden">
        <table class="tableau tableau-empile">
          <thead><tr><th scope="col">Jour</th><th scope="col" class="col-montant">Événements</th><th scope="col" class="col-montant">Refus ou échecs</th></tr></thead>
          <tbody>
          <?php foreach ($parJour as $j): ?>
            <tr>
              <td data-label="Jour" class="chiffres"><?= e(dateFr($j['jour'], 'court')) ?></td>
              <td data-label="Événements" class="col-montant chiffres"><?= number_format((int) $j['n'], 0, ',', ' ') ?></td>
              <td data-label="Refus ou échecs" class="col-montant chiffres"><?= number_format((int) $j['anomalies'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$parJour): ?><tr><td colspan="3" class="text-text-3">Aucun événement sur la période.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
