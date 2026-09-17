            <section id="tab-retraits" class="tab-content hidden">
                <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card">
                    <div class="flex items-center gap-2.5 mb-1">
                        <div class="w-9 h-9 rounded-xl bg-red-50 text-red-500 flex items-center justify-center">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
                        </div>
                        <div>
                            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Trésorerie</p>
                            <h2 class="text-[14px] font-extrabold text-ink leading-none">Suivi des demandes de retraits émises</h2>
                        </div>
                    </div>
                    <p class="text-[12px] text-slate-400 mb-5 ml-11">Effectuez le transfert d'argent réel (Mvola), puis confirmez l'action ci-dessous.</p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">
                                    <th class="pb-3">ID demande</th>
                                    <th class="pb-3">Affilié / agent</th>
                                    <th class="pb-3">Montant requis</th>
                                    <th class="pb-3">Statut actuel</th>
                                    <th class="pb-3 text-center">Validation finale</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-[13px]" id="live-withdrawal-table">
                                <?php if (empty($retraits)): ?>
                                    <tr><td colspan="5" class="text-center py-12">
                                        <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg>
                                        <p class="text-slate-400 text-[12px] font-medium">Aucune demande de retrait pour le moment.</p>
                                    </td></tr>
                                <?php else: foreach ($retraits as $r):
                                    $statut_r = $r['status'];
                                    $en_attente_r = in_array($statut_r, ['en_attente', 'pending'], true);
                                    $couleur_r = [
                                        'en_attente' => 'bg-amber-50 text-amber-600 border-amber-200',
                                        'pending'    => 'bg-amber-50 text-amber-600 border-amber-200',
                                        'valide'     => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                        'approved'   => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                        'rejected'   => 'bg-red-50 text-red-500 border-red-200',
                                        'rejete'     => 'bg-red-50 text-red-500 border-red-200',
                                    ][$statut_r] ?? '';
                                    $libelle_r = [
                                        'en_attente' => 'En attente', 'pending' => 'En attente',
                                        'valide' => 'Validé', 'approved' => 'Validé',
                                        'rejected' => 'Refusé', 'rejete' => 'Refusé',
                                    ][$statut_r] ?? $statut_r;
                                ?>
                                <tr class="hover:bg-canvas/60 transition-colors">
                                    <td class="py-3.5 font-mono text-[11px] text-slate-400">#W-<?= $r['id'] ?></td>
                                    <td class="py-3.5 font-bold text-ink">
                                        <?= htmlspecialchars($r['utilisateur_nom']) ?>
                                        <p class="text-[10px] font-normal text-slate-400"><?= htmlspecialchars($r['method'] ?? '—') ?></p>
                                    </td>
                                    <td class="py-3.5 font-extrabold text-red-500"><?= number_format((float) $r['amount'], 0, ',', ' ') ?> KMF</td>
                                    <td class="py-3.5"><span class="<?= $couleur_r ?> border px-2.5 py-1 rounded-full text-[10px] font-bold"><?= htmlspecialchars($libelle_r) ?></span></td>
                                    <td class="py-3.5 text-center">
                                        <?php if ($en_attente_r): ?>
                                            <div class="flex items-center justify-center gap-2">
                                                <form action="" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="withdrawal_id" value="<?= (int) $r['id'] ?>">
                                                    <button type="submit" name="action_valider_retrait"
                                                            onclick="return confirm('Confirmez-vous avoir envoyé <?= number_format((float) $r['amount'], 0, ',', ' ') ?> KMF à <?= htmlspecialchars($r['utilisateur_nom']) ?> via <?= htmlspecialchars($r['method'] ?? '') ?> ?');"
                                                            class="bg-emerald-50 hover:bg-emerald-500 text-emerald-600 hover:text-white px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all shadow-sm">Accepter</button>
                                                </form>
                                                <form action="" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="withdrawal_id" value="<?= (int) $r['id'] ?>">
                                                    <button type="submit" name="action_refuser_retrait"
                                                            onclick="return confirm('Refuser cette demande ? Le montant sera recrédité à l\'utilisateur.');"
                                                            class="bg-red-50 hover:bg-red-500 text-red-500 hover:text-white px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all shadow-sm">Refuser</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-400">Déjà traité</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>