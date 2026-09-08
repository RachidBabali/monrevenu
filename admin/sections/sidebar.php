<aside class="hidden lg:flex w-64 shrink-0 flex-col bg-white border-r border-slate-100 fixed top-0 left-0 h-screen z-40">

    <!-- LOGO -->
    <div class="h-16 min-h-[64px] flex items-center gap-3 px-5 border-b border-slate-100">
        <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center text-white font-extrabold text-lg shadow-card">
            M
        </div>

        <div>
            <p class="font-extrabold text-[15px] leading-tight">MonRevenu</p>
            <p class="text-[11px] text-slate-400 leading-tight">Espace Admin</p>
        </div>
    </div>


    <!-- NAVIGATION -->
    <nav class="flex-1 min-h-0 overflow-y-auto px-3 py-3 space-y-0.5" id="sidebar-nav">

        <p class="px-3 mb-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Pilotage
        </p>

        <!-- Utilisateurs -->
        <button onclick="switchTab('tab-utilisateurs', this)"
            class="nav-btn w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </span>

            <span class="truncate">Utilisateurs</span>

            <span class="ml-auto bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0">
                <?= $nb_utilisateurs ?>
            </span>
        </button>


        <!-- Produits -->
        <button onclick="switchTab('tab-produits', this)"
            class="nav-btn is-active w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                    <path d="m3.3 7 8.7 5 8.7-5"/>
                    <path d="M12 22V12"/>
                </svg>
            </span>

            <span class="truncate">Produits</span>
        </button>


        <!-- Ventes -->
        <button onclick="switchTab('tab-ventes', this)"
            class="nav-btn w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>
                    <path d="M3 6h18"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
            </span>

            <span class="truncate">Ventes affiliation</span>

            <?php if ($nb_ventes_attente > 0): ?>
                <span class="ml-auto bg-amber-100 text-amber-600 text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0">
                    <?= $nb_ventes_attente ?>
                </span>
            <?php endif; ?>
        </button>


        <!-- Formations -->
        <button onclick="switchTab('tab-formations', this)"
            class="nav-btn w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="m22 10-10-5L2 10l10 5 10-5Z"/>
                    <path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/>
                </svg>
            </span>

            <span class="truncate">Formations</span>
        </button>


        <!-- FINANCE -->
        <p class="px-3 mt-3 mb-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Finance
        </p>


        <!-- Commissions -->
        <button onclick="switchTab('tab-commissions', this)"
            class="nav-btn w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v10"/>
                    <path d="M9 9.5c0-1.5 1.3-2.5 3-2.5s3 1 3 2.5-1.3 2.5-3 2.5-3 1-3 2.5 1.3 2.5 3 2.5 3-1 3-2.5"/>
                </svg>
            </span>

            <span class="truncate">Commissions</span>
        </button>


        <!-- Retraits -->
        <button onclick="switchTab('tab-retraits', this)"
            class="nav-btn w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="6" width="20" height="12" rx="2"/>
                    <path d="M2 10h20"/>
                    <path d="M6 15h4"/>
                </svg>
            </span>

            <span class="truncate">Retraits</span>
        </button>


        <!-- Historique -->
        <button onclick="switchTab('tab-historique', this)"
            class="nav-btn w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 8v4l3 3"/>
                    <circle cx="12" cy="12" r="9"/>
                </svg>
            </span>

            <span class="truncate">Historique</span>
        </button>


        <!-- CROISSANCE -->
        <p class="px-3 mt-3 mb-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Croissance
        </p>


        <!-- Publicités -->
        <button onclick="switchTab('tab-publicites', this)"
            class="nav-btn w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2"/>
                    <path d="M8 21h8M12 17v4"/>
                </svg>
            </span>

            <span class="truncate">Publicités</span>
        </button>


        <!-- Stock revendeurs -->
        <button onclick="switchTab('tab-stock-revendeurs', this)"
            class="nav-btn w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold transition-colors">

            <span class="nav-icon w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 7 12 3 4 7l8 4 8-4Z"/>
                    <path d="M4 7v10l8 4 8-4V7"/>
                    <path d="M12 11v10"/>
                </svg>
            </span>

            <span class="truncate">Stock revendeurs</span>

            <?php if (!empty($ventes_stock_en_attente)): ?>
                <span class="ml-auto bg-amber-100 text-amber-600 text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0">
                    <?= count($ventes_stock_en_attente) ?>
                </span>
            <?php endif; ?>
        </button>

    </nav>


    <!-- ADMIN / DECONNEXION -->
    <div class="shrink-0 p-3 border-t border-slate-100 space-y-1">

        <div class="flex items-center gap-3 px-3 py-1.5 rounded-xl">

            <div class="w-9 h-9 rounded-full bg-primary-soft text-primary flex items-center justify-center text-xs font-extrabold shrink-0">
                <?= htmlspecialchars($admin_initiales) ?>
            </div>

            <div class="min-w-0">
                <p class="text-[12px] font-bold text-ink truncate">
                    <?= htmlspecialchars($admin['fullname'] ?? 'Admin') ?>
                </p>

                <p class="text-[10px] text-slate-400 truncate">
                    Super Administrateur
                </p>
            </div>

        </div>


        <a href="/index.php?logout=1"
           onclick="return confirm('Voulez-vous vraiment vous déconnecter ?');"
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-semibold text-red-500 hover:bg-red-50 transition-colors">

            <span class="w-9 h-9 rounded-lg flex items-center justify-center bg-red-50 text-red-500 shrink-0">

                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>

            </span>

            <span>Se déconnecter</span>

        </a>

    </div>

</aside>