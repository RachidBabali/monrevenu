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
  <title>MonRevenu – Inscription</title>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>

  <!-- Google Sign-In SDK (optionnel pour l'inscription sociale) -->
  <meta name="google-signin-client_id" content="YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com"/>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <link rel="stylesheet" href="css/inscription.css">

</head>
<body>

<div class="page">

  <!-- HERO (identique à la maquette) -->
  
    <?php include 'Forms/hero.php'; ?> 
  <?php include 'Forms/register.php'; ?> 
  
</div>
<script src="js/Auth.js"></script>


<!-- (Optionnel) SDK Facebook – à décommenter et configurer avec votre App ID -->
<!--
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/fr_FR/sdk.js#xfbml=1&version=v17.0&appId=VOTRE_APP_ID&autoLogAppEvents=1"></script>
-->
</body>
</html>