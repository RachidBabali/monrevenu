<?php
/**
 * includs/head.php : <!DOCTYPE> et <head> communs.
 * Variables lues : $titre_page (obligatoire), $description_page, $page_publique (pas de mode sombre),
 * $head_supp (HTML supplementaire deja echappe par l'appelant).
 */
require_once __DIR__ . '/ui.php';

$titre_page       = $titre_page ?? 'MonRevenu';
$description_page = $description_page ?? "MonRevenu : commissions d'affiliation sur les produits du catalogue, partagés par WhatsApp.";
$page_publique    = $page_publique ?? false;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titre_page === 'MonRevenu' ? 'MonRevenu' : $titre_page . ' | MonRevenu') ?></title>
<meta name="description" content="<?= e($description_page) ?>">
<meta name="theme-color" content="#123F91">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="MonRevenu">
<link rel="manifest" href="/manifest.json">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preload" href="/assets/fonts/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/ibm-plex-sans-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(actif('/assets/css/app.css')) ?>">
<?php if (!$page_publique): ?>
<script src="<?= e(actif('/assets/js/theme.js')) ?>"></script>
<?php endif; ?>
<?= $head_supp ?? '' ?>
</head>
