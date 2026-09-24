<?php
// Navigation de l'administration : les onglets sont geres par switchTab() (javaScript/script.js).
$onglets_admin = [
    'Pilotage' => [
        ['tab-utilisateurs', 'Utilisateurs', 'users', $nb_utilisateurs],
        ['tab-produits', 'Produits', 'store', null],
        ['tab-ventes', 'Ventes affiliation', 'shopping-cart', $nb_ventes_attente > 0 ? $nb_ventes_attente : null],
    ],
    'Finance' => [
        ['tab-commissions', 'Ajustement de solde', 'hand-coins', null],
        ['tab-retraits', 'Retraits', 'banknote', null],
        ['tab-historique', 'Historique', 'history', null],
    ],
    'Revendeurs' => [
        ['tab-stock-revendeurs', 'Stock revendeurs', 'package', !empty($ventes_stock_en_attente) ? count($ventes_stock_en_attente) : null],
    ],
];
?>
<aside class="barre-laterale" aria-label="Navigation de l'administration">
  <div class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-4">
    <img src="/assets/img/svg/monrevenu-marque.svg" alt="" width="28" height="28" class="h-7 w-7">
    <span class="font-semibold text-primary-ink">MonRevenu</span>
    <span class="pastille pastille-neutre ml-auto">Admin</span>
  </div>
  <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto p-3" id="sidebar-nav">
    <?php foreach ($onglets_admin as $groupe => $liens): ?>
      <p class="nav-groupe"><?= e($groupe) ?></p>
      <?php foreach ($liens as [$tab, $libelle, $icone, $compteur]): ?>
        <button type="button" onclick="switchTab('<?= e($tab) ?>', this)" class="nav-btn nav-lien w-full text-left"<?= $tab === 'tab-produits' ? ' aria-current="page"' : '' ?>>
          <?= ico($icone) ?><span class="flex-1 truncate"><?= e($libelle) ?></span>
          <?php if ($compteur !== null): ?><span class="chiffres rounded-full bg-surface-2 px-1.5 text-xs text-text-2"><?= (int) $compteur ?></span><?php endif; ?>
        </button>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <p class="nav-groupe">Commerçants</p>
    <a class="nav-lien" href="/admin/commercants.php">
      <?= ico('store') ?><span class="flex-1 truncate">Comptes</span>
      <?php if (!empty($nb_commercants_attente)): ?><span class="chiffres rounded-full bg-surface-2 px-1.5 text-xs text-text-2"><?= (int) $nb_commercants_attente ?></span><?php endif; ?>
    </a>
    <a class="nav-lien" href="/admin/audit.php"><?= ico('shield') ?><span class="flex-1 truncate">Audit</span></a>
    <a class="nav-lien" href="/admin/moderation.php">
      <?= ico('badge-check') ?><span class="flex-1 truncate">Produits à valider</span>
      <?php if (!empty($nb_produits_attente)): ?><span class="chiffres rounded-full bg-surface-2 px-1.5 text-xs text-text-2"><?= (int) $nb_produits_attente ?></span><?php endif; ?>
    </a>
    <a class="nav-lien" href="/admin/commissions.php"><?= ico('percent') ?><span class="flex-1 truncate">Commissions</span></a>
    <a class="nav-lien" href="/admin/reglages.php"><?= ico('settings') ?><span class="flex-1 truncate">Réglages</span></a>
  </nav>
  <div class="border-t border-line p-3">
    <div class="flex items-center gap-3 px-1 pb-2">
      <span class="avatar h-8 w-8 text-xs"><?= e($admin_initiales) ?></span>
      <span class="min-w-0"><span class="block truncate text-sm font-medium"><?= e($admin['fullname'] ?? 'Admin') ?></span><span class="block text-xs text-text-3">Administrateur</span></span>
    </div>
    <div class="flex gap-1">
      <button class="btn btn-sm btn-discret flex-1 justify-start" type="button" data-action="theme" aria-pressed="false"><?= ico('moon', 'ico-16') ?>Mode sombre</button>
      <a href="/logout.php?logout=1" class="btn btn-sm btn-icone btn-discret" aria-label="Se déconnecter" title="Se déconnecter"><?= ico('log-out', 'ico-16') ?></a>
    </div>
  </div>
</aside>
