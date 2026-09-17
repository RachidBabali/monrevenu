            <dialog id="modal-edition-produit" class="rounded-[24px] p-0 backdrop:bg-ink/40 w-[92vw] max-w-md">
                <form action="" method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="produit_id" id="edit-produit-id">

                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-[15px] font-extrabold text-ink">Modifier le produit</h3>
                        <button type="button" onclick="document.getElementById('modal-edition-produit').close()" class="w-7 h-7 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100" aria-label="Fermer">✕</button>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Nom du produit *</label>
                        <input type="text" name="nom_edit" id="edit-produit-nom" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Description</label>
                        <textarea name="description_edit" id="edit-produit-description" rows="3" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Prix (KMF) *</label>
                            <input type="number" step="0.01" name="prix_edit" id="edit-produit-prix" required class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1.5">Commission (%)</label>
                            <input type="number" name="commission_pourcentage_edit" id="edit-produit-commission" min="0" max="100" class="w-full bg-canvas border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-shadow">
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400">Pour changer l'image, supprimez le produit et recréez-le avec la nouvelle photo.</p>

                    <button type="submit" name="action_edit_produit" class="w-full bg-primary hover:bg-primary-dark text-white text-[13px] font-bold py-3 rounded-xl transition-colors shadow-sm">
                        Enregistrer les modifications
                    </button>
                </form>
            </dialog>