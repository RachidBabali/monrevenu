
            <section id="tab-ventes" class="tab-content hidden">
                <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card">
                    <div class="flex items-center gap-2.5 mb-1">
                        <div class="w-9 h-9 rounded-xl bg-mint-soft text-mint flex items-center justify-center">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        </div>
                        <div>
                            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Affiliation</p>
                            <h2 class="text-[14px] font-extrabold text-ink leading-none">Ventes générées par les affiliés</h2>
                        </div>
                    </div>
                    <p class="text-[12px] text-slate-400 mb-5 ml-11">Contactez le client, puis validez la vente pour créditer automatiquement la commission de l'affilié.</p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">
                                    <th class="pb-3">Produit</th>
                                    <th class="pb-3">Client</th>
                                    <th class="pb-3">Affilié</th>
                                    <th class="pb-3">Qté</th>
                                    <th class="pb-3">Commission</th>
                                    <th class="pb-3">Statut</th>
                                    <th class="pb-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-[13px]">
                                <?php if (empty($ventes)): ?>
                                    <tr><td colspan="7" class="text-center py-12">
                                        <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/></svg>
                                        <p class="text-slate-400 text-[12px] font-medium">Aucune vente d'affiliation pour le moment.</p>
                                    </td></tr>
                                <?php else: foreach ($ventes as $v): ?>
                                    <tr class="hover:bg-canvas/60 transition-colors align-top">
                                        <td class="py-3.5 font-bold text-ink"><?= htmlspecialchars($v['produit_nom']) ?></td>
                                        <td class="py-3.5 text-[11px] text-slate-500">
                                            <p class="font-bold text-ink"><?= htmlspecialchars($v['nom_client'] ?? '—') ?></p>
                                            <p><?= htmlspecialchars($v['telephone_client'] ?? '—') ?></p>
                                            <p class="max-w-[160px] truncate"><?= htmlspecialchars($v['adresse_client'] ?? '—') ?></p>
                                            <?php if (!empty($v['telephone_client'])):
                                                // Nettoie le numéro (garde uniquement les chiffres) et ajoute
                                                // l'indicatif Comores (269) s'il n'est pas déjà présent
                                                $tel_nettoye = preg_replace('/\D/', '', $v['telephone_client']);
                                                if (substr($tel_nettoye, 0, 3) !== '269') {
                                                    $tel_whatsapp = '269' . $tel_nettoye;
                                                } else {
                                                    $tel_whatsapp = $tel_nettoye;
                                                }
                                            ?>
                                                <a href="https://wa.me/<?= $tel_whatsapp ?>" target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-1 mt-1.5 bg-emerald-50 text-emerald-600 text-[10px] font-bold px-2 py-1 rounded-full hover:bg-emerald-500 hover:text-white transition-colors">
                                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2Zm5.8 14.14c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.13.11-1.82-.12-.42-.14-.96-.32-1.65-.62-2.9-1.25-4.79-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3 0-1.42.75-2.12 1.01-2.41.26-.29.58-.36.77-.36.19 0 .39 0 .55.01.18.01.41-.07.64.49.24.58.81 2 .88 2.14.07.15.12.32.02.51-.1.19-.15.31-.3.48-.15.17-.31.37-.44.5-.15.15-.3.31-.13.6.17.29.75 1.24 1.62 2.01 1.11.99 2.05 1.3 2.34 1.45.29.15.46.12.63-.07.17-.19.72-.84.91-1.13.19-.29.38-.24.63-.14.26.1 1.65.78 1.93.92.29.15.48.22.55.34.07.13.07.72-.17 1.4Z"/></svg>
                                                    WhatsApp
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 text-slate-600"><?= htmlspecialchars($v['vendeur_nom']) ?></td>
                                        <td class="py-3.5 text-slate-600"><?= (int) $v['quantite'] ?></td>
                                        <td class="py-3.5 font-extrabold text-mint"><?= number_format((float) $v['commission_earn'], 0, ',', ' ') ?> KMF</td>
                                        <td class="py-3.5">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $couleurs_statut[$v['statut']] ?? '' ?>">
                                                <?= htmlspecialchars($libelles_statut[$v['statut']] ?? $v['statut']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5">
                                            <form action="" method="POST" class="flex items-center gap-1.5 mb-1.5">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="vente_id" value="<?= (int) $v['id'] ?>">
                                                <select name="statut" class="bg-canvas border border-slate-200 rounded-lg px-2 py-1.5 text-[11px] focus:outline-none focus:border-primary">
                                                    <?php foreach ($libelles_statut as $valeur => $libelle): ?>
                                                        <option value="<?= $valeur ?>" <?= $v['statut'] === $valeur ? 'selected' : '' ?>><?= $libelle ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="action_update_statut_vente" class="bg-primary hover:bg-primary-dark text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors shrink-0">
                                                    OK
                                                </button>
                                            </form>
                                            <?php if ($v['statut'] === 'colis_recu' && (int) $v['commission_creditee'] === 0): ?>
                                                <form action="" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="vente_id" value="<?= (int) $v['id'] ?>">
                                                    <button type="submit" name="action_envoyer_commission"
                                                            onclick="return confirm('Envoyer la commission de <?= number_format((float) $v['commission_earn'], 0, ',', ' ') ?> KMF à <?= htmlspecialchars($v['vendeur_nom']) ?> ?');"
                                                            class="w-full bg-mint hover:opacity-90 text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-opacity">
                                                        💸 Envoyer la commission
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section