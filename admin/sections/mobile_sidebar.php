<nav class="sticky top-14 z-10 border-b border-line bg-surface lg:hidden" aria-label="Sections de l'administration">
  <div class="onglets conteneur border-b-0">
    <?php foreach ($onglets_admin as $liens): foreach ($liens as [$tab, $libelle]): ?>
      <button type="button" data-tab="<?= e($tab) ?>" class="mobile-tab-btn onglet"<?= $tab === 'tab-produits' ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></button>
    <?php endforeach; endforeach; ?>
  </div>
</nav>
