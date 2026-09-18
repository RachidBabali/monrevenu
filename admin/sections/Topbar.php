<header class="entete-app">
  <div class="conteneur flex h-14 max-w-[1400px] items-center justify-between gap-3">
    <div class="flex min-w-0 items-center gap-2">
      <img src="/assets/img/logo-64.png" alt="" width="28" height="28" class="h-7 w-7 lg:hidden">
      <h1 class="truncate text-lg font-semibold">Administration</h1>
    </div>
    <div class="flex items-center gap-1">
      <a class="btn btn-sm btn-discret" href="/dashboard.php"><?= ico('house', 'ico-16') ?><span class="hidden sm:inline">Espace affilié</span></a>
      <a href="/logout.php?logout=1" class="btn btn-sm btn-icone btn-discret lg:hidden" aria-label="Se déconnecter"><?= ico('log-out', 'ico-16') ?></a>
    </div>
  </div>
</header>
