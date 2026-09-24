<?php
/** Onglet Erreurs PHP : les 50 dernieres lignes de app_logs/php_errors.log (includs/journal_erreurs.php). Admin seulement. */
$fichier_log = cheminJournalErreurs();
$lignes_log = [];
if ($fichier_log !== '' && is_readable($fichier_log)) {
    $taille = filesize($fichier_log);
    $lecture = @file_get_contents($fichier_log, false, null, max(0, $taille - 65536)); // 64 Ko de fin suffisent pour 50 lignes
    if ($lecture !== false) $lignes_log = array_slice(array_filter(explode("\n", $lecture), 'strlen'), -50);
}
$lignes_log = array_reverse($lignes_log);
?>
    <section class="flex flex-col gap-3">
      <h2 class="section-titre">Dernières erreurs PHP</h2>
      <p class="meta">50 dernières lignes, la plus récente en haut. Fichier : <span class="chiffres"><?= e($fichier_log !== '' ? $fichier_log : 'aucun dossier de journal accessible') ?></span></p>
      <?php if (!$lignes_log): ?>
        <div class="carte p-4"><p class="meta">Aucune erreur enregistrée.</p></div>
      <?php else: ?>
        <div class="carte overflow-x-auto">
          <pre class="p-4 text-xs leading-relaxed whitespace-pre-wrap break-all"><?php foreach ($lignes_log as $l) echo e($l), "\n"; ?></pre>
        </div>
      <?php endif; ?>
    </section>
