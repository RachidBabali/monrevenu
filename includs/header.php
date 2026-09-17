<!-- ════ OVERLAY menu mobile ════ -->
<div id="menuOverlay" onclick="closeMenu()"></div>

<!-- ════ NAVBAR ════ -->


<nav id="nav">
  <div class="max-w-6xl mx-auto px-6 h-full flex items-center justify-between gap-4">

    <!-- Logo agrandi -->
    <a href="/index.php" class="flex items-center gap-3 shrink-0" style="text-decoration:none">
      <div class="w-14 h-14 rounded-2xl overflow-hidden border border-[#e2e8f0] shadow-sm">
        <img src="Logo/logo.jpg" alt="Mon Revenu" class="w-full h-full object-cover">
      </div>
      <div>
        <div class="f-syne font-extrabold text-lg tracking-tight" style="color:var(--navy)">
          MON <span style="color:var(--blue)">REVENU</span>
        </div>
        <div class="f-mono text-[10px] tracking-widest uppercase" style="color:var(--text-muted)">Plateforme Partenaire</div>
      </div>
    </a>

    <!-- Liens desktop -->
    <div id="desktopNav" class="flex items-center gap-1 flex-1 justify-center">
      <a href="/index.php" class="flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-xl text-slate-500 hover:text-[#0066cc] hover:bg-[#f4f7fc] transition" style="text-decoration:none">
        <i class="ti ti-home" style="font-size:16px;"></i> Accueil
      </a>
      <a href="/annuaire_prestataires.php" class="flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-xl text-slate-500 hover:text-[#0066cc] hover:bg-[#f4f7fc] transition" style="text-decoration:none">
  <i class="ti ti-layout-grid" style="font-size:16px;"></i> Services
   </a>
      <a href="/index.php#apropos" class="flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-xl text-slate-500 hover:text-[#0066cc] hover:bg-[#f4f7fc] transition" style="text-decoration:none">
        <i class="ti ti-info-circle" style="font-size:16px;"></i> À propos
      </a>
    </div>

    <!-- Boutons -->
    <div id="desktopCta" class="flex items-center gap-2 shrink-0">
      <button onclick="openModal('login')" class="btn-ghost flex items-center gap-2">
        <i class="ti ti-login" style="font-size:16px;"></i> Connexion
      </button>
      <button onclick="openModal('register')" class="btn-primary flex items-center gap-2" style="padding:9px 20px;font-size:.8rem">
        <i class="ti ti-user-plus" style="font-size:16px;"></i> S'inscrire
      </button>
    </div>

    <!-- Hamburger -->
    <button id="hamburger" onclick="toggleMenu()" aria-label="Menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- ════ MENU MOBILE (Plein écran) ════ -->
<div id="mobileMenu" role="navigation" aria-label="Menu mobile">
  <div class="badge mb-2">Menu</div>

  <a href="/index.php" onclick="closeMenu()">
    <i class="ti ti-home" style="font-size:20px; margin-right:10px; vertical-align:-3px;"></i>Accueil
  </a>
  <a href="/annuaire_prestataires.php" onclick="closeMenu()">
    <i class="ti ti-layout-grid" style="font-size:20px; margin-right:10px; vertical-align:-3px;"></i>Services
  </a>
  <a href="/index.php#apropos" onclick="closeMenu()">
    <i class="ti ti-info-circle" style="font-size:20px; margin-right:10px; vertical-align:-3px;"></i>À propos
  </a>

  <div class="divider"></div>

  <button onclick="closeMenu();openModal('login')" style="color:var(--text-muted);font-size:1rem;display:flex;align-items:center;gap:10px;">
    <i class="ti ti-login" style="font-size:20px;"></i>Connexion
  </button>
  <button onclick="closeMenu();openModal('register')" class="cta" style="display:flex;align-items:center;justify-content:center;gap:10px;">
    <i class="ti ti-user-plus" style="font-size:20px;"></i>S'inscrire maintenant
  </button>
</div>
