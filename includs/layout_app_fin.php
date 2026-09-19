<?php
/**
 * includs/layout_app_fin.php : ferme la coquille ouverte par layout_app_debut.php
 * (barre d'onglets mobile, feuille "Plus", scripts communs).
 * $scripts_page : liste de chemins de scripts propres a la page.
 */
$plusActif = in_array($current_page, $nav_plus, true);
?>
  </main>
</div>

<nav class="onglets-bas" aria-label="Navigation principale">
  <?php foreach ($nav_bas as [$url, $libelle, $icone]): ?>
    <a class="onglet-bas" href="<?= e($url) ?>"<?= $estActif($url) ? ' aria-current="page"' : '' ?>><?= ico($icone, 'ico-24') ?><?= e($libelle) ?></a>
  <?php endforeach; ?>
  <button class="onglet-bas relative" type="button" data-ouvrir="feuille-plus"<?= $plusActif ? ' aria-current="page"' : '' ?> aria-haspopup="dialog">
    <?= ico('ellipsis', 'ico-24') ?>Plus
    <?php if ($shell_non_lus > 0): ?><span class="absolute right-1/2 top-2 -mr-4 h-2 w-2 rounded-full bg-danger"><span class="sr-only">Messages non lus</span></span><?php endif; ?>
  </button>
</nav>

<dialog class="feuille lg:hidden" id="feuille-plus" aria-labelledby="feuille-plus-titre">
  <div class="poignee"></div>
  <div class="feuille-entete">
    <h2 class="feuille-titre" id="feuille-plus-titre">Plus</h2>
    <button class="btn btn-icone btn-discret" type="button" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
  </div>
  <nav class="liste-nav" aria-label="Autres pages">
    <?php foreach (array_filter($nav_principale, fn($n) => in_array($n[0], $nav_plus, true)) as [$url, $libelle, $icone]): ?>
      <a class="liste-nav-lien" href="<?= e($url) ?>"<?= $estActif($url) ? ' aria-current="page"' : '' ?>>
        <?= ico($icone) ?><?= e($libelle) ?>
        <?php if ($url === '/page/messagerie.php' && $shell_non_lus > 0): ?><span class="pastille pastille-info ml-auto chiffres"><?= $shell_non_lus ?> non lu<?= $shell_non_lus > 1 ? 's' : '' ?></span><?php endif; ?>
        <?= ico('chevron-right', 'ico-16') ?>
      </a>
    <?php endforeach; ?>
    <button class="liste-nav-lien w-full" type="button" data-action="theme" aria-pressed="false"><?= ico('moon') ?>Mode sombre<?= ico('chevron-right', 'ico-16 invisible') ?></button>
    <a class="liste-nav-lien" href="/logout.php?logout=1"><?= ico('log-out') ?>Se déconnecter<?= ico('chevron-right', 'ico-16 invisible') ?></a>
  </nav>
  <div class="h-[env(safe-area-inset-bottom)]"></div>
</dialog>

<div id="toasts" class="toasts" role="status" aria-live="polite"></div>
<script src="<?= e(actif('/assets/js/app-shell.js')) ?>"></script>
<script src="<?= e(actif('/assets/js/notifications.js')) ?>"></script>
<?php foreach ($scripts_page ?? [] as $script): ?>
<script src="<?= e(actif($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
