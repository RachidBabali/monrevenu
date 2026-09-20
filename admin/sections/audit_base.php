<?php
/** Onglet Base de données : tables et tailles (SHOW TABLE STATUS), contrôles d'intégrité. */
$tables = santeTables($pdo);
$total = array_sum(array_column($tables, 'octets'));
$integrite = santeIntegrite($pdo);
$base = santeBase($pdo);
?>
    <?php tableauControles($base, 'Base de données'); ?>
    <?php tableauControles($integrite, 'Contrôles d\'intégrité'); ?>

    <section class="flex flex-col gap-3">
      <h2 class="section-titre">Tables</h2>
      <p class="meta">Total <?= e(santeOctets($total)) ?> sur 3 Go. Valeurs lues par SHOW TABLE STATUS, mises en cache 10 minutes.</p>
      <div class="carte overflow-hidden">
        <table class="tableau tableau-empile">
          <thead><tr><th scope="col">Table</th><th scope="col" class="col-montant">Lignes</th><th scope="col" class="col-montant">Taille</th><th scope="col">Moteur</th></tr></thead>
          <tbody>
          <?php foreach ($tables as $t): ?>
            <tr>
              <td data-label="Table" class="chiffres"><?= e($t['nom']) ?></td>
              <td data-label="Lignes" class="col-montant chiffres"><?= number_format($t['lignes'], 0, ',', ' ') ?></td>
              <td data-label="Taille" class="col-montant chiffres"><?= e(santeOctets($t['octets'])) ?></td>
              <td data-label="Moteur" class="text-text-2"><?= e($t['moteur']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$tables): ?><tr><td colspan="4" class="text-text-3">Liste indisponible.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
