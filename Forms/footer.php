<footer class="bg-surface">
  <div class="conteneur flex flex-col gap-6 py-10 md:flex-row md:items-start md:justify-between">
    <div class="flex max-w-sm flex-col gap-3">
      <a href="/" class="flex min-h-[44px] items-center gap-2" aria-label="MonRevenu, accueil">
        <img src="/assets/img/svg/monrevenu-marque.svg" alt="" width="28" height="28" class="logo-marque h-7 w-7" loading="lazy">
        <span class="font-semibold text-primary-ink">MonRevenu</span>
      </a>
      <p class="text-sm text-text-2">Plateforme d'affiliation : partagez les produits de commerçants partenaires et touchez une commission fixe sur chaque vente validée.</p>
    </div>
    <nav class="grid grid-cols-2 gap-x-10 gap-y-2 text-sm" aria-label="Liens du pied de page">
      <a class="cible text-text-2 hover:text-primary-ink" href="#fonctionnement">Fonctionnement</a>
      <a class="cible text-text-2 hover:text-primary-ink" href="/conditions.php">Conditions générales</a>
      <a class="cible text-text-2 hover:text-primary-ink" href="#remuneration">Rémunération</a>
      <a class="cible text-text-2 hover:text-primary-ink" href="/confidentialite.php">Confidentialité</a>
      <a class="cible text-text-2 hover:text-primary-ink" href="#questions">Questions</a>
      <a class="cible text-text-2 hover:text-primary-ink" href="/suppression-donnees.php">Suppression des données</a>
      <a class="cible text-text-2 hover:text-primary-ink" href="/contact.php">Contact</a>
    </nav>
  </div>
  <div class="border-t border-line">
    <p class="conteneur py-4 text-xs text-text-3">&copy; <?= date('Y') ?> MonRevenu</p>
  </div>
</footer>
