<section id="remuneration" class="scroll-mt-16 border-b border-line bg-bg" aria-labelledby="titre-remuneration">
  <div class="conteneur grid gap-10 py-12 lg:grid-cols-2 lg:py-16">
    <div class="flex min-w-0 flex-col gap-4">
      <h2 id="titre-remuneration" class="text-2xl font-semibold lg:text-3xl">Rémunération</h2>
      <p class="max-w-lecture text-text-2">La commission est un montant fixe par article vendu. Elle dépend du prix du produit, pas du nombre de clics ni du nombre d'inscrits.</p>
      <div class="carte overflow-hidden">
        <table class="tableau">
          <caption class="sr-only">Commission par article selon le prix du produit</caption>
          <thead><tr><th scope="col">Prix du produit</th><th scope="col" class="col-montant">Commission par article</th></tr></thead>
          <tbody>
            <tr><td>Jusqu'à <?= montant($seuil_commission, false, 'font-normal') ?></td><td class="col-montant"><?= montant($commission_basse) ?></td></tr>
            <tr><td>Plus de <?= montant($seuil_commission, false, 'font-normal') ?></td><td class="col-montant"><?= montant($commission_haute) ?></td></tr>
          </tbody>
        </table>
      </div>
      <p class="text-sm text-text-2">Une commande de plusieurs articles rapporte la commission autant de fois que d'articles commandés. La commission n'est créditée que si la vente est validée ; une commande annulée ne rapporte rien.</p>
    </div>

    <div id="retraits" class="flex min-w-0 scroll-mt-16 flex-col gap-4">
      <h2 class="text-2xl font-semibold lg:text-3xl">Retraits</h2>
      <p class="max-w-lecture text-text-2">Votre solde se retire depuis le portefeuille de votre espace, sur un compte mobile money.</p>
      <dl class="recap bg-surface">
        <div class="recap-ligne"><dt>Montant minimum</dt><dd class="montant"><?= formaterMontant($minimum_retrait) ?></dd></div>
        <div class="recap-ligne"><dt>Moyen de retrait proposé</dt><dd>Mvola</dd></div>
        <div class="recap-ligne"><dt>Traitement</dt><dd>Validation par l'équipe MonRevenu</dd></div>
        <div class="recap-ligne"><dt>Demande refusée</dt><dd>Montant recrédité sur le solde</dd></div>
      </dl>
      <p class="text-sm text-text-2">Chaque demande et son statut (En attente, Payé, Refusé) restent visibles dans votre historique.</p>
    </div>
  </div>
</section>
