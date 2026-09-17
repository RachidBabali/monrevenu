            <section id="tab-formations" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card h-fit">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 10-10-5L2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg>
                            </div>
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Académie</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Publier un nouveau module vidéo</h2>
                            </div>
                        </div>
                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Titre de la leçon / vidéo *</label>
                                <input type="text" name="titre_formation" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Lien YouTube *</label>
                                <input type="url" name="url_youtube" placeholder="https://www.youtube.com/watch?v=..." required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                                <p class="text-[10px] text-slate-400 mt-1">Collez simplement l'URL de la vidéo YouTube</p>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Résumé & objectifs d'apprentissage</label>
                                <textarea name="description_formation" rows="4" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow"></textarea>
                            </div>

                            <button type="submit" name="action_formation" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white text-[13px] font-bold py-3 rounded-xl transition-colors shadow-sm">
                                Héberger la formation sur la plateforme
                            </button>
                        </form>
                    </div>

                    <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card lg:col-span-2">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Bibliothèque</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Formations publiées récemment</h2>
                            </div>
                            <span class="text-[11px] font-bold text-slate-400"><?= count($formations) ?> formation<?= count($formations) > 1 ? 's' : '' ?></span>
                        </div>
                        <?php if (empty($formations)): ?>
                            <div class="text-center py-12">
                                <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m22 10-10-5L2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg>
                                <p class="text-slate-400 text-[12px] font-medium">Aucune formation publiée pour le moment.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($formations as $f): ?>
                                    <div class="flex items-center gap-3 p-3 rounded-2xl hover:bg-canvas/60 transition-colors">
                                        <div class="w-16 h-16 rounded-xl overflow-hidden bg-slate-100 shrink-0 flex items-center justify-center">
                                            <?php if (!empty($f['video'])): ?>
                                                <img src="https://img.youtube.com/vi/<?= htmlspecialchars($f['video']) ?>/default.jpg" alt="" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <svg class="w-6 h-6 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 10-10-5L2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-ink text-[13px] truncate"><?= htmlspecialchars($f['titre']) ?></p>
                                            <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($f['description'] ?? '') ?></p>
                                            <p class="text-[10px] text-slate-400 mt-0.5">Publiée le <?= date('d/m/Y', strtotime($f['date_publication'])) ?></p>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button type="button"
                                                    onclick="ouvrirEditionFormation(<?= (int) $f['id'] ?>, '<?= htmlspecialchars(addslashes($f['titre']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($f['description'] ?? ''), ENT_QUOTES) ?>', 'https://youtu.be/<?= htmlspecialchars($f['video'] ?? '') ?>')"
                                                    class="bg-indigo-50 text-indigo-600 hover:bg-indigo-500 hover:text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors">
                                                Modifier
                                            </button>
                                            <form action="" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="formation_id" value="<?= (int) $f['id'] ?>">
                                                <button type="submit" name="action_delete_formation"
                                                        onclick="return confirm('Supprimer la formation « <?= htmlspecialchars(addslashes($f['titre'])) ?> » ?');"
                                                        class="bg-red-50 hover:bg-red-500 text-red-500 hover:text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors">
                                                    Supprimer
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>