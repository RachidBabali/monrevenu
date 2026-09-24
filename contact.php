<?php
/**
 * contact.php : page de contact simple (adresse e-mail et lien WhatsApp Business).
 * Aucun formulaire : les demandes passent par e-mail ou WhatsApp directement.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/env_loader.php';

$numero_whatsapp = env('WHATSAPP_BUSINESS_DISPLAY_NUMBER', '+221 77 876 48 19');
$numero_wa_me    = preg_replace('/\D/', '', $numero_whatsapp);

$titre_page        = 'Contact';
$description_page  = "Contacter MonRevenu par e-mail ou WhatsApp.";
$page_publique     = true;
$page_indexable    = true;
include $_SERVER['DOCUMENT_ROOT'] . '/includs/head.php';
?>
<body class="bg-surface">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>
<header class="border-b border-line">
  <div class="conteneur flex h-14 items-center justify-between gap-3">
    <a href="/" class="flex min-h-[44px] items-center gap-2" aria-label="MonRevenu, accueil">
      <img src="/assets/img/svg/monrevenu-marque.svg" alt="" width="28" height="28" class="logo-marque h-7 w-7">
      <span class="font-semibold text-primary-ink">MonRevenu</span>
    </a>
    <a class="lien cible text-sm" href="/">Retour à l'accueil</a>
  </div>
</header>
<main id="contenu" class="conteneur max-w-[640px] py-10 lg:py-16">
  <h1 class="text-2xl font-semibold lg:text-3xl">Contact</h1>
  <p class="mt-2 text-text-2">Une question sur votre compte, une commande, un paiement ou une boutique ? Écrivez-nous directement.</p>

  <ul class="mt-8 flex flex-col gap-4">
    <li class="carte flex items-center gap-4 p-4">
      <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary-ink"><?= ico('mail', 'ico-20') ?></span>
      <div class="min-w-0">
        <p class="font-medium text-text">E-mail</p>
        <a class="lien cible break-all" href="mailto:contact@monrevenu.xyz">contact@monrevenu.xyz</a>
      </div>
    </li>
    <li class="carte flex items-center gap-4 p-4">
      <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary-ink"><?= ico('whatsapp', 'ico-20') ?></span>
      <div class="min-w-0">
        <p class="font-medium text-text">WhatsApp</p>
        <a class="lien cible" href="https://wa.me/<?= e($numero_wa_me) ?>" target="_blank" rel="noopener"><?= e($numero_whatsapp) ?></a>
      </div>
    </li>
  </ul>

  <p class="mt-8 text-sm text-text-3">Nous répondons du lundi au vendredi. Pour un problème lié à un compte, indiquez votre numéro ou votre adresse e-mail d'inscription.</p>
</main>
<?php include 'Forms/footer.php'; ?>
</body>
</html>
