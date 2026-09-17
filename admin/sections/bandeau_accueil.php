 <div class="relative overflow-hidden rounded-[28px] bg-primary p-7 lg:p-9 text-white shadow-card">
                <div class="absolute inset-0 dot-grid opacity-40"></div>
                <div class="absolute -right-10 -top-16 w-56 h-56 rounded-full bg-white/10"></div>
                <div class="absolute right-16 bottom-[-40px] w-32 h-32 rounded-full bg-mint/30"></div>

                <div class="relative">
                    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-7">
                        <div>
                            <p class="text-[13px] text-white/70 font-semibold mb-1">Bonjour, <?= htmlspecialchars($admin_prenom) ?> 👋</p>
                            <h2 class="text-[24px] lg:text-[28px] font-extrabold leading-tight max-w-md">Voici l'état de votre écosystème d'affiliation aujourd'hui</h2>
                        </div>
                        <p class="text-[11px] text-white/60 font-mono font-medium">Actualisé le <?= date('d/m/Y à H:i') ?></p>
                    </div>

                    <div class="relative grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                        <div class="kpi-card bg-white/15 backdrop-blur rounded-2xl px-4 py-4 lg:px-5">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-[9.5px] lg:text-[11px] text-white/70 font-semibold uppercase tracking-wide">Utilisateurs</p>
                                <svg class="w-3.5 h-3.5 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <p class="text-[20px] lg:text-[26px] font-extrabold leading-none"><?= $nb_utilisateurs ?></p>
                            <p class="text-[9.5px] lg:text-[10.5px] text-white/60 font-medium mt-1.5 leading-tight">
                                <?= implode(' · ', array_filter([
                                    isset($repartition_roles['affilie']) ? $repartition_roles['affilie'] . ' ' . $libelles_roles['affilie'] : null,
                                    isset($repartition_roles['agent']) ? $repartition_roles['agent'] . ' ' . $libelles_roles['agent'] : null,
                                ])) ?: 'Aucun rôle enregistré' ?>
                            </p>
                        </div>
                        <div class="kpi-card bg-white/15 backdrop-blur rounded-2xl px-4 py-4 lg:px-5">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-[9.5px] lg:text-[11px] text-white/70 font-semibold uppercase tracking-wide">Produits actifs</p>
                                <svg class="w-3.5 h-3.5 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
                            </div>
                            <p class="text-[20px] lg:text-[26px] font-extrabold leading-none"><?= $nb_produits ?></p>
                            <p class="text-[9.5px] lg:text-[10.5px] text-white/60 font-medium mt-1.5 leading-tight">Au catalogue</p>
                        </div>
                        <div class="kpi-card bg-white/15 backdrop-blur rounded-2xl px-4 py-4 lg:px-5">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-[9.5px] lg:text-[11px] text-white/70 font-semibold uppercase tracking-wide">Ventes en attente</p>
                                <svg class="w-3.5 h-3.5 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            </div>
                            <p class="text-[20px] lg:text-[26px] font-extrabold leading-none"><?= $nb_ventes_attente ?></p>
                            <p class="text-[9.5px] lg:text-[10.5px] text-white/60 font-medium mt-1.5 leading-tight">À traiter</p>
                        </div>
                        <div class="kpi-card bg-mint rounded-2xl px-4 py-4 lg:px-5 text-ink">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-[9.5px] lg:text-[11px] font-semibold uppercase tracking-wide opacity-70">Commissions</p>
                                <svg class="w-3.5 h-3.5 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9.5c0-1.5 1.3-2.5 3-2.5s3 1 3 2.5-1.3 2-3 2.5-3 1-3 2.5 1.3 2.5 3 2.5 3-1 3-2.5"/></svg>
                            </div>
                            <p class="text-[20px] lg:text-[26px] font-extrabold leading-none"><?= number_format($total_commissions, 0, ',', ' ') ?></p>
                            <p class="text-[9.5px] lg:text-[10.5px] font-medium mt-1.5 opacity-70 leading-tight">KMF validées</p>
                        </div>
                    </div>
                </div>
            </div>