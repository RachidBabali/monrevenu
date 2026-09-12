<!-- ================= HEADER ================= -->
<header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-100">
  <div class="max-w-7xl mx-auto px-5 md:px-8 h-16 flex items-center justify-between">

    <!-- Logo (fichier réel : assets/img/logo-mark.png) -->
    <a href="index.php" class="flex items-center gap-2 shrink-0">
      <img src="assets/img/icon-192.png" alt="MonRevenu" class="h-8 w-8 object-contain"/>
      <span class="font-extrabold text-lg text-mr-blue">MonRevenu</span>
    </a>

    <!-- Nav desktop -->
    <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-mr-navy-soft">
      <a href="#accueil" class="text-mr-blue-dark">Accueil</a>
      <a href="#fonctionnalites" class="hover:text-mr-navy transition-colors">Fonctionnalités</a>
      <a href="#comment" class="hover:text-mr-navy transition-colors">Comment ça marche</a>
      <a href="#apropos" class="hover:text-mr-navy transition-colors">À propos</a>
    </nav>

    <!-- Actions desktop -->
    <div class="hidden md:flex items-center gap-5">
      <button type="button" onclick="openModal('modal-login')" class="text-sm font-semibold text-mr-navy hover:text-mr-blue-dark transition-colors">
        Connexion
      </button>
      <button type="button" onclick="openModal('modal-register')" class="inline-flex items-center gap-1.5 bg-mr-blue hover:bg-mr-blue-dark text-white text-sm font-bold px-5 py-2.5 rounded-full transition-colors">
        Créer un compte
      </button>
    </div>

    <!-- Bouton menu mobile -->
    <button type="button" onclick="toggleMobileMenu()" aria-label="Menu" aria-expanded="false" id="mrMobileBtn" class="md:hidden flex flex-col gap-1.5 p-2">
      <span class="w-5 h-0.5 bg-mr-navy rounded-full"></span>
      <span class="w-5 h-0.5 bg-mr-navy rounded-full"></span>
      <span class="w-5 h-0.5 bg-mr-navy rounded-full"></span>
    </button>
  </div>

  <!-- Menu mobile -->
  <div id="mrMobileMenu" class="hidden md:hidden border-t border-slate-100 bg-white">
    <nav class="flex flex-col px-5 py-4 gap-1 text-sm font-semibold text-mr-navy">
      <a href="#accueil" class="py-2.5" onclick="toggleMobileMenu()">Accueil</a>
      <a href="#fonctionnalites" class="py-2.5" onclick="toggleMobileMenu()">Fonctionnalités</a>
      <a href="#comment" class="py-2.5" onclick="toggleMobileMenu()">Comment ça marche</a>
      <a href="#apropos" class="py-2.5" onclick="toggleMobileMenu()">À propos</a>
      <div class="h-px bg-slate-100 my-2"></div>
      <button type="button" onclick="toggleMobileMenu(); openModal('modal-login')" class="py-2.5 text-left">
        Connexion
      </button>
      <button type="button" onclick="toggleMobileMenu(); openModal('modal-register')" class="mt-2 bg-mr-blue text-white font-bold px-5 py-3 rounded-full text-center">
        Créer un compte
      </button>
    </nav>
  </div>
</header>