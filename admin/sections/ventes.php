<section id="tab-ventes" class="tab-content hidden" aria-labelledby="t-ventes">
  <div class="carte overflow-hidden">
    <div class="border-b border-line p-4">
      <h2 id="t-ventes" class="carte-titre">Ventes des affiliés</h2>
      <p class="meta mt-1">Contactez le client, puis faites avancer le statut. Au statut Validée, la commission est créditée à l'affilié. 30 ventes les plus récentes.</p>
    </div>
    <div class="max-h-[75vh] overflow-auto">
      <table class="tableau tableau-empile">
        <thead><tr><th scope="col">Produit</th><th scope="col">Client</th><th scope="col">Affilié</th><th scope="col" class="col-montant">Qté</th><th scope="col" class="col-montant">Commission</th><th scope="col">Statut</th><th scope="col">Changer le statut</th></tr></thead>
        <tbody>
          <?php if (empty($ventes)): ?>
            <tr><td colspan="7"><div class="vide"><?= ico('shopping-cart', 'ico-40') ?><p class="vide-titre">Aucune vente pour le moment</p></div></td></tr>
          <?php else: foreach ($ventes as $v): $tel_whatsapp = numeroWhatsapp($v['telephone_client'] ?? ''); ?>
            <tr>
              <td data-label="" class="font-medium"><?= e($v['produit_nom']) ?><span class="block font-mono text-xs font-normal text-text-3">#<?= (int) $v['id'] ?>, <?= e(dateFr($v['created_at'], 'court')) ?></span></td>
              <td data-label="Client">
                <span class="block"><?= e($v['nom_client'] ?: 'Non renseigné') ?></span>
                <span class="chiffres block text-text-2"><?= e($v['telephone_client'] ?: '') ?></span>
                <?php if (!empty($v['adresse_client'])): ?><span class="block max-w-[220px] text-xs text-text-3"><?= e($v['adresse_client']) ?></span><?php endif; ?>
                <?php if ($tel_whatsapp !== ''): ?><a class="lien mt-1 inline-flex items-center gap-1 text-xs" href="https://wa.me/<?= e($tel_whatsapp) ?>" target="_blank" rel="noopener"><?= ico('whatsapp', 'ico-16') ?>Écrire au client</a><?php endif; ?>
              </td>
              <td data-label="Affilié"><?= e($v['vendeur_nom']) ?></td>
              <td data-label="Qté" class="col-montant chiffres"><?= (int) $v['quantite'] ?></td>
              <td data-label="Commission" class="col-montant"><?= montant($v['commission_earn'], false, '', $v['marche']) ?></td>
              <td data-label="Statut"><?= badgeStatut($v['statut'], 'commission') ?></td>
              <td data-label="">
                <form action="" method="POST" class="flex items-center gap-1.5">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                  <input type="hidden" name="vente_id" value="<?= (int) $v['id'] ?>">
                  <label class="sr-only" for="statut-vente-<?= (int) $v['id'] ?>">Statut de la vente <?= (int) $v['id'] ?></label>
                  <select class="champ-saisie h-9 w-40 text-sm lg:h-9" name="statut" id="statut-vente-<?= (int) $v['id'] ?>">
                    <?php foreach ($libelles_statut as $valeur => $libelle): ?>
                      <option value="<?= e($valeur) ?>"<?= $v['statut'] === $valeur ? ' selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" name="action_update_statut_vente" class="btn btn-sm btn-secondaire">Appliquer</button>
                </form>
                <?php if ($v['statut'] === 'colis_recu' && (int) $v['commission_creditee'] === 0): ?>
                  <form action="" method="POST" class="mt-1.5">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="vente_id" value="<?= (int) $v['id'] ?>">
                    <button type="submit" name="action_envoyer_commission" class="btn btn-sm btn-primaire"
                            onclick="return confirm('Envoyer la commission de <?= e(formaterMontant($v['commission_earn'], false, true, $v['marche'])) ?> à <?= e(addslashes($v['vendeur_nom'])) ?> ?');"><?= ico('hand-coins', 'ico-16') ?>Envoyer la commission</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
