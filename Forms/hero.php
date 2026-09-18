<section class="border-b border-line bg-surface" aria-labelledby="titre-ouverture">
  <div class="conteneur grid items-center gap-10 py-10 lg:grid-cols-[minmax(0,1fr)_440px] lg:gap-16 lg:py-20">
    <div class="flex max-w-xl flex-col gap-5">
      <h1 id="titre-ouverture" class="text-[clamp(28px,5vw,44px)] font-semibold leading-[1.15] text-text">Gagnez une commission sur chaque vente réalisée avec votre lien</h1>
      <p class="text-lg text-text-2">Choisissez un produit du catalogue MonRevenu et partagez votre lien sur WhatsApp. Quand la commande de votre client est validée, la commission est créditée sur votre portefeuille.</p>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <button type="button" class="btn btn-primaire" data-ouvrir="modal-register">Créer un compte</button>
        <a class="lien cible px-1 text-base" href="#fonctionnement">Voir comment ça marche</a>
      </div>
      <p class="text-sm text-text-3">Inscription gratuite. Aucun stock à gérer, aucun paiement à encaisser.</p>
    </div>

    <figure class="flex flex-col gap-3" aria-label="Exemple d'affichage de l'espace affilié">
      <figcaption class="text-xs font-medium text-text-3">Exemple d'affichage</figcaption>
      <div class="grid items-start gap-3 sm:grid-cols-[minmax(0,180px)_minmax(0,1fr)]" aria-hidden="true">
        <div class="produit">
          <div class="produit-image max-sm:aspect-[3/1]"><div class="flex items-center justify-center text-text-3"><?= ico('package', 'ico-40') ?></div></div>
          <div class="produit-corps">
            <p class="produit-nom">Produit du catalogue</p>
            <p class="produit-prix"><?= montant(15000) ?></p>
            <p class="produit-commission"><span>Commission</span><?= montant($commission_haute) ?></p>
          </div>
          <div class="produit-actions sm:flex-col">
            <span class="btn btn-sm btn-primaire"><?= ico('copy', 'ico-16') ?>Copier le lien</span>
          </div>
        </div>
        <div class="carte flex flex-col">
          <p class="border-b border-line px-3 py-2 text-xs font-medium text-text-3">Dernières commissions</p>
          <div class="ligne-tx px-3"><div class="ligne-tx-corps"><p class="ligne-tx-titre">Commande de 1 article</p><div class="mt-1"><?= badgeStatut('en_attente', 'commission') ?></div></div><div class="ligne-tx-montant"><?= montant($commission_haute) ?></div></div>
          <div class="ligne-tx px-3"><div class="ligne-tx-corps"><p class="ligne-tx-titre">Commande de 1 article</p><div class="mt-1"><?= badgeStatut('validee', 'commission') ?></div></div><div class="ligne-tx-montant"><?= montant($commission_basse, true, 'montant-entrant') ?></div></div>
          <div class="ligne-tx px-3"><div class="ligne-tx-corps"><p class="ligne-tx-titre">Retrait</p><div class="mt-1"><?= badgeStatut('valide', 'retrait') ?></div></div><div class="ligne-tx-montant"><?= montant(-$minimum_retrait) ?></div></div>
        </div>
      </div>
    </figure>
  </div>
</section>
