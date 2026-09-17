<header class="h-20 bg-white/70 backdrop-blur border-b border-slate-100 px-5 lg:px-8 flex items-center justify-between sticky top-0 z-30">
            <div>
                <p class="text-[11px] text-slate-400 font-medium">Tableau de bord</p>
                <h1 class="text-[17px] font-extrabold text-ink">Administration MonRevenu</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden sm:flex items-center gap-1.5 bg-red-50 text-red-600 px-3 py-1.5 rounded-full text-[11px] font-bold border border-red-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Mode SuperAdmin
                </span>
                <a href="/index.php?logout=1" onclick="return confirm('Voulez-vous vraiment vous déconnecter ?');"
                   class="lg:hidden w-10 h-10 rounded-full bg-red-50 text-red-500 flex items-center justify-center border border-red-100" aria-label="Se déconnecter">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
                <div class="w-10 h-10 rounded-full bg-primary-soft text-primary flex items-center justify-center text-xs font-extrabold lg:hidden"><?= htmlspecialchars($admin_initiales) ?></div>
            </div>
        </header>