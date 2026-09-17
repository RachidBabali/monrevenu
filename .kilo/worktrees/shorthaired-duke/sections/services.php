<?php
// sections/services.php

$boutiques = [
  ['slug' => 'boutique', 'label' => 'Boutique', 'bg' => 'bg-emerald-50', 'stroke' => '#10B981',
    // Redirige vers le catalogue d'affiliation
   'icon' => '
     <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
     <line x1="3" y1="6" x2="21" y2="6"/>
     <path d="M16 10a4 4 0 0 1-8 0"/>
   '],
];

$categories = [
];


?>

<section id="section-services-unique" class="px-4 py-4">
  <div class="flex items-center justify-between mb-4">
    <h3 class="font-bold text-[15px] text-slate-800 dark:text-white">Nos Services</h3>
  </div>

  <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-3">

    <?php foreach($boutiques as $b): ?>
    <?php
      // Si une URL personnalisée est définie (clé 'url'), on l'utilise.
      // Sinon on garde le comportement par défaut : /services/{slug}.php
      $lien_boutique = $b['url'] ?? ('/services/' . $b['slug'] . '.php');
    ?>
    <a href="<?= htmlspecialchars($lien_boutique) ?>"
       class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700
              rounded-2xl p-3 flex flex-col items-center gap-2
              hover:-translate-y-1 hover:shadow-md active:scale-95
              transition-all duration-200 shadow-sm cursor-pointer group">
      <div class="w-12 h-12 rounded-xl <?= $b['bg'] ?> flex items-center justify-center
                  group-hover:scale-110 transition-transform duration-200">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"
             stroke="<?= $b['stroke'] ?>" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
          <?= $b['icon'] ?>
        </svg>
      </div>
      <span class="text-[10.5px] font-semibold text-slate-700 dark:text-slate-300 text-center leading-tight">
        <?= $b['label'] ?>
      </span>
    </a>
    <?php endforeach; ?>

    <?php foreach($categories as $cat): ?>
    <?php
      // Même logique que pour les boutiques : URL personnalisée si définie,
      // sinon comportement par défaut /services/{slug}.php
      $lien_categorie = $cat['url'] ?? ('/services/' . $cat['slug'] . '.php');
    ?>
    <a href="<?= htmlspecialchars($lien_categorie) ?>"
       class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700
              rounded-2xl p-3 flex flex-col items-center gap-2
              hover:-translate-y-1 hover:shadow-md active:scale-95
              transition-all duration-200 shadow-sm cursor-pointer group">
      <div class="w-12 h-12 rounded-xl <?= $cat['bg'] ?> flex items-center justify-center
                  group-hover:scale-110 transition-transform duration-200">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"
             stroke="<?= $cat['stroke'] ?>" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
          <?= $cat['icon'] ?>
        </svg>
      </div>
      <span class="text-[10.5px] font-semibold text-slate-700 dark:text-slate-300 text-center leading-tight">
        <?= $cat['label'] ?>
      </span>
    </a>
    <?php endforeach; ?>

   

    <a href="/services/mon-stock.php"
       class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700
              rounded-2xl p-3 flex flex-col items-center gap-2
              hover:-translate-y-1 hover:shadow-md active:scale-95
              transition-all duration-200 shadow-sm cursor-pointer group">
      <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center
                  group-hover:scale-110 transition-transform duration-200">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"
             stroke="#6366F1" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 7 12 3 4 7l8 4 8-4Z"/>
          <path d="M4 7v10l8 4 8-4V7"/>
          <path d="M12 11v10"/>
        </svg>
      </div>
      <span class="text-[10.5px] font-semibold text-slate-700 dark:text-slate-300 text-center leading-tight">
        Mon Stock
      </span>
    </a>

  </div>
</section>