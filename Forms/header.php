<header class="sticky top-0 z-30 border-b border-line bg-surface">
  <div class="conteneur flex h-16 items-center justify-between gap-3">
    <a href="/" class="flex min-h-[44px] min-w-[44px] shrink-0 items-center gap-2" aria-label="MonRevenu, accueil">
      <img src="/assets/img/svg/monrevenu-marque.svg" alt="" width="32" height="32" class="h-8 w-8">
      <span class="hidden text-lg font-semibold text-primary-ink min-[400px]:inline">MonRevenu</span>
    </a>
    <nav class="hidden items-center gap-1 lg:flex" aria-label="Sections de la page">
      <a class="btn btn-sm btn-discret text-text-2 hover:text-primary-ink" href="#fonctionnement">Fonctionnement</a>
      <a class="btn btn-sm btn-discret text-text-2 hover:text-primary-ink" href="#remuneration">Rémunération</a>
      <a class="btn btn-sm btn-discret text-text-2 hover:text-primary-ink" href="#retraits">Retraits</a>
      <a class="btn btn-sm btn-discret text-text-2 hover:text-primary-ink" href="#questions">Questions</a>
    </nav>
    <div class="flex items-center gap-2">
      <nav class="segments" aria-label="Marché">
        <?php foreach (marches() as $codeMarcheEntete => $configMarcheEntete): ?>
          <a class="segment" href="?marche=<?= e($codeMarcheEntete) ?>"<?= ($marche_visiteur ?? MARCHE_DEFAUT) === $codeMarcheEntete ? ' aria-current="true"' : '' ?>><?= e($codeMarcheEntete) ?></a>
        <?php endforeach; ?>
      </nav>
      <button type="button" class="btn btn-sm btn-discret" data-ouvrir="modal-login">Connexion</button>
      <button type="button" class="btn btn-sm btn-secondaire lg:btn-primaire lg:border-transparent" data-ouvrir="modal-register">Créer un compte</button>
    </div>
  </div>
</header>
