<!-- ============================ SECTION STOCK REVENDEURS ============================ -->
            <section id="tab-stock-revendeurs" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Créer un produit de stock -->
                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card h-fit">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14"/></svg>
                            </div>
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Catalogue de stock</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Créer un produit</h2>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400 mb-4">Ce produit sert uniquement au stock des revendeurs — il n'apparaît pas dans le catalogue affilié public.</p>
                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Nom du produit *</label>
                                <input type="text" name="nom_produit_stock" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Image du produit</label>
                                <input type="file" name="image_produit_stock" accept=".jpg,.jpeg,.png,.webp" class="w-full bg-canvas border border-slate-200 rounded-xl px-3 py-2 text-[12px] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-[11px] file:font-bold focus:outline-none">
                                <p class="text-[10px] text-slate-400 mt-1">JPG, PNG, WEBP · 2 Mo max</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Prix (KMF) *</label>
                                    <input type="number" step="0.01" name="prix_produit_stock" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Commission par unité (KMF)</label>
                                    <input type="number" step="0.01" name="commission_produit_stock" value="500" min="0" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                </div>
                            </div>

                            <button type="submit" name="action_creer_produit_stock" class="w-full bg-primary hover:bg-primary-dark text-white text-[13px] font-bold py-3 rounded-xl transition-colors shadow-sm">
                                Ajouter au catalogue de stock
                            </button>
                        </form>
                    </div>

                    <!-- Attribuer du stock -->
                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card h-fit">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 12 3 4 7l8 4 8-4Z"/><path d="M4 7v10l8 4 8-4V7"/><path d="M12 11v10"/></svg>
                            </div>
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Stock</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Attribuer du stock</h2>
                            </div>
                        </div>

                        <?php if (empty($produits_catalogue_complet)): ?>
                            <p class="text-[12px] text-slate-400 text-center py-6">Crée d'abord un produit de stock (à gauche) avant de pouvoir en attribuer.</p>
                        <?php else: ?>
                            <form action="" method="POST" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Utilisateur *</label>
                                    <select name="stock_user_id" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                        <option value="">-- Choisir un utilisateur --</option>
                                        <?php foreach ($utilisateurs as $u): ?>
                                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= strtoupper($u['role']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Produit *</label>
                                    <select name="stock_produit_id" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                        <option value="">-- Choisir un produit --</option>
                                        <?php foreach ($produits_catalogue_complet as $p): ?>
                                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom_produit']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Quantité à ajouter *</label>
                                    <input type="number" name="stock_quantite" min="1" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                    <p class="text-[10px] text-slate-400 mt-1">S'ajoute au stock existant de l'utilisateur pour ce produit.</p>
                                </div>

                                <button type="submit" name="action_attribuer_stock" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white text-[13px] font-bold py-3 rounded-xl transition-colors shadow-sm">
                                    Attribuer le stock
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Catalogue de stock existant -->
                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card h-fit">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Catalogue</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Produits de stock</h2>
                            </div>
                            <span class="text-[11px] font-bold text-slate-400"><?= count($produits_catalogue_complet) ?></span>
                        </div>
                        <?php if (empty($produits_catalogue_complet)): ?>
                            <p class="text-[12px] text-slate-400 text-center py-6">Aucun produit de stock créé pour le moment.</p>
                        <?php else: ?>
                            <div class="space-y-2 max-h-80 overflow-y-auto">
                                <?php foreach ($produits_catalogue_complet as $p): ?>
                                    <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-canvas/60 transition-colors">
                                        <img src="<?= htmlspecialchars($p['image']) ?>" alt="" class="w-10 h-10 rounded-lg object-cover border border-slate-100 shrink-0">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-ink text-[12.5px] truncate"><?= htmlspecialchars($p['nom_produit']) ?></p>
                                            <p class="text-[10.5px] text-slate-400"><?= number_format((float) $p['prix_vente'], 0, ',', ' ') ?> KMF · <?= number_format((float) $p['commission_fixe'], 0, ',', ' ') ?> KMF/unité</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Vue d'ensemble des stocks -->
                <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card mt-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Vue d'ensemble</p>
                            <h2 class="text-[14px] font-extrabold text-ink leading-none">Stocks attribués</h2>
                        </div>
                        <span class="text-[11px] font-bold text-slate-400"><?= count($stocks_tous_utilisateurs) ?> ligne<?= count($stocks_tous_utilisateurs) > 1 ? 's' : '' ?></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">
                                    <th class="pb-3">Produit</th>
                                    <th class="pb-3">Utilisateur</th>
                                    <th class="pb-3 text-right">Disponible</th>
                                    <th class="pb-3 text-right">Vendu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-[13px]">
                                <?php if (empty($stocks_tous_utilisateurs)): ?>
                                    <tr><td colspan="4" class="text-center py-12">
                                        <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 12 3 4 7l8 4 8-4Z"/><path d="M4 7v10l8 4 8-4V7"/></svg>
                                        <p class="text-slate-400 text-[12px] font-medium">Aucun stock attribué pour le moment.</p>
                                    </td></tr>
                                <?php else: foreach ($stocks_tous_utilisateurs as $s): ?>
                                    <tr class="hover:bg-canvas/60 transition-colors">
                                        <td class="py-3">
                                            <div class="flex items-center gap-3">
                                                <img src="<?= htmlspecialchars($s['image']) ?>" alt="" class="w-9 h-9 rounded-lg object-cover border border-slate-100 shrink-0">
                                                <span class="font-bold text-ink"><?= htmlspecialchars($s['nom_produit']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 text-slate-600"><?= htmlspecialchars($s['fullname']) ?></td>
                                        <td class="py-3 text-right font-extrabold <?= (int) $s['quantite_disponible'] <= 5 ? 'text-amber-500' : 'text-ink' ?>"><?= (int) $s['quantite_disponible'] ?></td>
                                        <td class="py-3 text-right text-slate-500"><?= (int) $s['quantite_vendue'] ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Commissions de ventes en attente d'envoi -->
                <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card mt-6">
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-mint-soft text-mint flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9.5c0-1.5 1.3-2.5 3-2.5s3 1 3 2.5-1.3 2-3 2.5-3 1-3 2.5 1.3 2.5 3 2.5 3-1 3-2.5"/></svg>
                            </div>
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">À traiter</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Commissions de ventes en attente</h2>
                            </div>
                        </div>
                        <span class="text-[11px] font-bold text-slate-400"><?= count($ventes_stock_en_attente) ?> en attente</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">
                                    <th class="pb-3">Référence</th>
                                    <th class="pb-3">Produit</th>
                                    <th class="pb-3">Utilisateur</th>
                                    <th class="pb-3">Montant vente</th>
                                    <th class="pb-3">Commission</th>
                                    <th class="pb-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-[13px]">
                                <?php if (empty($ventes_stock_en_attente)): ?>
                                    <tr><td colspan="6" class="text-center py-12">
                                        <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
                                        <p class="text-slate-400 text-[12px] font-medium">Aucune commission en attente.</p>
                                    </td></tr>
                                <?php else: foreach ($ventes_stock_en_attente as $vs): ?>
                                    <tr class="hover:bg-canvas/60 transition-colors">
                                        <td class="py-3 font-mono text-[11px] text-slate-400"><?= htmlspecialchars($vs['reference']) ?></td>
                                        <td class="py-3">
                                            <div class="flex items-center gap-2.5">
                                                <img src="<?= htmlspecialchars($vs['image']) ?>" alt="" class="w-8 h-8 rounded-lg object-cover border border-slate-100 shrink-0">
                                                <span class="text-slate-600"><?= htmlspecialchars($vs['nom_produit']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 font-bold text-ink"><?= htmlspecialchars($vs['fullname']) ?></td>
                                        <td class="py-3 text-slate-600"><?= number_format((float) $vs['montant_total'], 0, ',', ' ') ?> KMF</td>
                                        <td class="py-3 font-extrabold text-mint"><?= number_format((float) $vs['commission_montant'], 0, ',', ' ') ?> KMF</td>
                                        <td class="py-3 text-center">
                                            <form action="" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="vente_stock_id" value="<?= (int) $vs['id'] ?>">
                                                <button type="submit" name="action_envoyer_commission_stock"
                                                        onclick="return confirm('Envoyer <?= number_format((float) $vs['commission_montant'], 0, ',', ' ') ?> KMF à <?= htmlspecialchars(addslashes($vs['fullname'])) ?> ?');"
                                                        class="bg-mint hover:opacity-90 text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-opacity">
                                                    💸 Envoyer
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>