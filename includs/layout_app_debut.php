<?php
/**
 * includs/layout_app_debut.php : coquille de l'espace connecte (head, navigation, en-tete, messages flash).
 * A inclure apres la logique PHP de la page. Variables lues :
 *   $titre_page (obligatoire), $pdo, $actions_entete (HTML), $message_success, $message_error.
 * Fermer avec includs/layout_app_fin.php.
 */
require_once __DIR__ . '/ui.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_page = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '';

$nav_principale = [
    ['/dashboard.php', 'Accueil', 'house'],
    ['/services/boutique.php', 'Catalogue', 'store'],
    ['/page/portefeuille.php', 'Portefeuille', 'wallet'],
    ['/page/historique.php', 'Historique', 'history'],
    ['/page/messagerie.php', 'Messages', 'message-square'],
    ['/services/mon-stock.php', 'Mon stock', 'package'],
];
$nav_plus = ['/page/historique.php', '/page/messagerie.php', '/services/mon-stock.php'];

$shell_nom   = $_SESSION['user_fullname'] ?? 'Utilisateur';
$shell_roles = ['affilie' => 'Affilié', 'agent' => 'Agent revendeur', 'admin' => 'Administrateur', 'client' => 'Membre'];
$shell_role  = $shell_roles[$_SESSION['user_role'] ?? ''] ?? 'Membre';

$shell_non_lus = 0;
if (isset($pdo) && !empty($_SESSION['user_id'])) {
    try {
        $stmtShell = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND statut = 'non_lu'");
        $stmtShell->execute([(int) $_SESSION['user_id']]);
        $shell_non_lus = (int) $stmtShell->fetchColumn();
    } catch (PDOException $e) {
        $shell_non_lus = 0;
    }
}

$estActif = static fn(string $url): bool => $current_page === $url;

$head_supp = '<meta name="csrf-token" content="' . e($_SESSION['csrf_token']) . '">'
    . '<meta name="vapid-public-key" content="' . e(function_exists('env') ? env('VAPID_PUBLIC_KEY', '') : '') . '">';
include __DIR__ . '/head.php';
?>
<body>
<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<aside class="barre-laterale" aria-label="Navigation principale">
  <a href="/dashboard.php" class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-4">
    <img src="/assets/img/logo-64.png" alt="" width="28" height="28" class="h-7 w-7 rounded">
    <span class="text-base font-semibold text-primary-ink">MonRevenu</span>
  </a>
  <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto p-3">
    <?php foreach ($nav_principale as [$url, $libelle, $icone]): ?>
      <a class="nav-lien" href="<?= e($url) ?>"<?= $estActif($url) ? ' aria-current="page"' : '' ?>>
        <?= ico($icone) ?><?= e($libelle) ?>
        <?php if ($url === '/page/messagerie.php' && $shell_non_lus > 0): ?>
          <span class="ml-auto rounded-full bg-primary px-1.5 text-xs font-medium text-on-primary chiffres"><?= $shell_non_lus > 99 ? '99+' : $shell_non_lus ?><span class="sr-only"> non lus</span></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="border-t border-line p-3">
    <a class="nav-lien" href="/page/profil.php"<?= $estActif('/page/profil.php') ? ' aria-current="page"' : '' ?>>
      <span class="avatar h-7 w-7 text-xs"><?= e(initiales($shell_nom)) ?></span>
      <span class="min-w-0 flex-1"><span class="block truncate text-text"><?= e($shell_nom) ?></span><span class="block text-xs font-normal text-text-3"><?= e($shell_role) ?></span></span>
    </a>
    <div class="mt-1 flex gap-1">
      <button class="btn btn-sm btn-discret flex-1 justify-start" type="button" data-action="theme" aria-pressed="false"><?= ico('moon', 'ico-16') ?>Mode sombre</button>
      <a class="btn btn-sm btn-icone btn-discret" href="/logout.php?logout=1" aria-label="Se déconnecter" title="Se déconnecter"><?= ico('log-out', 'ico-16') ?></a>
    </div>
  </div>
</aside>

<div class="contenu-app">
  <header class="entete-app">
    <div class="conteneur flex h-14 items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-2">
        <img src="/assets/img/logo-64.png" alt="" width="28" height="28" class="h-7 w-7 rounded lg:hidden">
        <h1 class="truncate text-lg font-semibold"><?= e($titre_page) ?></h1>
      </div>
      <div class="flex shrink-0 items-center gap-1">
        <?= $actions_entete ?? '' ?>
        <?php include __DIR__ . '/../sections/notifications_bell.php'; ?>
      </div>
    </div>
  </header>

  <main id="contenu" class="conteneur flex flex-col gap-6 py-4 lg:py-6">
    <?php if (!empty($message_success)): ?>
      <p class="alerte alerte-succes" role="status"><?= ico('circle-check') ?><span><?= e($message_success) ?></span></p>
    <?php endif; ?>
    <?php if (!empty($message_error)): ?>
      <p class="alerte alerte-danger" role="alert"><?= ico('circle-alert') ?><span><?= e($message_error) ?></span></p>
    <?php endif; ?>
