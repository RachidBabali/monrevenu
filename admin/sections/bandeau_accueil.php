<section class="flex flex-col gap-3" aria-labelledby="t-apercu-admin">
  <div class="flex flex-wrap items-end justify-between gap-2">
    <h2 id="t-apercu-admin" class="section-titre">Bonjour, <?= e($admin_prenom) ?></h2>
    <p class="meta">Données au <?= e(dateFr('now', 'heure')) ?></p>
  </div>
  <dl class="indicateurs">
    <div class="indicateur">
      <dt class="indicateur-libelle">Utilisateurs</dt>
      <dd class="indicateur-valeur"><?= (int) $nb_utilisateurs ?></dd>
      <dd class="indicateur-note"><?= e(implode(', ', array_filter([
          isset($repartition_roles['affilie']) ? $repartition_roles['affilie'] . ' ' . $libelles_roles['affilie'] : null,
          isset($repartition_roles['agent']) ? $repartition_roles['agent'] . ' ' . $libelles_roles['agent'] : null,
      ])) ?: 'Aucun rôle enregistré') ?></dd>
    </div>
    <div class="indicateur">
      <dt class="indicateur-libelle">Produits récents</dt>
      <dd class="indicateur-valeur"><?= (int) $nb_produits ?></dd>
      <dd class="indicateur-note">10 derniers au catalogue</dd>
    </div>
    <div class="indicateur">
      <dt class="indicateur-libelle">Ventes en attente</dt>
      <dd class="indicateur-valeur"><?= (int) $nb_ventes_attente ?></dd>
      <dd class="indicateur-note">à traiter, sur les 30 dernières</dd>
    </div>
    <div class="indicateur">
      <dt class="indicateur-libelle">Commissions validées</dt>
      <dd class="indicateur-valeur"><?= formaterMontant($total_commissions) ?></dd>
      <dd class="indicateur-note">sur les 30 dernières ventes</dd>
    </div>
  </dl>
</section>
