<section id="tab-retraits" class="tab-content hidden" aria-labelledby="t-retraits-admin">
  <div class="carte overflow-hidden">
    <div class="border-b border-line p-4">
      <h2 id="t-retraits-admin" class="carte-titre">Demandes de retrait</h2>
      <p class="meta mt-1">Envoyez d'abord le paiement sur le numéro indiqué, puis confirmez. Un refus recrédite le montant au membre. 30 demandes les plus récentes.</p>
    </div>
    <div class="max-h-[75vh] overflow-auto">
      <table class="tableau tableau-empile">
        <thead><tr><th scope="col">Demande</th><th scope="col">Membre</th><th scope="col">Paiement vers</th><th scope="col" class="col-montant">Montant</th><th scope="col">Statut</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody id="live-withdrawal-table">
          <?php if (empty($retraits)): ?>
            <tr><td colspan="6"><div class="vide"><?= ico('banknote', 'ico-40') ?><p class="vide-titre">Aucune demande de retrait</p></div></td></tr>
          <?php else: foreach ($retraits as $r):
            $statut_r = $r['status'];
            $en_attente_r = in_array($statut_r, ['en_attente', 'pending'], true);
            $statut_norme = ['pending' => 'en_attente', 'approved' => 'valide', 'rejected' => 'rejete'][$statut_r] ?? $statut_r;
          ?>
            <tr>
              <td data-label=""><span class="font-mono font-medium">RETRAIT-<?= (int) $r['id'] ?></span><span class="chiffres block text-xs text-text-3"><?= e(dateFr($r['created_at'], 'heure')) ?></span></td>
              <td data-label="Membre"><?= e($r['utilisateur_nom']) ?></td>
              <td data-label="Paiement vers"><span class="block"><?= e($r['method'] ?: 'Non précisé') ?></span><?php if (!empty($r['note'])): ?><span class="block font-mono text-xs text-text-2"><?= e(nettoyerPictogrammes($r['note'])) ?></span><?php endif; ?></td>
              <td data-label="Montant" class="col-montant"><?= montant($r['amount']) ?></td>
              <td data-label="Statut"><?= badgeStatut($statut_norme, 'retrait') ?></td>
              <td data-label="">
                <?php if ($en_attente_r): ?>
                  <span class="flex justify-end gap-1.5">
                    <form action="" method="POST">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                      <input type="hidden" name="withdrawal_id" value="<?= (int) $r['id'] ?>">
                      <button type="submit" name="action_valider_retrait" class="btn btn-sm btn-primaire"
                              onclick="return confirm('Confirmez-vous avoir envoyé <?= e(formaterMontant($r['amount'])) ?> à <?= e(addslashes($r['utilisateur_nom'])) ?> via <?= e(addslashes((string) $r['method'])) ?> ?');">Marquer payé</button>
                    </form>
                    <form action="" method="POST">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                      <input type="hidden" name="withdrawal_id" value="<?= (int) $r['id'] ?>">
                      <button type="submit" name="action_refuser_retrait" class="btn btn-sm btn-discret text-danger hover:bg-danger-soft"
                              onclick="return confirm('Refuser cette demande ? Le montant sera recrédité au membre.');">Refuser</button>
                    </form>
                  </span>
                <?php else: ?>
                  <span class="meta">Traitée</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
