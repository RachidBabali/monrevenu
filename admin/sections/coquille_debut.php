<?php
/**
 * admin/sections/coquille_debut.php : coquille des pages d'administration secondaires
 * (Commercants, Produits a valider, et plus tard Audit). Variables lues :
 *   $titre_page, $page_admin (cle de navigation), $admin, $message, $error, $compteurs_admin.
 * Fermer avec coquille_fin.php.
 */
require_once __DIR__ . '/../../includs/ui.php';

$liens_admin = [
    'dashboard'   => ['/admin/dashboard_admin.php', 'Tableau de bord', 'layout-dashboard', null],
    'commercants' => ['/admin/commercants.php', 'Commerçants', 'store', $compteurs_admin['commercants'] ?? null],
    'moderation'  => ['/admin/moderation.php', 'Produits à valider', 'badge-check', $compteurs_admin['produits'] ?? null],
    'audit'       => ['/admin/audit.php', 'Audit', 'shield', null],
    'reglages'    => ['/admin/reglages.php', 'Réglages', 'settings', null],
];
$titre_page = $titre_page ?? 'Administration';
$head_supp  = '<meta name="robots" content="noindex"><meta name="csrf-token" content="' . e($_SESSION['csrf_token'] ?? '') . '">';
include __DIR__ . '/../../includs/head.php';
?>
<body class="admin">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<aside class="barre-laterale" aria-label="Navigation de l'administration">
  <div class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-4">
    <img src="/assets/img/logo-64.png" alt="" width="28" height="28" class="h-7 w-7">
    <span class="font-semibold text-primary-ink">MonRevenu</span>
    <span class="pastille pastille-neutre ml-auto">Admin</span>
  </div>
  <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto p-3">
    <?php foreach ($liens_admin as $cle => [$url, $libelle, $icone, $compteur]): ?>
      <a class="nav-lien" href="<?= e($url) ?>"<?= ($page_admin ?? '') === $cle ? ' aria-current="page"' : '' ?>>
        <?= ico($icone) ?><span class="flex-1 truncate"><?= e($libelle) ?></span>
        <?php if ($compteur): ?><span class="chiffres rounded-full bg-surface-2 px-1.5 text-xs text-text-2"><?= (int) $compteur ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="border-t border-line p-3">
    <div class="flex items-center gap-3 px-1 pb-2">
      <span class="avatar h-8 w-8 text-xs"><?= e(initiales($admin['fullname'] ?? 'Admin')) ?></span>
      <span class="min-w-0"><span class="block truncate text-sm font-medium"><?= e($admin['fullname'] ?? 'Admin') ?></span><span class="block text-xs text-text-3">Administrateur</span></span>
    </div>
    <div class="flex gap-1">
      <button class="btn btn-sm btn-discret flex-1 justify-start" type="button" data-action="theme" aria-pressed="false"><?= ico('moon', 'ico-16') ?>Mode sombre</button>
      <a href="/logout.php?logout=1" class="btn btn-sm btn-icone btn-discret" aria-label="Se déconnecter" title="Se déconnecter"><?= ico('log-out', 'ico-16') ?></a>
    </div>
  </div>
</aside>

<div class="contenu-app pb-8">
  <header class="entete-app">
    <div class="conteneur flex h-14 max-w-[1400px] items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-2">
        <img src="/assets/img/logo-64.png" alt="" width="28" height="28" class="h-7 w-7 lg:hidden">
        <h1 class="truncate text-lg font-semibold"><?= e($titre_page) ?></h1>
      </div>
      <a class="btn btn-sm btn-discret" href="/admin/dashboard_admin.php"><?= ico('layout-dashboard', 'ico-16') ?><span class="hidden sm:inline">Tableau de bord</span></a>
    </div>
  </header>

  <nav class="sticky top-14 z-10 border-b border-line bg-surface lg:hidden" aria-label="Sections de l'administration">
    <div class="onglets conteneur border-b-0">
      <?php foreach ($liens_admin as $cle => [$url, $libelle]): ?>
        <a class="onglet" href="<?= e($url) ?>"<?= ($page_admin ?? '') === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>

  <main id="contenu" class="conteneur flex max-w-[1400px] flex-col gap-6 py-4 lg:py-6">
    <?php if (!empty($message)): ?>
      <p class="alerte alerte-succes" role="status"><?= ico('circle-check') ?><span><?= e($message) ?></span></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <p class="alerte alerte-danger" role="alert"><?= ico('circle-alert') ?><span><?= e($error) ?></span></p>
    <?php endif; ?>
