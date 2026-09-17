            <section id="tab-publicites" class="tab-content hidden">

                <!-- Statistiques -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white border border-slate-100 rounded-[20px] p-5 shadow-card">
                        <p class="text-[11px] font-bold text-slate-400 mb-1">Publicités actives</p>
                        <p class="text-[22px] font-extrabold text-ink"><?= count(array_filter($publicites, fn($p) => (int)$p['actif'] === 1)) ?> / <?= count($publicites) ?></p>
                    </div>
                    <div class="bg-white border border-slate-100 rounded-[20px] p-5 shadow-card">
                        <p class="text-[11px] font-bold text-slate-400 mb-1">Vues aujourd'hui</p>
                        <p class="text-[22px] font-extrabold text-ink"><?= $vues_aujourdhui ?></p>
                    </div>
                    <div class="bg-white border border-slate-100 rounded-[20px] p-5 shadow-card">
                        <p class="text-[11px] font-bold text-slate-400 mb-1">Total versé (pubs + CPA)</p>
                        <p class="text-[22px] font-extrabold text-mint"><?= number_format($total_verse_pubs + $total_verse_cpa, 0, ',', ' ') ?> KMF</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card h-fit">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                            </div>
                            <h2 class="text-[14px] font-extrabold text-ink">Créer une publicité</h2>
                        </div>
                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Titre *</label>
                                <input type="text" name="titre_pub" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Vidéo de la publicité *</label>
                                <input type="file" name="video_pub" accept=".mp4,.webm,.mov" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3 py-2 text-[12px] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-[11px] file:font-bold focus:outline-none">
                                <p class="text-[10px] text-slate-400 mt-1">MP4, WEBM ou MOV · 100 Mo max</p>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Miniature (optionnelle)</label>
                                <input type="file" name="image_pub" accept=".jpg,.jpeg,.png,.webp" class="w-full bg-canvas border border-slate-200 rounded-xl px-3 py-2 text-[12px] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-[11px] file:font-bold focus:outline-none">
                                <p class="text-[10px] text-slate-400 mt-1">JPG, PNG, WEBP · 2 Mo max</p>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Description</label>
                                <textarea name="description_pub" rows="2" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow"></textarea>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Lien annonceur *</label>
                                <input type="url" name="lien_annonceur" placeholder="https://..." required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Gain (KMF) *</label>
                                    <input type="number" step="0.01" name="montant_gain" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Durée (sec) *</label>
                                    <input type="number" name="duree_secondes" required min="1" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                </div>
                            </div>

                            <label class="flex items-center gap-2 text-[12px] font-semibold text-slate-600">
                                <input type="checkbox" name="actif_pub" checked class="rounded border-slate-300 text-primary focus:ring-primary/30">
                                Publicité active immédiatement
                            </label>

                            <button type="submit" name="action_publicite" class="w-full bg-primary hover:bg-primary-dark text-white text-[13px] font-bold py-3 rounded-xl transition-colors shadow-sm">
                                Créer la publicité
                            </button>
                        </form>
                    </div>

                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card lg:col-span-2">
                        <div class="flex items-center justify-between mb-5">
                            <h2 class="text-[14px] font-extrabold text-ink">Publicités existantes</h2>
                            <span class="text-[11px] font-bold text-slate-400"><?= count($publicites) ?> pub<?= count($publicites) > 1 ? 's' : '' ?></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">
                                        <th class="pb-3">Publicité</th>
                                        <th class="pb-3">Gain</th>
                                        <th class="pb-3">Durée</th>
                                        <th class="pb-3">Vues</th>
                                        <th class="pb-3">Statut</th>
                                        <th class="pb-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-[13px]">
                                    <?php if (empty($publicites)): ?>
                                        <tr><td colspan="6" class="text-center py-12">
                                            <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                                            <p class="text-slate-400 text-[12px] font-medium">Aucune publicité créée pour le moment.</p>
                                        </td></tr>
                                    <?php else: foreach ($publicites as $pub):
                                        $vues_pub = $vues_par_pub[$pub['id']] ?? ['total' => 0, 'aujourdhui' => 0];
                                    ?>
                                        <tr class="hover:bg-canvas/60 transition-colors align-top">
                                            <td class="py-3.5">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?= htmlspecialchars($pub['image']) ?>" alt="" class="w-10 h-10 rounded-xl object-cover border border-slate-100 shrink-0">
                                                    <div class="min-w-0">
                                                        <p class="font-bold text-ink truncate max-w-[160px]"><?= htmlspecialchars($pub['titre']) ?></p>
                                                        <p class="text-[10px] text-slate-400 font-mono">#<?= $pub['id'] ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3.5">
                                                <form action="" method="POST" class="flex items-center gap-1">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="pub_id" value="<?= (int) $pub['id'] ?>">
                                                    <input type="number" step="0.01" name="nouveau_montant" value="<?= $pub['montant_gain'] ?>" class="w-20 bg-canvas border border-slate-200 rounded-lg px-2 py-1 text-[11px] focus:outline-none focus:border-primary">
                                                    <button type="submit" name="action_update_montant_pub" class="bg-primary-soft text-primary text-[10px] font-bold px-2 py-1 rounded-lg hover:bg-primary hover:text-white transition-colors">OK</button>
                                                </form>
                                            </td>
                                            <td class="py-3.5 text-slate-600"><?= (int) $pub['duree_secondes'] ?>s</td>
                                            <td class="py-3.5">
                                                <p class="font-extrabold text-ink"><?= $vues_pub['total'] ?></p>
                                                <p class="text-[10px] text-slate-400">dont <?= $vues_pub['aujourdhui'] ?> aujourd'hui</p>
                                            </td>
                                            <td class="py-3.5">
                                                <?php if ((int) $pub['actif'] === 1): ?>
                                                    <span class="bg-emerald-50 text-emerald-600 border border-emerald-200 px-2.5 py-1 rounded-full text-[10px] font-bold">Active</span>
                                                <?php else: ?>
                                                    <span class="bg-slate-100 text-slate-500 border border-slate-200 px-2.5 py-1 rounded-full text-[10px] font-bold">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3.5 text-center">
                                                <form action="" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="pub_id" value="<?= (int) $pub['id'] ?>">
                                                    <button type="submit" name="action_toggle_publicite" class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors <?= (int) $pub['actif'] === 1 ? 'bg-red-50 text-red-500 hover:bg-red-500 hover:text-white' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white' ?>">
                                                        <?= (int) $pub['actif'] === 1 ? 'Désactiver' : 'Activer' ?>
                                                    </button>
                                                </form>
                                                <form action="" method="POST" class="mt-1.5">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="pub_id" value="<?= (int) $pub['id'] ?>">
                                                    <button type="submit" name="action_delete_publicite"
                                                            onclick="return confirm('Supprimer la publicité « <?= htmlspecialchars(addslashes($pub['titre'])) ?> » ?');"
                                                            class="bg-slate-100 text-slate-500 hover:bg-red-500 hover:text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors">
                                                        Supprimer
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>