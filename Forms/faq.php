<section id="questions" class="scroll-mt-16 border-b border-line bg-bg" aria-labelledby="titre-questions">
  <div class="conteneur grid gap-8 py-12 lg:grid-cols-[320px_minmax(0,1fr)] lg:py-16">
    <div>
      <h2 id="titre-questions" class="text-2xl font-semibold lg:text-3xl">Questions fréquentes</h2>
      <p class="mt-2 text-text-2">Vous ne trouvez pas la réponse ? Écrivez à <a class="lien" href="mailto:contact@monrevenu.xyz">contact@monrevenu.xyz</a>.</p>
    </div>
    <div class="carte divide-y divide-line px-4">
      <details class="accordeon">
        <summary>Faut-il payer pour s'inscrire ?<?= ico('chevron-down') ?></summary>
        <p class="pb-4 text-text-2">Non. L'inscription est gratuite et vous n'achetez aucun stock.</p>
      </details>
      <details class="accordeon">
        <summary>Pourquoi dois-je vérifier mon numéro WhatsApp ?<?= ico('chevron-down') ?></summary>
        <p class="pb-4 text-text-2">Le catalogue et les liens d'affiliation s'ouvrent après la vérification. Vous envoyez un code par WhatsApp depuis votre tableau de bord ; le compte est débloqué à la réception du message.</p>
      </details>
      <details class="accordeon">
        <summary>Mon client doit-il créer un compte ?<?= ico('chevron-down') ?></summary>
        <p class="pb-4 text-text-2">Non. Il ouvre votre lien, indique son nom, son numéro WhatsApp et son adresse, puis paie à la livraison.</p>
      </details>
      <details class="accordeon">
        <summary>Quand ma commission est-elle créditée ?<?= ico('chevron-down') ?></summary>
        <p class="pb-4 text-text-2">Quand l'équipe MonRevenu valide la vente. Avant cela, la commande apparaît avec le statut En attente. Une commande annulée ne donne pas de commission.</p>
      </details>
      <details class="accordeon">
        <summary>Combien vais-je gagner ?<?= ico('chevron-down') ?></summary>
        <p class="pb-4 text-text-2">Cela dépend uniquement des ventes validées : <?= formaterMontant($commission_basse) ?> par article pour un produit jusqu'à <?= formaterMontant($seuil_commission) ?>, <?= formaterMontant($commission_haute) ?> au-delà. MonRevenu ne garantit aucun revenu.</p>
      </details>
      <details class="accordeon">
        <summary>Comment retirer mon argent ?<?= ico('chevron-down') ?></summary>
        <p class="pb-4 text-text-2">Depuis votre portefeuille, à partir de <?= formaterMontant($minimum_retrait) ?>. Le montant est déduit de votre solde au moment de la demande, puis envoyé sur le numéro indiqué après validation. Une demande refusée est recréditée.</p>
      </details>
    </div>
  </div>
</section>
