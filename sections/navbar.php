<?php
// Sécurités au cas où les variables ne sont pas définies sur une page spécifique
$user_fullname = $user_fullname ?? $_SESSION['user_fullname'] ?? 'Utilisateur';
$user_initials = $user_initials ?? strtoupper(substr($user_fullname, 0, 2)) ?: 'U';
$user_role     = $user_role ?? $_SESSION['user_role'] ?? 'Client';
?>

<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>

<aside id="sidebar" class="fixed top-0 left-0 bottom-0 w-64 bg-[#1246A0] text-white z-50 flex flex-col justify-between transition-transform duration-300 transform -translate-x-full lg:translate-x-0">
  
  <div class="p-6">
    <div class="flex items-center gap-3 mb-10">
      <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center font-bold text-lg text-white">
        M
      </div>
      <span class="font-bold text-[18px] tracking-wide">Mon<span class="text-white/60 font-medium">Revenu</span></span>
    </div>

    <div class="mb-8">
      <p class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-4">Principal</p>
      <nav class="flex flex-col gap-1">
        <a href="/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-[13px] font-medium text-white/70 hover:bg-white/10 hover:text-white transition-all">
          <svg class="w-5 h-5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Accueil
        </a>
        
        <a href="/page/messagerie.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-[13px] font-medium text-white/70 hover:bg-white/10 hover:text-white transition-all">
          <svg class="w-5 h-5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Messagerie
        </a>
        <a href="/page/historique.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-[13px] font-medium <?= $current_page == 'historique.php' ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' ?> transition-all">
  <svg class="w-5 h-5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
  Historique
</a>
                <a href="/page/profil.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-[13px] font-medium text-white/70 hover:bg-white/10 hover:text-white transition-all">
          <svg class="w-5 h-5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 1 0-16 0"/><circle cx="12" cy="8" r="4"/></svg>
          Profil
        </a>
      </nav>
    </div>

    
  </div>

  <div class="p-4 border-t border-white/10 flex items-center justify-between bg-black/10">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center font-bold text-[13px] text-white tracking-wider">
        <?= htmlspecialchars($user_initials) ?>
      </div>
      <div class="leading-tight">
        <p class="font-semibold text-[13px] truncate max-w-[110px]"><?= htmlspecialchars($user_fullname) ?></p>
        <p class="text-[10px] text-white/50 capitalize"><?= htmlspecialchars($user_role) ?></p>
      </div>
    </div>
   <a href="/index.php?logout=1" class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all" title="Déconnexion">
  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
</a>
  </div>

</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        // Si elle est cachée, on l'affiche, sinon on la cache
        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            overlay.classList.add('hidden');
        }
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay.classList.add('hidden');
    }
}
</script>