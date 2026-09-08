            <section id="tab-historique" class="tab-content hidden">
                <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card">
                    <div class="flex items-center gap-2.5 mb-1">
                        <div class="w-9 h-9 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg>
                        </div>
                        <div>
                            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Journal</p>
                            <h2 class="text-[14px] font-extrabold text-ink leading-none">Historique global des transactions</h2>
                        </div>
                    </div>
                    <p class="text-[12px] text-slate-400 mb-5 ml-11">Toutes les transactions enregistrées sur la plateforme, tous utilisateurs confondus (50 plus récentes).</p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">
                                    <th class="pb-3">Utilisateur</th>
                                    <th class="pb-3">Type</th>
                                    <th class="pb-3">Montant</th>
                                    <th class="pb-3">Référence</th>
                                    <th class="pb-3">Description</th>
                                    <th class="pb-3">Statut</th>
                                    <th class="pb-3">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-[13px]">
                                <?php if (empty($historique)): ?>
                                    <tr><td colspan="7" class="text-center py-12">
                                        <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg>
                                        <p class="text-slate-400 text-[12px] font-medium">Aucune transaction enregistrée pour le moment.</p>
                                    </td></tr>
                                <?php else: foreach ($historique as $h): ?>
                                    <tr class="hover:bg-canvas/60 transition-colors">
                                        <td class="py-3 font-bold text-ink"><?= htmlspecialchars($h['utilisateur_nom']) ?></td>
                                        <td class="py-3">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $couleurs_type_tx[$h['type']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>">
                                                <?= htmlspecialchars($libelles_type_tx[$h['type']] ?? $h['type']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 font-extrabold text-ink"><?= number_format((float) $h['amount'], 0, ',', ' ') ?> KMF</td>
                                        <td class="py-3 font-mono text-[10px] text-slate-400"><?= htmlspecialchars($h['reference'] ?? '—') ?></td>
                                        <td class="py-3 text-[11px] text-slate-500 max-w-[220px] truncate"><?= htmlspecialchars($h['description'] ?? '—') ?></td>
                                        <td class="py-3">
                                            <?php
                                                $couleur_statut_tx = [
                                                    'en_attente' => 'bg-amber-50 text-amber-600 border-amber-200',
                                                    'complete'   => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                                    'echoue'     => 'bg-red-50 text-red-500 border-red-200',
                                                ][$h['status']] ?? '';
                                            ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $couleur_statut_tx ?>">
                                                <?= htmlspecialchars(ucfirst($h['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-[11px] text-slate-400"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>