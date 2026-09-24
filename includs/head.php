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
// Indexable seulement pour les pages publiques explicitement marquees comme telles (voir chaque
// page) : par defaut une page (compte connecte, administration, flux de verification) n'est pas
// indexee. L'URL canonique pointe toujours vers www, jamais vers l'apex ni une variante ?query.
$page_indexable = $page_indexable ?? false;
$chemin_courant = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$url_canonique  = $url_canonique ?? ('https://www.monrevenu.xyz' . $chemin_courant);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titre_page === 'MonRevenu' ? 'MonRevenu' : $titre_page . ' | MonRevenu') ?></title>
<meta name="description" content="<?= e($description_page) ?>">
<link rel="canonical" href="<?= e($url_canonique) ?>">
<?php if (!$page_indexable): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="theme-color" content="#123F91">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="MonRevenu">
<link rel="manifest" href="/manifest.json">
<?php /* Favicons : voir assets/img/README.md. Jeu clair par defaut, jeu sombre pour les navigateurs en theme sombre. */ ?>
<link rel="icon" href="/assets/img/favicon/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-sombre/favicon-32x32.png" media="(prefers-color-scheme: dark)">
<link rel="apple-touch-icon" href="/assets/img/favicon/apple-touch-icon.png">
<link rel="preload" href="/assets/fonts/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/ibm-plex-sans-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(actif('/assets/css/app.css')) ?>">
<?php if ($page_indexable): ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="MonRevenu">
<meta property="og:title" content="<?= e($titre_page === 'MonRevenu' ? 'MonRevenu' : $titre_page . ' | MonRevenu') ?>">
<meta property="og:description" content="<?= e($description_page) ?>">
<meta property="og:url" content="<?= e($url_canonique) ?>">
<meta property="og:image" content="https://www.monrevenu.xyz/assets/img/jpeg/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<?php if (!$page_publique): ?>
<script src="<?= e(actif('/assets/js/theme.js')) ?>"></script>
<?php endif; ?>
<?= $head_supp ?? '' ?>
</head>
