<section id="tab-utilisateurs" class="tab-content hidden">
                <div class="bg-white border border-slate-100 rounded-[24px] p-6 shadow-card">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-1">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <div>
                                <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Annuaire</p>
                                <h2 class="text-[14px] font-extrabold text-ink leading-none">Tous les utilisateurs</h2>
                            </div>
                        </div>
                        <input type="text" id="recherche-utilisateurs" placeholder="Rechercher un nom, email, téléphone…"
                               class="w-full sm:w-72 bg-canvas border border-slate-200 rounded-xl px-3.5 py-2 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                    </div>
                    <p class="text-[12px] text-slate-400 mb-5 ml-11"><?= $nb_utilisateurs ?> utilisateur<?= $nb_utilisateurs > 1 ? 's' : '' ?> au total. Bloquer empêche la connexion sans effacer l'historique ; supprimer désactive le compte de façon permanente (l'historique financier est conservé pour la comptabilité).</p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">
                                    <th class="pb-3">Utilisateur</th>
                                    <th class="pb-3">Contact</th>
                                    <th class="pb-3">Rôle</th>
                                    <th class="pb-3">Solde</th>
                                    <th class="pb-3">Statut</th>
                                    <th class="pb-3">Inscrit le</th>
                                    <th class="pb-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-[13px]" id="table-utilisateurs">
                                <?php if (empty($tous_utilisateurs)): ?>
                                    <tr><td colspan="7" class="text-center py-12">
                                        <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                        <p class="text-slate-400 text-[12px] font-medium">Aucun utilisateur enregistré.</p>
                                    </td></tr>
                                <?php else: foreach ($tous_utilisateurs as $u):
                                    $est_soi_meme = (int) $u['id'] === (int) $admin['id'];
                                    $statut_u = $u['status'] ?? 'active';
                                    $texte_recherche = mb_strtolower($u['fullname'] . ' ' . $u['email'] . ' ' . $u['phone']);

                                    // Numéro nettoyé pour le lien WhatsApp (wa.me n'accepte que des chiffres, sans + ni espaces)
                                    $whatsapp_numero = preg_replace('/\D/', '', $u['phone'] ?? '');
                                ?>
                                    <tr class="hover:bg-canvas/60 transition-colors" data-recherche="<?= htmlspecialchars($texte_recherche, ENT_QUOTES) ?>">
                                        <td class="py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-full bg-primary-soft text-primary flex items-center justify-center text-[11px] font-extrabold shrink-0">
                                                    <?= strtoupper(mb_substr($u['fullname'], 0, 1)) ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-bold text-ink truncate max-w-[160px]"><?= htmlspecialchars($u['fullname']) ?><?= $est_soi_meme ? ' <span class="text-[10px] text-primary font-bold">(vous)</span>' : '' ?></p>
                                                    <p class="text-[10px] text-slate-400 font-mono">#<?= $u['id'] ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 text-[11px] text-slate-500">
                                            <p class="truncate max-w-[170px]"><?= htmlspecialchars($u['email']) ?></p>
                                            <p><?= htmlspecialchars($u['phone']) ?></p>
                                        </td>
                                        <td class="py-3">
                                            <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase"><?= htmlspecialchars($u['role']) ?></span>
                                        </td>
                                        <td class="py-3 font-extrabold text-ink"><?= number_format((float) $u['balance'], 0, ',', ' ') ?> KMF</td>
                                        <td class="py-3">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $couleurs_statut_user[$statut_u] ?? '' ?>">
                                                <?= htmlspecialchars($libelles_statut_user[$statut_u] ?? $statut_u) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-[11px] text-slate-400"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                        <td class="py-3">
                                            <?php if ($est_soi_meme): ?>
                                                <p class="text-center text-[11px] text-slate-300">—</p>
                                            <?php elseif ($statut_u === 'deleted'): ?>
                                                <p class="text-center text-[11px] text-slate-400">Compte supprimé</p>
                                            <?php else: ?>
                                                <div class="flex items-center justify-center gap-1.5">

                                                    <?php if ($whatsapp_numero): ?>
                                                    <a href="https://wa.me/<?= htmlspecialchars($whatsapp_numero, ENT_QUOTES) ?>"
                                                       target="_blank" rel="noopener noreferrer"
                                                       title="Contacter <?= htmlspecialchars($u['fullname'], ENT_QUOTES) ?> sur WhatsApp"
                                                       class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-colors">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.42-1.36a9.9 9.9 0 0 0 4.62 1.14h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm0 18.06h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.83.83-3.05-.2-.31a8.16 8.16 0 0 1-1.26-4.36c0-4.52 3.68-8.2 8.21-8.2 2.19 0 4.25.86 5.8 2.4a8.15 8.15 0 0 1 2.4 5.8c0 4.53-3.68 8.22-8.16 8.22Zm4.5-6.15c-.25-.12-1.46-.72-1.68-.8-.23-.08-.39-.12-.56.12-.16.25-.64.8-.78.96-.14.16-.29.18-.53.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.48c-.16 0-.42.06-.64.31-.22.25-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.7 2.6 4.13 3.64.58.25 1.03.4 1.38.51.58.18 1.11.16 1.53.1.47-.07 1.46-.6 1.66-1.17.21-.58.21-1.08.14-1.17-.06-.1-.22-.16-.47-.28Z"/>
                                                        </svg>
                                                    </a>
                                                    <?php endif; ?>

                                                    <form action="" method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                                                        <button type="submit" name="action_toggle_user_status"
                                                                onclick="return confirm('<?= $statut_u === 'suspended' ? 'Débloquer' : 'Bloquer' ?> le compte de <?= htmlspecialchars(addslashes($u['fullname'])) ?> ?');"
                                                                class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors <?= $statut_u === 'suspended' ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white' : 'bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white' ?>">
                                                            <?= $statut_u === 'suspended' ? 'Débloquer' : 'Bloquer' ?>
                                                        </button>
                                                    </form>
                                                    <form action="" method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                                                        <button type="submit" name="action_delete_user"
                                                                onclick="return confirm('Supprimer définitivement le compte de <?= htmlspecialchars(addslashes($u['fullname'])) ?> ? Cette action est irréversible (le compte ne pourra plus se connecter).');"
                                                                class="bg-red-50 hover:bg-red-500 text-red-500 hover:text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg transition-colors">
                                                            Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>