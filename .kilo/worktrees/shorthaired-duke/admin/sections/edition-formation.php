            <dialog id="modal-edition-formation" class="rounded-[24px] p-0 backdrop:bg-ink/40 w-[92vw] max-w-md">
                <form action="" method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="formation_id" id="edit-formation-id">

                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-[15px] font-extrabold text-ink">Modifier la formation</h3>
                        <button type="button" onclick="document.getElementById('modal-edition-formation').close()" class="w-7 h-7 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100" aria-label="Fermer">✕</button>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Titre *</label>
                        <input type="text" name="titre_formation_edit" id="edit-formation-titre" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Lien YouTube *</label>
                        <input type="url" name="url_youtube_edit" id="edit-formation-url" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Résumé & objectifs</label>
                        <textarea name="description_formation_edit" id="edit-formation-description" rows="3" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow"></textarea>
                    </div>

                    <button type="submit" name="action_edit_formation" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white text-[13px] font-bold py-3 rounded-xl transition-colors shadow-sm">
                        Enregistrer les modifications
                    </button>
                </form>
            </dialog>