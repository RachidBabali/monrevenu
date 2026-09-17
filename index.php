<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/geoip.php';
enregistrerVisitePays($pdo, $_SESSION['user_id'] ?? null);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
  <meta name="theme-color" content="#1465e0"/>
  <meta name="mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-capable" content="yes"/>
  <meta name="google-signin-client_id" content="<?= htmlspecialchars(env('GOOGLE_CLIENT_ID', '')) ?>">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
  <title>MonRevenu – Recommandez. Gagnez. Développez vos revenus.</title>

  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>

  <!-- Tailwind CSS (CDN) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'mr-navy':       '#0f2547',
            'mr-navy-soft':  '#5c6c80',
            'mr-blue':       '#1465e0',
            'mr-blue-dark':  '#0d47ad',
            'mr-blue-light': '#3ec6f0',
            'mr-blue-pale':  '#e8f2fe',
            'mr-bg':         '#f7faff',
          },
          fontFamily: {
            sora: ['Sora', 'sans-serif'],
          },
          boxShadow: {
            'mr-card': '0 20px 45px -24px rgba(15,37,71,.18)',
          },
        }
      }
    }
  </script>

  <!-- Google Sign-In SDK (formulaires) -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>

  <link rel="stylesheet" href="css/style.css">
  <link rel="manifest" href="/manifest.json">
</head>
<body class="font-sora text-mr-navy bg-white antialiased">

  <?php include 'Forms/header.php'; ?>
  <?php include 'Forms/hero.php'; ?>
  <?php include 'Forms/avantages.php'; ?>
  <?php include 'Forms/fonctionnalites.php'; ?>
  <?php include 'Forms/fonctionnement.php'; ?>
  <?php include 'Forms/offres.php'; ?>
  <?php include 'Forms/dashboard.php'; ?>
  <?php include 'Forms/confiance.php'; ?>
  <?php include 'Forms/cta.php'; ?>
  <?php include 'Forms/footer.php'; ?>

  <!-- Modales Connexion / Inscription -->
  <?php include 'Forms/login.php'; ?>
  <?php include 'Forms/register.php'; ?>

  <script src="js/app.js"></script>
</body>
</html>