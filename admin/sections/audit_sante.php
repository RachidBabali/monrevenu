<?php
/** Onglet Santé : application, en-têtes servis, services externes, journal d'erreurs, tâche planifiée. */
$application = santeApplication();
$services = santeServices($pdo);
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
$entetes = santeEntetes($url);
$controlesEntetes = $entetes['erreur'] ? [santeControle('entetes', 'En-têtes de sécurité', 'site injoignable depuis le serveur', 'inconnu')]
    : santeControlesEntetes($entetes['entetes']);
$erreurs = santeDernieresErreurs(15);
$capture = $pdo->query("SELECT taken_at, kind, status FROM health_snapshots ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$commandeCron = '/usr/bin/php ' . dirname(__DIR__, 2) . '/admin/cron/snapshot.php';

?>
    <?php tableauControles($application, 'Application'); ?>
    <?php tableauControles($controlesEntetes, 'En-têtes de sécurité réellement servis'); ?>
    <?php tableauControles($services, 'Services externes'); ?>

    <section class="flex flex-col gap-3">
      <h2 class="section-titre">Tâche planifiée</h2>
      <div class="carte flex flex-col gap-3 p-4">
        <p class="text-sm text-text-2">Dernière capture : <strong><?= $capture ? e(dateFr($capture['taken_at'], 'long')) . ' (' . e($capture['status']) . ')' : 'jamais' ?></strong>.
          Sans tâche planifiée, cette page reste utilisable : elle calcule tout à chaque ouverture.</p>
        <p class="text-sm text-text-2">À créer dans hPanel, Advanced, Cron Jobs, une fois par jour :</p>
        <p class="lien-copie"><code class="chiffres break-all"><?= e($commandeCron) ?></code>
          <button type="button" class="btn btn-sm btn-discret" data-copier="<?= e($commandeCron) ?>"><?= ico('copy', 'ico-16') ?>Copier</button></p>
      </div>
    </section>

    <section class="flex flex-col gap-3">
      <h2 class="section-titre">Dernières lignes du journal d'erreurs PHP</h2>
      <div class="carte overflow-hidden">
        <?php if (!$erreurs): ?>
          <p class="p-4 text-sm text-text-3">Aucune ligne lisible (journal vide, absent ou non configuré).</p>
        <?php else: ?>
          <ul class="divide-y divide-line">
            <?php foreach ($erreurs as $l): ?>
              <li class="px-4 py-2 text-xs text-text-2 break-all"><?= e($l) ?></li>
            <?php endforeach; ?>
          </ul>
          <p class="border-t border-line px-4 py-2 text-xs text-text-3">Adresses, chemins et numéros sont masqués.</p>
        <?php endif; ?>
      </div>
    </section>
