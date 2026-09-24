<?php
/**
 * includs/layout_public_debut.php : page publique courte centree (connexion, mot de passe, verification).
 * Variables lues : $titre_page, $largeur_carte (classe max-w-*, facultatif).
 */
$page_publique = true;
include __DIR__ . '/head.php';
?>
<body class="bg-bg">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>
<header class="border-b border-line bg-surface">
  <div class="conteneur flex h-14 items-center">
    <a href="/" class="flex min-h-[44px] items-center gap-2" aria-label="MonRevenu, accueil">
      <img src="/assets/img/svg/monrevenu-marque.svg" alt="" width="28" height="28" class="logo-marque h-7 w-7">
      <span class="font-semibold text-primary-ink">MonRevenu</span>
    </a>
  </div>
</header>
<main id="contenu" class="conteneur flex justify-center py-8 lg:py-16">
  <div class="carte w-full <?= e($largeur_carte ?? 'max-w-md') ?> p-5 sm:p-8">
