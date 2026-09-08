<?php
/**
 * confidentialite.php — Politique de Confidentialité
 * À placer à la racine du projet
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Politique de Confidentialité — MonRevenu</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; max-width: 800px; margin: 0 auto; padding: 32px 20px 80px; color: #1e293b; line-height: 1.6; }
  h1 { font-size: 24px; margin-bottom: 4px; }
  .maj { color: #64748b; font-size: 13px; margin-bottom: 32px; }
  h2 { font-size: 17px; margin-top: 32px; color: #0d6efd; }
  p, li { font-size: 14.5px; }
  .back { display: inline-block; margin-bottom: 24px; color: #0d6efd; text-decoration: none; font-weight: 600; font-size: 14px; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13.5px; }
  th, td { text-align: left; padding: 8px 10px; border: 1px solid #e2e8f0; }
  th { background: #f8fafc; }
</style>
</head>
<body>

<a href="/index.php" class="back">← Retour à MonRevenu</a>

<h1>Politique de Confidentialité</h1>
<p class="maj">Dernière mise à jour : <?= date('d/m/Y') ?></p>

<h2>1. Données collectées</h2>
<p>Lors de votre inscription et de l'utilisation de MonRevenu, nous collectons les données suivantes :</p>
<table>
  <tr><th>Donnée</th><th>Finalité</th></tr>
  <tr><td>Nom complet</td><td>Identification du compte</td></tr>
  <tr><td>Adresse email</td><td>Vérification du compte, envoi du code de sécurité, communications</td></tr>
  <tr><td>Numéro de téléphone</td><td>Identification et sécurité du compte</td></tr>
  <tr><td>Date de naissance</td><td>Vérification de l'âge minimum requis (18 ans)</td></tr>
  <tr><td>Code secret (haché)</td><td>Authentification sécurisée</td></tr>
  <tr><td>Historique de transactions</td><td>Suivi de vos revenus, dépôts, retraits et transferts</td></tr>
</table>

<h2>2. Sécurité des données</h2>
<p>Votre code secret est stocké de façon chiffrée (hachage bcrypt) et n'est jamais accessible en clair, y compris par notre équipe. Les échanges avec la plateforme sont protégés, et un système de jeton anti-CSRF sécurise vos formulaires.</p>

<h2>3. Utilisation des données</h2>
<p>Vos données sont utilisées exclusivement pour :</p>
<ul>
  <li>Créer et sécuriser votre compte</li>
  <li>Traiter vos transactions (dépôts, retraits, transferts)</li>
  <li>Gérer le programme de parrainage</li>
  <li>Vous envoyer les codes de vérification et notifications liées à votre compte</li>
  <li>Améliorer la qualité du service</li>
</ul>

<h2>4. Partage des données</h2>
<p>MonRevenu ne vend ni ne loue vos données personnelles à des tiers. Vos données peuvent être partagées uniquement avec les prestataires techniques strictement nécessaires au fonctionnement du service (ex : service d'envoi d'emails), et uniquement dans la mesure requise pour cette fonction.</p>

<h2>5. Conservation des données</h2>
<p>Vos données sont conservées tant que votre compte est actif. En cas de suppression de compte, vos données personnelles sont supprimées ou anonymisées, sous réserve des obligations légales de conservation applicables aux données de transactions financières.</p>

<h2>6. Vos droits</h2>
<p>Vous pouvez à tout moment demander l'accès, la rectification ou la suppression de vos données personnelles en nous contactant via la messagerie intégrée à l'application.</p>

<h2>7. Cookies</h2>
<p>Le site utilise des cookies techniques strictement nécessaires à son fonctionnement (session de connexion, sécurité). Aucun cookie publicitaire ou de tracking tiers n'est utilisé.</p>

<h2>8. Modification de la politique</h2>
<p>Cette politique de confidentialité peut être mise à jour périodiquement. Toute modification substantielle sera communiquée aux utilisateurs.</p>

<h2>9. Contact</h2>
<p>Pour toute question relative à vos données personnelles, contactez-nous via la messagerie intégrée à l'application.</p>

</body>
</html>