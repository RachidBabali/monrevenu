<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/geoip.php';
enregistrerVisitePays($pdo, $_SESSION['user_id'] ?? null);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';

// Chiffres repris du code : includs/affiliation_helpers.php (commission) et sections/wallet.php (retrait)
$seuil_commission = SEUIL_PRIX_COMMISSION;
$commission_basse = COMMISSION_BASSE;
$commission_haute = COMMISSION_HAUTE;
$minimum_retrait  = 1000;
$numero_whatsapp  = env('WHATSAPP_BUSINESS_DISPLAY_NUMBER', '+221 77 876 48 19');
$numero_wa_me     = preg_replace('/\D/', '', $numero_whatsapp);

$titre_page       = 'MonRevenu';
$description_page = "Gagnez une commission fixe sur chaque vente réalisée avec votre lien d'affiliation MonRevenu. Partage par WhatsApp, retrait sur votre compte mobile money.";
$page_publique    = true;
$head_supp        = '<meta name="google-signin-client_id" content="' . e(env('GOOGLE_CLIENT_ID', '')) . '">'
    . '<script src="https://accounts.google.com/gsi/client" async defer></script>';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/head.php';
?>
<body class="bg-surface">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<?php include 'Forms/header.php'; ?>

<main id="contenu">
  <?php include 'Forms/hero.php'; ?>
  <?php include 'Forms/fonctionnement.php'; ?>
  <?php include 'Forms/remuneration.php'; ?>
  <?php include 'Forms/confiance.php'; ?>
  <?php include 'Forms/faq.php'; ?>
</main>

<?php include 'Forms/footer.php'; ?>

<?php include 'Forms/login.php'; ?>
<?php include 'Forms/register.php'; ?>

<div id="toasts" class="toasts" role="status" aria-live="polite"></div>
<script src="<?= e(actif('/assets/js/app-shell.js')) ?>"></script>
<script src="<?= e(actif('/assets/js/public.js')) ?>"></script>
</body>
</html>
