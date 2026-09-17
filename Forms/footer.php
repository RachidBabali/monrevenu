<!-- ================= FOOTER ================= -->
<footer class="bg-white pt-14 pb-8 border-t border-slate-100">
  <div class="max-w-7xl mx-auto px-5 md:px-8">
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-10 mb-10">

      <div class="max-w-xs">
        <div class="flex items-center gap-2 mb-2">
          <img src="assets/img/icon-192.png" alt="MonRevenu" class="h-7 w-7 object-contain"/>
          <span class="font-extrabold text-mr-blue text-base">MonRevenu</span>
        </div>
        <p class="text-sm text-mr-navy-soft">Votre revenu, notre mission.</p>
      </div>

      <nav class="grid grid-cols-2 sm:flex sm:flex-wrap gap-x-10 gap-y-3 text-sm font-semibold text-mr-navy-soft">
        <a href="#accueil" class="hover:text-mr-navy transition-colors">Accueil</a>
        <a href="#fonctionnalites" class="hover:text-mr-navy transition-colors">Fonctionnalités</a>
        <a href="#comment" class="hover:text-mr-navy transition-colors">Comment ça marche</a>
        <a href="#apropos" class="hover:text-mr-navy transition-colors">À propos</a>
        <a href="/contact.php" class="hover:text-mr-navy transition-colors">Contact</a>
        <a href="/conditions.php" class="hover:text-mr-navy transition-colors">Conditions d'utilisation</a>
        <a href="/confidentialite.php" class="hover:text-mr-navy transition-colors">Politique de confidentialité</a>
        <a href="/suppression-donnees.php" class="hover:text-mr-navy transition-colors">Suppression des données</a>
      </nav>
    </div>

    <div class="pt-6 border-t border-slate-100 text-xs text-mr-navy-soft">
      © <?= date('Y') ?> MonRevenu. Tous droits réservés.
    </div>
  </div>
</footer>