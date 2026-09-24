<section id="tab-produits" class="tab-content grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]" aria-label="Produits">
  <form action="" method="POST" enctype="multipart/form-data" class="carte self-start">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <h2 class="carte-entete carte-titre">Ajouter un produit au catalogue</h2>
    <div class="flex flex-col gap-4 p-4">
      <div class="champ">
        <label class="champ-label" for="produit-nom">Nom du produit</label>
        <input class="champ-saisie" type="text" id="produit-nom" name="nom" required>
      </div>
      <div class="champ">
        <label class="champ-label" for="produit-image">Photo</label>
        <input class="text-sm text-text-2 file:mr-3 file:rounded file:border file:border-line-strong file:bg-surface file:px-3 file:py-2 file:text-sm file:font-medium file:text-text" type="file" id="produit-image" name="image_produit" accept=".jpg,.jpeg,.png,.webp">
        <p class="champ-aide">JPG, PNG ou WEBP, 2 Mo maximum. Photo carrée conseillée.</p>
      </div>
      <div class="champ">
        <label class="champ-label" for="produit-description">Description</label>
        <textarea class="champ-saisie" id="produit-description" name="description" rows="3"></textarea>
      </div>
      <div class="champ">
        <label class="champ-label" for="produit-marche">Marché</label>
        <select class="champ-saisie" id="produit-marche" name="marche" required>
          <?php foreach (marches() as $codeMarche => $configMarche): ?>
            <option value="<?= e($codeMarche) ?>"><?= e($configMarche['nom']) ?> (<?= e($configMarche['devise_libelle']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <p class="champ-aide">Le produit n'apparaît que dans le catalogue des affiliés de ce marché, et son prix est dans la devise du marché.</p>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="champ">
          <label class="champ-label" for="produit-prix">Prix</label>
          <input class="champ-saisie chiffres" type="number" step="0.01" id="produit-prix" name="prix" required inputmode="decimal">
        </div>
        <div class="champ">
          <label class="champ-label" for="produit-commission">Commission (%)</label>
          <input class="champ-saisie chiffres" type="number" id="produit-commission" name="commission_pourcentage" value="10" min="0" max="100">
        </div>
      </div>
      <p class="champ-aide">La commission versée à l'affilié suit la règle du marché choisi. Le pourcentage est enregistré mais n'intervient pas dans le calcul.</p>
      <button type="submit" name="action_produit" class="btn btn-primaire"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Publier le produit</span></button>
    </div>
  </form>

  <div class="carte min-w-0 overflow-hidden">
    <div class="carte-entete">
      <h2 class="carte-titre">Produits récents</h2>
      <span class="meta chiffres"><?= (int) $nb_produits ?> affiché<?= $nb_produits > 1 ? 's' : '' ?></span>
    </div>
    <div class="max-h-[70vh] overflow-auto">
      <table class="tableau tableau-empile">
        <thead><tr><th scope="col">Produit</th><th scope="col" class="col-montant">Prix</th><th scope="col" class="col-montant">Comm.</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php if (empty($produits)): ?>
            <tr><td colspan="4"><div class="vide"><?= ico('store', 'ico-40') ?><p class="vide-titre">Aucun produit publié</p><p class="vide-texte">Ajoutez le premier produit avec le formulaire.</p></div></td></tr>
          <?php else: foreach ($produits as $p): ?>
            <tr>
              <td data-label="">
                <span class="flex items-center gap-3">
                  <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded bg-surface-2">
                    <?php if (!empty($p['image'])): ?><img src="<?= e($p['image']) ?>" alt="" width="40" height="40" loading="lazy" class="h-full w-full object-contain"><?php else: ?><?= ico('image', 'text-text-3') ?><?php endif; ?>
                  </span>
                  <span class="min-w-0"><span class="block truncate font-medium"><?= e($p['nom_produit']) ?></span><span class="block font-mono text-xs text-text-3">#<?= (int) $p['id'] ?></span></span>
                </span>
              </td>
              <td data-label="Prix" class="col-montant"><?= montant($p['prix_vente'], false, '', $p['marche']) ?></td>
              <td data-label="Commission %" class="col-montant chiffres"><?= e((string) $p['commission_pct']) ?> %</td>
              <td data-label="">
                <span class="flex justify-end gap-1.5">
                  <button type="button" class="btn btn-sm btn-icone btn-secondaire" title="Modifier"
                          onclick="ouvrirEditionProduit(<?= (int) $p['id'] ?>, '<?= e(addslashes($p['nom_produit'])) ?>', '<?= e(addslashes((string) $p['description'])) ?>', <?= (float) $p['prix_vente'] ?>, <?= (int) $p['commission_pct'] ?>, '<?= e(deviseLibelle($p['marche'])) ?>')"
                          aria-label="Modifier <?= e($p['nom_produit']) ?>"><?= ico('pencil', 'ico-16') ?></button>
                  <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="produit_id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" name="action_delete_produit" class="btn btn-sm btn-icone btn-discret text-danger hover:bg-danger-soft" title="Supprimer"
                            onclick="return confirm('Supprimer le produit <?= e(addslashes($p['nom_produit'])) ?> ?');"
                            aria-label="Supprimer <?= e($p['nom_produit']) ?>"><?= ico('trash-2', 'ico-16') ?></button>
                  </form>
                </span>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
