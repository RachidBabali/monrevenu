<section id="fonctionnement" class="scroll-mt-16 border-b border-line" aria-labelledby="titre-fonctionnement">
  <div class="conteneur py-12 lg:py-16">
    <h2 id="titre-fonctionnement" class="text-2xl font-semibold lg:text-3xl">Comment ça marche</h2>
    <p class="mt-2 max-w-lecture text-text-2">Trois étapes, du choix du produit au retrait de votre commission.</p>

    <ol class="mt-8 flex flex-col divide-y divide-line border-y border-line">
      <li class="grid gap-4 py-6 md:grid-cols-[minmax(0,1fr)_minmax(0,360px)] md:gap-10">
        <div>
          <h3 class="text-lg font-semibold"><span class="text-text-3">1.</span> Choisissez un produit</h3>
          <p class="mt-2 text-text-2">Le catalogue liste les produits proposés par les commerçants partenaires de MonRevenu. Chaque fiche affiche le prix et la commission que vous touchez pour une vente.</p>
          <p class="mt-2 text-sm text-text-3">Le catalogue s'ouvre après la vérification de votre numéro WhatsApp.</p>
        </div>
        <div class="carte flex items-center justify-between gap-4 p-4" aria-hidden="true">
          <div class="min-w-0"><p class="truncate text-sm font-medium">Produit du catalogue</p><p class="mt-1 text-sm"><?= montant(8500) ?></p></div>
          <p class="produit-commission flex-col items-end gap-0"><span>Commission</span><?= montant($commission_basse) ?></p>
        </div>
      </li>
      <li class="grid gap-4 py-6 md:grid-cols-[minmax(0,1fr)_minmax(0,360px)] md:gap-10">
        <div>
          <h3 class="text-lg font-semibold"><span class="text-text-3">2.</span> Partagez votre lien</h3>
          <p class="mt-2 text-text-2">Copiez votre lien personnel ou envoyez-le directement sur WhatsApp. Votre client commande sur la page du produit sans créer de compte : il laisse son nom, son numéro WhatsApp et son adresse, puis paie à la livraison.</p>
        </div>
        <div class="carte flex flex-col gap-2 p-4" aria-hidden="true">
          <span class="lien-copie-valeur">monrevenu.xyz/produit/...</span>
          <div class="grid grid-cols-2 gap-2">
            <span class="btn btn-sm btn-primaire"><?= ico('copy', 'ico-16') ?>Copier le lien</span>
            <span class="btn btn-sm btn-secondaire"><?= ico('whatsapp', 'ico-16') ?>WhatsApp</span>
          </div>
        </div>
      </li>
      <li class="grid gap-4 py-6 md:grid-cols-[minmax(0,1fr)_minmax(0,360px)] md:gap-10">
        <div>
          <h3 class="text-lg font-semibold"><span class="text-text-3">3.</span> Suivez la commande, puis retirez</h3>
          <p class="mt-2 text-text-2">La commande apparaît dans votre espace avec le statut En attente. Le client est contacté sur WhatsApp pour confirmer ; quand la vente est validée par MonRevenu, la commission passe à Créditée et s'ajoute à votre solde. Vous demandez ensuite un retrait depuis votre portefeuille.</p>
        </div>
        <div class="carte divide-y divide-line" aria-hidden="true">
          <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm"><span>Commande reçue</span><?= badgeStatut('en_attente', 'commission') ?></div>
          <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm"><span>Vente validée</span><?= badgeStatut('validee', 'commission') ?></div>
        </div>
      </li>
    </ol>
  </div>
</section>
