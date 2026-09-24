<section id="tab-utilisateurs" class="tab-content hidden" aria-labelledby="t-utilisateurs">
  <div class="carte overflow-hidden">
    <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 id="t-utilisateurs" class="carte-titre">Utilisateurs</h2>
        <p class="meta mt-1"><?= (int) $nb_utilisateurs ?> au total. Bloquer empêche la connexion sans effacer l'historique ; supprimer désactive le compte définitivement (l'historique financier est conservé).</p>
      </div>
      <div class="relative sm:w-72">
        <label class="sr-only" for="recherche-utilisateurs">Rechercher un utilisateur</label>
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-3"><?= ico('search', 'ico-16') ?></span>
        <input type="search" id="recherche-utilisateurs" class="champ-saisie h-10 pl-9 text-sm lg:h-10" placeholder="Nom, email ou téléphone" autocomplete="off">
      </div>
    </div>
    <div class="max-h-[70vh] overflow-auto">
      <table class="tableau tableau-empile">
        <thead><tr><th scope="col">Utilisateur</th><th scope="col">Contact</th><th scope="col">Rôle</th><th scope="col" class="col-montant">Solde</th><th scope="col">Statut</th><th scope="col">Inscrit le</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody id="table-utilisateurs">
          <?php if (empty($tous_utilisateurs)): ?>
            <tr><td colspan="7"><div class="vide"><?= ico('users', 'ico-40') ?><p class="vide-titre">Aucun utilisateur</p></div></td></tr>
          <?php else: foreach ($tous_utilisateurs as $u):
            $est_soi_meme = (int) $u['id'] === (int) $admin['id'];
            $statut_u = $u['status'] ?? 'active';
            $texte_recherche = mb_strtolower($u['fullname'] . ' ' . $u['email'] . ' ' . $u['phone']);
            $whatsapp_numero = numeroWhatsapp($u['phone'] ?? '');
          ?>
            <tr data-recherche="<?= e($texte_recherche) ?>">
              <td data-label="">
                <span class="flex items-center gap-3">
                  <span class="avatar h-8 w-8 text-xs"><?= e(initiales($u['fullname'])) ?></span>
                  <span class="min-w-0">
                    <span class="block truncate font-medium"><?= e($u['fullname']) ?><?= $est_soi_meme ? ' <span class="text-xs font-normal text-text-3">(vous)</span>' : '' ?></span>
                    <span class="block font-mono text-xs text-text-3">#<?= (int) $u['id'] ?></span>
                  </span>
                </span>
              </td>
              <td data-label="Contact" class="text-text-2"><span class="block max-w-[200px] truncate"><?= e($u['email']) ?></span><span class="chiffres block"><?= e($u['phone']) ?></span><?php if (($u['geo_score'] ?? '') === 'FAIBLE'): ?><span class="pastille pastille-attente" title="<?= e((string) ($u['geo_raisons'] ?? '')) ?>">Pays à revoir</span><?php endif; ?></td>
              <td data-label="Rôle"><span class="pastille pastille-neutre"><?= e(['affilie' => 'Affilié', 'agent' => 'Agent', 'admin' => 'Admin', 'client' => 'Client'][$u['role']] ?? $u['role']) ?></span></td>
              <td data-label="Solde" class="col-montant"><?= montant($u['balance'], false, '', $u['marche']) ?></td>
              <td data-label="Statut"><?= badgeStatut($statut_u, 'compte') ?></td>
              <td data-label="Inscrit le" class="chiffres text-text-2"><?= e(dateFr($u['created_at'], 'court')) ?></td>
              <td data-label="">
                <?php if ($est_soi_meme): ?>
                  <span class="meta">Votre compte</span>
                <?php elseif ($statut_u === 'deleted'): ?>
                  <span class="meta">Compte supprimé</span>
                <?php else: ?>
                  <span class="flex items-center justify-end gap-1.5">
                    <?php if ($whatsapp_numero): ?>
                      <a href="https://wa.me/<?= e($whatsapp_numero) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-icone btn-secondaire" aria-label="Écrire à <?= e($u['fullname']) ?> sur WhatsApp" title="WhatsApp"><?= ico('whatsapp', 'ico-16') ?></a>
                    <?php endif; ?>
                    <form action="" method="POST">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                      <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                      <button type="submit" name="action_toggle_user_status" class="btn btn-sm btn-secondaire"
                              onclick="return confirm('<?= $statut_u === 'suspended' ? 'Débloquer' : 'Bloquer' ?> le compte de <?= e(addslashes($u['fullname'])) ?> ?');"
                              aria-label="<?= $statut_u === 'suspended' ? 'Débloquer' : 'Bloquer' ?> <?= e($u['fullname']) ?>"><?= $statut_u === 'suspended' ? 'Débloquer' : 'Bloquer' ?></button>
                    </form>
                    <form action="" method="POST">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                      <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                      <button type="submit" name="action_delete_user" class="btn btn-sm btn-discret text-danger hover:bg-danger-soft"
                              onclick="return confirm('Supprimer définitivement le compte de <?= e(addslashes($u['fullname'])) ?> ? Le compte ne pourra plus se connecter.');"
                              aria-label="Supprimer <?= e($u['fullname']) ?>">Supprimer</button>
                    </form>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
