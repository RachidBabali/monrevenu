<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
  <meta name="theme-color" content="#1e90e8"/>
  <meta name="mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
  <title>MonRevenu – Connexion</title>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>

  <meta name="google-signin-client_id" content="YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com"/>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <link rel="stylesheet" href="css/style.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0d6efd">
</head>
<body>

<div class="page">
  <?php include 'Forms/hero.php'; ?>  
  <?php include 'Forms/login.php'; ?>
</div>

<script src="js/Auth.js"></script>

<script>
  function bloquerNavigation() {
    window.history.pushState(null, "", window.location.href);
  }
  bloquerNavigation();
  window.addEventListener('popstate', function () {
    bloquerNavigation();
  });
</script>

</body>
</html>