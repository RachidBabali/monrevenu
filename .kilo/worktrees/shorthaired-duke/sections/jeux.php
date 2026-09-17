<?php
// sections/section-shop.php
// Section complète : bannière promo + filtres catégories + grille "Popular"

$filtres = ['Tous', 'Nike', 'Adidas', 'Puma'];
$filtre_actif = 'Tous';

// Exemple de données — à remplacer par ta requête PDO
$produits_exemple = [
  ['nom' => 'Ambush Air', 'image' => '/assets/img/kochi.jpg'],
  ['nom' => 'Air Jordan 5', 'image' => '/assets/img/pull.jpg'],

];
?>
<section class="px-4 py-4 bg-[#F8F9FB] dark:bg-[#0B1120] flex flex-col gap-5">

  <!-- ============ BANNIÈRE PROMO ============ -->
  <a href="/services/boutique.php"
     class="relative overflow-hidden bg-gradient-to-r from-[#0D47A1] to-[#1976D2] rounded-2xl shadow-lg flex items-center justify-between p-4 group hover:shadow-xl transition-all min-h-[110px]">

    <div class="absolute -right-6 -bottom-8 w-32 h-32 rounded-full bg-white/5"></div>

    <div class="relative z-10 flex-1 max-w-[60%]">
      <h3 class="text-white font-extrabold text-[16px] leading-snug">
       voire les produit a affilier dans notre application
      </h3>
      <button type="button"
              class="mt-3 bg-white text-[#0D47A1] font-bold text-[11px] px-4 py-2 rounded-full shadow-sm group-hover:scale-105 transition-transform">
        Explore
      </button>
    </div>

    <div class="relative z-10 shrink-0 w-28 h-28 sm:w-32 sm:h-32 -my-2 -mr-1 flex items-end justify-center">
      <img src="/assets/img/shoe-banner.png" alt="Nouvelle collection"
           class="w-full h-full object-contain drop-shadow-2xl">
    </div>
  </a>



</section>

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>