<!-- ================= HEADER ================= -->
<header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-100">
  <div class="max-w-7xl mx-auto px-5 md:px-8 h-20 flex items-center justify-between">

    <!-- Logo -->
    <a href="index.php" class="flex items-center gap-3 shrink-0">
      <img src="assets/img/icon-192.png" alt="MonRevenu" class="h-9 w-9 object-contain"/>
      <div class="leading-tight">
        <div class="font-display font-extrabold text-[19px]">
          <span class="text-[#12213D]">Mon</span><span class="text-[#1E3F8F]">Revenu</span>
        </div>
        <div class="text-[11px] text-ink/45 font-medium -mt-0.5">Plateforme d'affiliation</div>
      </div>
    </a>

    <!-- Actions -->
    <div class="flex items-center gap-3">
      <button type="button" onclick="openModal('modal-register')"
              class="sm:hidden inline-flex bg-[#1E3F8F] hover:bg-[#152C66] text-white text-[13.5px] font-bold px-5 py-2.5 rounded-full transition-colors">
        Créer un compte
      </button>

      <button type="button" onclick="toggleMobileMenu()" aria-label="Menu" aria-expanded="false" id="mrMobileBtn"
              class="w-11 h-11 rounded-xl border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-colors">
        <svg class="w-5 h-5 text-[#12213D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="4" y1="7" x2="20" y2="7"/>
          <line x1="4" y1="12" x2="20" y2="12"/>
          <line x1="4" y1="17" x2="20" y2="17"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Menu mobile -->
  <div id="mrMobileMenu" class="hidden border-t border-slate-100 bg-white">
    <nav class="flex flex-col px-5 py-4 gap-1 text-[13.5px] font-semibold text-[#12213D]">
      <a href="#accueil" class="py-2.5" onclick="toggleMobileMenu()">Accueil</a>
      <a href="#fonctionnalites" class="py-2.5" onclick="toggleMobileMenu()">Fonctionnalités</a>
      <a href="#comment" class="py-2.5" onclick="toggleMobileMenu()">Comment ça marche</a>
      <a href="#apropos" class="py-2.5" onclick="toggleMobileMenu()">À propos</a>
      <div class="h-px bg-slate-100 my-2"></div>
      <button type="button" onclick="toggleMobileMenu(); openModal('modal-login')" class="py-2.5 text-left">
        Connexion
      </button>
      <button type="button" onclick="toggleMobileMenu(); openModal('modal-register')"
              class="mt-1 bg-[#1E3F8F] text-white font-bold px-5 py-3 rounded-full text-center">
        Créer un compte
      </button>
    </nav>
  </div>
</header>

<script>
function toggleMobileMenu() {
  document.getElementById('mrMobileMenu').classList.toggle('hidden');
}
</script>