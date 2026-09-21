<dialog id="modal-edition-produit" class="feuille" aria-labelledby="titre-edition-produit">
  <div class="poignee"></div>
  <form action="" method="POST" class="flex flex-col">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="produit_id" id="edit-produit-id">
    <div class="feuille-entete">
      <h2 class="feuille-titre" id="titre-edition-produit">Modifier le produit</h2>
      <button type="button" class="btn btn-icone btn-discret" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
    </div>
    <div class="feuille-corps flex flex-col gap-4">
      <div class="champ">
        <label class="champ-label" for="edit-produit-nom">Nom du produit</label>
        <input class="champ-saisie" type="text" name="nom_edit" id="edit-produit-nom" required>
      </div>
      <div class="champ">
        <label class="champ-label" for="edit-produit-description">Description</label>
        <textarea class="champ-saisie" name="description_edit" id="edit-produit-description" rows="3"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="champ">
          <label class="champ-label" for="edit-produit-prix">Prix (<span id="edit-produit-devise"><?= e(deviseLibelle()) ?></span>)</label>
          <input class="champ-saisie chiffres" type="number" step="0.01" name="prix_edit" id="edit-produit-prix" required>
        </div>
        <div class="champ">
          <label class="champ-label" for="edit-produit-commission">Commission (%)</label>
          <input class="champ-saisie chiffres" type="number" name="commission_pourcentage_edit" id="edit-produit-commission" min="0" max="100">
        </div>
      </div>
      <p class="champ-aide">Pour changer la photo, supprimez le produit puis recréez-le avec la nouvelle photo.</p>
    </div>
    <div class="feuille-pied">
      <button type="button" class="btn btn-secondaire" data-fermer>Annuler</button>
      <button type="submit" name="action_edit_produit" class="btn btn-primaire"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Enregistrer</span></button>
    </div>
  </form>
</dialog>
