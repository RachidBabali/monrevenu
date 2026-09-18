<section id="tab-historique" class="tab-content hidden" aria-labelledby="t-historique-admin">
  <div class="carte overflow-hidden">
    <div class="border-b border-line p-4">
      <h2 id="t-historique-admin" class="carte-titre">Historique des transactions</h2>
      <p class="meta mt-1">Toutes les transactions, tous membres confondus (50 plus récentes).</p>
    </div>
    <div class="max-h-[75vh] overflow-auto">
      <table class="tableau tableau-empile">
        <thead><tr><th scope="col">Date</th><th scope="col">Membre</th><th scope="col">Type</th><th scope="col">Référence</th><th scope="col">Description</th><th scope="col">Statut</th><th scope="col" class="col-montant">Montant</th></tr></thead>
        <tbody>
          <?php if (empty($historique)): ?>
            <tr><td colspan="7"><div class="vide"><?= ico('history', 'ico-40') ?><p class="vide-titre">Aucune transaction</p></div></td></tr>
          <?php else: foreach ($historique as $h): ?>
            <tr>
              <td data-label="" class="chiffres whitespace-nowrap text-text-2"><?= e(dateFr($h['created_at'], 'heure')) ?></td>
              <td data-label="Membre" class="font-medium"><?= e($h['utilisateur_nom']) ?></td>
              <td data-label="Type"><span class="pastille pastille-neutre"><?= e($libelles_type_tx[$h['type']] ?? $h['type']) ?></span></td>
              <td data-label="Référence" class="font-mono text-xs text-text-2"><?= e($h['reference'] ?: 'Aucune') ?></td>
              <td data-label="Description" class="max-w-[260px] text-text-2"><?= e(nettoyerPictogrammes($h['description'] ?? '')) ?></td>
              <td data-label="Statut"><?= badgeStatut($h['status'], 'transaction') ?></td>
              <td data-label="Montant" class="col-montant"><?= montant($h['amount']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
