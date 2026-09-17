            <section id="tab-produits" class="tab-content block">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card h-fit">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14"/></svg>
                            </div>
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Catalogue</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Mettre un produit en vente</h2>
                            </div>
                        </div>
                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Nom du produit *</label>
                                <input type="text" name="nom" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Image du produit</label>
                                <input type="file" name="image_produit" accept=".jpg,.jpeg,.png,.webp" class="w-full bg-canvas border border-slate-200 rounded-xl px-3 py-2 text-[12px] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-[11px] file:font-bold focus:outline-none">
                                <p class="text-[10px] text-slate-400 mt-1">JPG, PNG, WEBP · 2 Mo max</p>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Description</label>
                                <textarea name="description" rows="3" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Prix (KMF) *</label>
                                    <input type="number" step="0.01" name="prix" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Commission (%)</label>
                                    <input type="number" name="commission_pourcentage" value="10" min="0" max="100" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                </div>
                            </div>

                            <button type="submit" name="action_produit" class="w-full bg-primary hover:bg-primary-dark text-white text-[13px] font-bold py-3 rounded-xl transition-colors shadow-sm">
                                Publier le produit
                            </button>
                        </form>
                    </div>

                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card lg:col-span-2">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Vue d'ensemble</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Catalogue des produits récents</h2>
                            </div>
                            <span class="text-[11px] font-bold text-slate-400"><?= $nb_produits ?> produit<?= $nb_produits > 1 ? 's' : '' ?></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">
                                        <th class="pb-3">Produit</th>
                                        <th class="pb-3">Prix</th>
                                        <th class="pb-3 text-center">Commission</th>
                                        <th class="pb-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-[13px]">
                                    <?php if(empty($produits)): ?>
                                        <tr><td colspan="4" class="text-center py-12">
                                            <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
                                            <p class="text-slate-400 text-[12px] font-medium">Aucun produit publié pour l'instant.</p>
                                            <p class="text-slate-300 text-[11px] mt-0.5">Utilisez le formulaire à gauche pour ajouter le premier.</p>
                                        </td></tr>
                                    <?php else: foreach($produits as $p): ?>
                                        <tr class="hover:bg-canvas/60 transition-colors">
                                            <td class="py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['nom_produit']) ?>" class="w-10 h-10 rounded-xl object-cover border border-slate-100 shrink-0">
                                                    <div class="min-w-0">
                                                        <p class="font-bold text-ink truncate"><?= htmlspecialchars($p['nom_produit']) ?></p>
                                                        <p class="text-[10px] text-slate-400 font-mono">#<?= $p['id'] ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3 font-extrabold text-ink"><?= number_format($p['prix_vente'], 0, ',', ' ') ?> KMF</td>
                                            <td class="py-3 text-center">
                                                <span class="bg-primary-soft text-primary text-[11px] font-bold px-2.5 py-1 rounded-full"><?= $p['commission_pct'] ?>%</span>
                                            </td>
                                            <td class="py-3">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <button type="button"
                                                            onclick="ouvrirEditionProduit(<?= (int) $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nom_produit']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($p['description']), ENT_QUOTES) ?>', <?= (float) $p['prix_vente'] ?>, <?= (int) $p['commission_pct'] ?>)"
                                                            class="bg-primary-soft text-primary hover:bg-primary hover:text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors">
                                                        Modifier
                                                    </button>
                                                    <form action="" method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="produit_id" value="<?= (int) $p['id'] ?>">
                                                        <button type="submit" name="action_delete_produit"
                                                                onclick="return confirm('Supprimer le produit « <?= htmlspecialchars(addslashes($p['nom_produit'])) ?> » ?');"
                                                                class="bg-red-50 hover:bg-red-500 text-red-500 hover:text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors">
                                                            Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>