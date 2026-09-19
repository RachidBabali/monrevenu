<section class="border-b border-line" aria-labelledby="titre-confiance">
  <div class="conteneur py-12 lg:py-16">
    <div class="carte mb-8 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold">Vous vendez des produits ?</h2>
        <p class="mt-1 text-text-2">Publiez vos fiches et laissez les affiliés les faire connaître. Chaque fiche est vérifiée avant sa mise en ligne.</p>
      </div>
      <a class="btn btn-secondaire shrink-0" href="/#inscription-commercant">Ouvrir une boutique</a>
    </div>
    <h2 id="titre-confiance" class="text-2xl font-semibold lg:text-3xl">Vos données et vos contacts</h2>
    <div class="mt-6 grid gap-6 md:grid-cols-2">
      <div class="flex flex-col gap-3">
        <p class="text-text-2">Les règles du service et le traitement de vos données sont décrits dans nos pages légales. Vous pouvez supprimer votre compte à tout moment depuis votre profil.</p>
        <ul class="carte liste-nav">
          <li><a class="liste-nav-lien" href="/conditions.php"><?= ico('file-text') ?>Conditions générales d'utilisation<?= ico('chevron-right', 'ico-16') ?></a></li>
          <li><a class="liste-nav-lien" href="/confidentialite.php"><?= ico('shield') ?>Politique de confidentialité<?= ico('chevron-right', 'ico-16') ?></a></li>
          <li><a class="liste-nav-lien" href="/suppression-donnees.php"><?= ico('trash-2') ?>Suppression du compte et des données<?= ico('chevron-right', 'ico-16') ?></a></li>
        </ul>
      </div>
      <div class="flex flex-col gap-3">
        <p class="text-text-2">Une question sur une commande, une commission ou un retrait ? Écrivez-nous.</p>
        <ul class="carte liste-nav">
          <li><a class="liste-nav-lien" href="mailto:contact@monrevenu.xyz"><?= ico('mail') ?><span>contact@monrevenu.xyz</span><?= ico('chevron-right', 'ico-16') ?></a></li>
          <li><a class="liste-nav-lien" href="https://wa.me/<?= e($numero_wa_me) ?>" target="_blank" rel="noopener"><?= ico('whatsapp') ?><span>WhatsApp <span class="whitespace-nowrap"><?= e($numero_whatsapp) ?></span></span><?= ico('chevron-right', 'ico-16') ?></a></li>
        </ul>
      </div>
    </div>
  </div>
</section>
