<?php
/** Onglet Stockage : état de R2, images manquantes, objets non référencés. */
$stockage = santeStockage($pdo);
?>
    <?php tableauControles($stockage['controles'], 'Stockage des images (Cloudflare R2)'); ?>
    <p class="meta">Liste bornée à 10 000 objets et mise en cache 10 minutes. Aucune clé d'accès n'apparaît ici.</p>

    <?php if ($stockage['manquantes']): ?>
      <section class="flex flex-col gap-3">
        <h2 class="section-titre">Images référencées en base mais absentes du stockage</h2>
        <div class="carte overflow-hidden">
          <table class="tableau tableau-empile">
            <thead><tr><th scope="col">Produit</th><th scope="col">Clé attendue</th></tr></thead>
            <tbody>
            <?php foreach ($stockage['manquantes'] as $m): ?>
              <tr>
                <td data-label="Produit" class="chiffres">#<?= (int) ($m['produit_id'] ?? $m['produit_stock_id'] ?? 0) ?><?= isset($m['produit_stock_id']) ? ' (stock)' : '' ?></td>
                <td data-label="Clé attendue" class="chiffres break-all"><?= e($m['cle']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($stockage['orphelins']): ?>
      <section class="flex flex-col gap-3">
        <h2 class="section-titre">Objets non référencés (échantillon)</h2>
        <p class="meta">Anciennes images remplacées ou envois interrompus. Rien n'est supprimé automatiquement.</p>
        <div class="carte overflow-hidden">
          <table class="tableau tableau-empile">
            <thead><tr><th scope="col">Clé</th><th scope="col" class="col-montant">Taille</th><th scope="col">Dernière modification</th></tr></thead>
            <tbody>
            <?php foreach ($stockage['orphelins'] as $o): ?>
              <tr>
                <td data-label="Clé" class="chiffres break-all"><?= e($o['cle']) ?></td>
                <td data-label="Taille" class="col-montant chiffres"><?= e(santeOctets($o['octets'])) ?></td>
                <td data-label="Dernière modification" class="text-text-2"><?= e(substr($o['date'], 0, 19)) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
