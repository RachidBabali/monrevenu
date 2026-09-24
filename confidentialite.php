<?php
/**
 * confidentialite.php, Politique de Confidentialité
 * À placer à la racine du projet
 */
?>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
$titre_page    = "Politique de confidentialité";
$page_publique = true;
$page_indexable = true;
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
<main id="contenu" class="conteneur grid gap-8 py-8 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-x-16 lg:py-14">
  <div class="lg:col-start-2">
    <h1 class="text-2xl font-semibold lg:text-3xl">Politique de confidentialité</h1>
    <p class="mt-2 text-sm text-text-3">Dernière mise à jour : <?= date('d/m/Y') ?></p>
  </div>
  <nav class="lg:sticky lg:top-8 lg:row-start-2 lg:self-start" aria-labelledby="titre-sommaire">
    <p id="titre-sommaire" class="text-sm font-semibold text-text">Sommaire</p>
    <ol class="mt-3 flex flex-col gap-2 border-l border-line pl-4 text-sm text-text-2">
          <li><a class="cible hover:text-primary-ink" href="#donnees-collectees">1. Données collectées</a></li>
          <li><a class="cible hover:text-primary-ink" href="#securite-des-donnees">2. Sécurité des données</a></li>
          <li><a class="cible hover:text-primary-ink" href="#utilisation-des-donnees">3. Utilisation des données</a></li>
          <li><a class="cible hover:text-primary-ink" href="#partage-des-donnees">4. Partage des données</a></li>
          <li><a class="cible hover:text-primary-ink" href="#conservation-des-donnees">5. Conservation des données</a></li>
          <li><a class="cible hover:text-primary-ink" href="#vos-droits">6. Vos droits</a></li>
          <li><a class="cible hover:text-primary-ink" href="#cookies">7. Cookies</a></li>
          <li><a class="cible hover:text-primary-ink" href="#modification-de-la-politique">8. Modification de la politique</a></li>
          <li><a class="cible hover:text-primary-ink" href="#contact">9. Contact</a></li>
    </ol>
  </nav>
  <article class="lg:row-start-2">
    <div class="lecture">
<h2 id="donnees-collectees">1. Données collectées</h2>
<p>Lors de votre inscription et de l'utilisation de MonRevenu, nous collectons les données suivantes :</p>
<table>
  <tr><th>Donnée</th><th>Finalité</th></tr>
  <tr><td>Nom complet</td><td>Identification du compte</td></tr>
  <tr><td>Adresse email</td><td>Vérification du compte, envoi du code de sécurité, communications</td></tr>
  <tr><td>Numéro de téléphone</td><td>Identification et sécurité du compte</td></tr>
  <tr><td>Date de naissance</td><td>Vérification de l'âge minimum requis (18 ans)</td></tr>
  <tr><td>Mot de passe (haché)</td><td>Authentification sécurisée</td></tr>
  <tr><td>Historique de transactions</td><td>Suivi de vos revenus, dépôts, retraits et transferts</td></tr>
</table>
<h2 id="securite-des-donnees">2. Sécurité des données</h2>
<p>Votre mot de passe est stocké de façon chiffrée (hachage bcrypt) et n'est jamais accessible en clair, y compris par notre équipe. Les échanges avec la plateforme sont protégés, et un système de jeton anti-CSRF sécurise vos formulaires.</p>
<h2 id="utilisation-des-donnees">3. Utilisation des données</h2>
<p>Vos données sont utilisées exclusivement pour :</p>
<ul>
  <li>Créer et sécuriser votre compte</li>
  <li>Traiter vos transactions (dépôts, retraits, transferts)</li>
  <li>Gérer le programme de parrainage</li>
  <li>Vous envoyer les codes de vérification et notifications liées à votre compte</li>
  <li>Améliorer la qualité du service</li>
</ul>
<h2 id="partage-des-donnees">4. Partage des données</h2>
<p>MonRevenu ne vend ni ne loue vos données personnelles à des tiers. Vos données peuvent être partagées uniquement avec les prestataires techniques strictement nécessaires au fonctionnement du service (ex : service d'envoi d'emails), et uniquement dans la mesure requise pour cette fonction.</p>
<h2 id="conservation-des-donnees">5. Conservation des données</h2>
<p>Vos données sont conservées tant que votre compte est actif. En cas de suppression de compte, vos données personnelles sont supprimées ou anonymisées, sous réserve des obligations légales de conservation applicables aux données de transactions financières.</p>
<h2 id="vos-droits">6. Vos droits</h2>
<p>Vous pouvez à tout moment demander l'accès, la rectification ou la suppression de vos données personnelles en nous contactant via la messagerie intégrée à l'application.</p>
<h2 id="cookies">7. Cookies</h2>
<p>Le site utilise des cookies techniques strictement nécessaires à son fonctionnement (session de connexion, sécurité). Aucun cookie publicitaire ou de tracking tiers n'est utilisé.</p>
<h2 id="modification-de-la-politique">8. Modification de la politique</h2>
<p>Cette politique de confidentialité peut être mise à jour périodiquement. Toute modification substantielle sera communiquée aux utilisateurs.</p>
<h2 id="contact">9. Contact</h2>
<p>Pour toute question relative à vos données personnelles, contactez-nous via la messagerie intégrée à l'application.</p>
    </div>
  </article>
</main>
<footer class="border-t border-line">
  <div class="conteneur flex flex-wrap gap-x-6 gap-y-2 py-6 text-sm text-text-3">
    <a class="cible hover:text-primary-ink" href="/conditions.php">Conditions générales</a>
    <a class="cible hover:text-primary-ink" href="/confidentialite.php">Confidentialité</a>
    <a class="cible hover:text-primary-ink" href="/suppression-donnees.php">Suppression des données</a>
    <a class="cible hover:text-primary-ink" href="/contact.php">Contact</a>
    <a class="cible hover:text-primary-ink" href="mailto:contact@monrevenu.xyz">contact@monrevenu.xyz</a>
  </div>
</footer>
</body>
</html>
