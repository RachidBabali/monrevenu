<section id="tab-commissions" class="tab-content hidden" aria-labelledby="t-ajustement">
  <form action="" method="POST" class="carte max-w-xl">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <div class="border-b border-line p-4">
      <h2 id="t-ajustement" class="carte-titre">Créditer le solde d'un membre</h2>
      <p class="meta mt-1">Ajoute le montant au solde, enregistre une transaction de type commission et prévient le membre.</p>
    </div>
    <div class="flex flex-col gap-4 p-4">
      <div class="champ">
        <label class="champ-label" for="ajust-user">Membre</label>
        <select class="champ-saisie" id="ajust-user" name="user_id" required>
          <option value="">Choisir un membre</option>
          <?php foreach ($utilisateurs as $u): ?>
            <option value="<?= (int) $u['id'] ?>"><?= e($u['fullname']) ?> (<?= e($u['role']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <p class="champ-aide"><?= (int) $nb_utilisateurs ?> membres enregistrés.</p>
      </div>
      <div class="champ">
        <label class="champ-label" for="ajust-montant">Montant</label>
        <input class="champ-saisie chiffres" type="number" id="ajust-montant" name="montant" required inputmode="numeric" min="1">
      </div>
      <div class="champ">
        <label class="champ-label" for="ajust-motif">Motif</label>
        <input class="champ-saisie" type="text" id="ajust-motif" name="description" placeholder="Visible par le membre dans sa notification">
      </div>
      <button type="submit" name="action_transfert_commission" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Créditer le solde</span></button>
    </div>
  </form>
</section>
