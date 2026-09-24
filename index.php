<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/geoip.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/config_marche.php';
enregistrerVisitePays($pdo, $_SESSION['user_id'] ?? null);

// Selecteur de marche du visiteur (SN/KM), memorise par cookie un an : voir G5.
// Redirection sans le parametre pour eviter de le garder dans l'URL partagee.
$marche_demandee = marcheValide($_GET['marche'] ?? null);
if ($marche_demandee !== null) {
    setcookie('marche', $marche_demandee, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
    $sansParametre = preg_replace('/[?&]marche=[^&]*/', '', $_SERVER['REQUEST_URI']);
    header('Location: ' . ($sansParametre !== '' ? $sansParametre : '/'));
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/vitrine_accueil.php';

// Chiffres du marche affiche (includs/config_marche.php) : commission, minimum de retrait, devise.
$marche_visiteur  = marcheCourant();
$config_marche    = marche($marche_visiteur);
$seuil_commission = $config_marche['commission']['seuil'];
$commission_basse = $config_marche['commission']['basse'];
$commission_haute = $config_marche['commission']['haute'];
$minimum_retrait  = $config_marche['retrait_minimum'];
$vitrine          = produitVitrineAccueil($pdo, $marche_visiteur);
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
