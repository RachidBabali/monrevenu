<section id="tab-stock-revendeurs" class="tab-content hidden space-y-6" aria-label="Stock revendeurs">
  <?php $img_stock = static fn(?string $i): string => (string) $i; ?>

  <div class="carte overflow-hidden">
    <div class="carte-entete">
      <h2 class="carte-titre">Commissions de ventes à envoyer</h2>
      <span class="pastille <?= !empty($ventes_stock_en_attente) ? 'pastille-attente' : 'pastille-neutre' ?>"><?= count($ventes_stock_en_attente) ?> en attente</span>
    </div>
    <div class="max-h-[60vh] overflow-auto">
      <table class="tableau tableau-empile">
        <thead><tr><th scope="col">Référence</th><th scope="col">Produit</th><th scope="col">Revendeur</th><th scope="col" class="col-montant">Vente</th><th scope="col" class="col-montant">Commission</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
        <tbody>
          <?php if (empty($ventes_stock_en_attente)): ?>
            <tr><td colspan="6"><p class="px-4 py-6 text-sm text-text-2">Aucune commission en attente.</p></td></tr>
          <?php else: foreach ($ventes_stock_en_attente as $vs): ?>
            <tr>
              <td data-label="" class="font-mono text-sm"><?= e($vs['reference']) ?></td>
              <td data-label="Produit"><?= e($vs['nom_produit']) ?></td>
              <td data-label="Revendeur"><?= e($vs['fullname']) ?></td>
              <td data-label="Vente" class="col-montant"><?= montant($vs['montant_total'], false, 'font-normal') ?></td>
              <td data-label="Commission" class="col-montant"><?= montant($vs['commission_montant']) ?></td>
              <td data-label="">
                <form action="" method="POST" class="flex justify-end">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                  <input type="hidden" name="vente_stock_id" value="<?= (int) $vs['id'] ?>">
                  <button type="submit" name="action_envoyer_commission_stock" class="btn btn-sm btn-primaire"
                          onclick="return confirm('Envoyer <?= e(formaterMontant($vs['commission_montant'])) ?> à <?= e(addslashes($vs['fullname'])) ?> ?');"><?= ico('hand-coins', 'ico-16') ?>Envoyer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid gap-6 lg:grid-cols-2">
    <form action="" method="POST" enctype="multipart/form-data" class="carte self-start">
      <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
      <div class="border-b border-line p-4">
        <h2 class="carte-titre">Créer un produit de stock</h2>
        <p class="meta mt-1">Réservé au stock des revendeurs : il n'apparaît pas dans le catalogue des affiliés.</p>
      </div>
      <div class="flex flex-col gap-4 p-4">
        <div class="champ">
          <label class="champ-label" for="stock-nom">Nom du produit</label>
          <input class="champ-saisie" type="text" id="stock-nom" name="nom_produit_stock" required>
        </div>
        <div class="champ">
          <label class="champ-label" for="stock-image">Photo</label>
          <input class="text-sm text-text-2 file:mr-3 file:rounded file:border file:border-line-strong file:bg-surface file:px-3 file:py-2 file:text-sm file:font-medium file:text-text" type="file" id="stock-image" name="image_produit_stock" accept=".jpg,.jpeg,.png,.webp">
          <p class="champ-aide">JPG, PNG ou WEBP, 2 Mo maximum.</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div class="champ">
            <label class="champ-label" for="stock-prix">Prix (FCFA)</label>
            <input class="champ-saisie chiffres" type="number" step="0.01" id="stock-prix" name="prix_produit_stock" required>
          </div>
          <div class="champ">
            <label class="champ-label" for="stock-commission">Commission par unité</label>
            <input class="champ-saisie chiffres" type="number" step="0.01" id="stock-commission" name="commission_produit_stock" value="500" min="0">
          </div>
        </div>
        <button type="submit" name="action_creer_produit_stock" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Créer le produit</span></button>
      </div>
    </form>

    <div class="carte self-start">
      <h2 class="carte-entete carte-titre">Attribuer du stock</h2>
      <?php if (empty($produits_catalogue_complet)): ?>
        <p class="p-4 text-sm text-text-2">Créez d'abord un produit de stock pour pouvoir l'attribuer.</p>
      <?php else: ?>
        <form action="" method="POST" class="flex flex-col gap-4 p-4">
          <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
          <div class="champ">
            <label class="champ-label" for="stock-user">Revendeur</label>
            <select class="champ-saisie" id="stock-user" name="stock_user_id" required>
              <option value="">Choisir un membre</option>
              <?php foreach ($utilisateurs as $u): ?>
                <option value="<?= (int) $u['id'] ?>"><?= e($u['fullname']) ?> (<?= e($u['role']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="champ">
            <label class="champ-label" for="stock-produit">Produit</label>
            <select class="champ-saisie" id="stock-produit" name="stock_produit_id" required>
              <option value="">Choisir un produit</option>
              <?php foreach ($produits_catalogue_complet as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['nom_produit']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="champ">
            <label class="champ-label" for="stock-quantite">Quantité à ajouter</label>
            <input class="champ-saisie chiffres" type="number" id="stock-quantite" name="stock_quantite" min="1" required>
            <p class="champ-aide">S'ajoute au stock existant du revendeur pour ce produit.</p>
          </div>
          <button type="submit" name="action_attribuer_stock" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Attribuer</span></button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid gap-6 lg:grid-cols-2">
    <div class="carte overflow-hidden">
      <div class="carte-entete"><h2 class="carte-titre">Stocks attribués</h2><span class="meta chiffres"><?= count($stocks_tous_utilisateurs) ?> ligne<?= count($stocks_tous_utilisateurs) > 1 ? 's' : '' ?></span></div>
      <div class="max-h-[60vh] overflow-auto">
        <table class="tableau tableau-empile">
          <thead><tr><th scope="col">Produit</th><th scope="col">Revendeur</th><th scope="col" class="col-montant">Disponible</th><th scope="col" class="col-montant">Vendu</th></tr></thead>
          <tbody>
            <?php if (empty($stocks_tous_utilisateurs)): ?>
              <tr><td colspan="4"><p class="px-4 py-6 text-sm text-text-2">Aucun stock attribué.</p></td></tr>
            <?php else: foreach ($stocks_tous_utilisateurs as $s): ?>
              <tr>
                <td data-label="" class="font-medium"><?= e($s['nom_produit']) ?></td>
                <td data-label="Revendeur"><?= e($s['fullname']) ?></td>
                <td data-label="Disponible" class="col-montant chiffres"><?= (int) $s['quantite_disponible'] ?></td>
                <td data-label="Vendu" class="col-montant chiffres"><?= (int) $s['quantite_vendue'] ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="carte overflow-hidden">
      <div class="carte-entete"><h2 class="carte-titre">Produits de stock</h2><span class="meta chiffres"><?= count($produits_catalogue_complet) ?></span></div>
      <?php if (empty($produits_catalogue_complet)): ?>
        <p class="p-4 text-sm text-text-2">Aucun produit de stock.</p>
      <?php else: foreach ($produits_catalogue_complet as $p): ?>
        <div class="ligne-tx">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded bg-surface-2">
            <?php if (!empty($p['image'])): ?><img src="<?= e($img_stock($p['image'])) ?>" alt="" width="40" height="40" loading="lazy" class="h-full w-full object-contain"><?php else: ?><?= ico('package', 'text-text-3') ?><?php endif; ?>
          </span>
          <div class="ligne-tx-corps"><p class="ligne-tx-titre"><?= e($p['nom_produit']) ?></p><p class="ligne-tx-meta"><?= e(formaterMontant($p['prix_vente'])) ?></p></div>
          <div class="ligne-tx-montant"><span class="meta block">Par unité</span><?= montant($p['commission_fixe']) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>
