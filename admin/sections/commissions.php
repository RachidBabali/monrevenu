<section id="tab-commissions" class="tab-content hidden">
                <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card max-w-xl mx-auto">
                    <div class="flex items-center gap-2.5 mb-5">
                        <div class="w-9 h-9 rounded-xl bg-mint-soft text-mint flex items-center justify-center">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9.5c0-1.5 1.3-2.5 3-2.5s3 1 3 2.5-1.3 2-3 2.5-3 1-3 2.5 1.3 2.5 3 2.5 3-1 3-2.5"/></svg>
                        </div>
                        <div>
                            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Ajustement manuel</p>
                            <h2 class="text-[14px] font-extrabold text-ink leading-none">Transférer ou corriger le solde d'un membre</h2>
                        </div>
                    </div>
                    <form action="" method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Sélectionner l'utilisateur bénéficiaire *</label>
                            <select name="user_id" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                <option value="">-- Choisissez un utilisateur / affilié --</option>
                                <?php foreach($utilisateurs as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= strtoupper($u['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1"><?= $nb_utilisateurs ?> utilisateur<?= $nb_utilisateurs > 1 ? 's' : '' ?> enregistré<?= $nb_utilisateurs > 1 ? 's' : '' ?> au total.</p>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Montant à octroyer (KMF) *</label>
                            <input type="number" name="montant" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Motif / description du transfert</label>
                            <input type="text" name="description" placeholder="Ex: Bonus de parrainage exceptionnel" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                        </div>

                        <button type="submit" name="action_transfert_commission" class="w-full bg-mint hover:opacity-90 text-white text-[13px] font-bold py-3 rounded-xl transition-opacity shadow-sm">
                            Valider l'envoi de la commission
                        </button>
                    </form>
                </div>
            </section>