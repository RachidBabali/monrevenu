<?php
/**
 * conditions.php, Conditions Générales d'Utilisation
 * À placer à la racine du projet
 */
?>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
$titre_page    = "Conditions générales d'utilisation";
$page_publique = true;
$page_indexable = true;
include $_SERVER['DOCUMENT_ROOT'] . '/includs/head.php';
?>
<body class="bg-surface">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>
<header class="border-b border-line">
  <div class="conteneur flex h-14 items-center justify-between gap-3">
    <a href="/" class="flex min-h-[44px] items-center gap-2" aria-label="MonRevenu, accueil">
      <img src="/assets/img/svg/monrevenu-marque.svg" alt="" width="28" height="28" class="h-7 w-7">
      <span class="font-semibold text-primary-ink">MonRevenu</span>
    </a>
    <a class="lien cible text-sm" href="/">Retour à l'accueil</a>
  </div>
</header>
<main id="contenu" class="conteneur grid gap-8 py-8 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-x-16 lg:py-14">
  <div class="lg:col-start-2">
    <h1 class="text-2xl font-semibold lg:text-3xl">Conditions générales d'utilisation</h1>
    <p class="mt-2 text-sm text-text-3">Dernière mise à jour : <?= date('d/m/Y') ?></p>
  </div>
  <nav class="lg:sticky lg:top-8 lg:row-start-2 lg:self-start" aria-labelledby="titre-sommaire">
    <p id="titre-sommaire" class="text-sm font-semibold text-text">Sommaire</p>
    <ol class="mt-3 flex flex-col gap-2 border-l border-line pl-4 text-sm text-text-2">
          <li><a class="cible hover:text-primary-ink" href="#objet">1. Objet</a></li>
          <li><a class="cible hover:text-primary-ink" href="#inscription-et-compte-utilisateur">2. Inscription et compte utilisateur</a></li>
          <li><a class="cible hover:text-primary-ink" href="#services-proposes">3. Services proposés</a></li>
          <li><a class="cible hover:text-primary-ink" href="#programme-de-parrainage">4. Programme de parrainage</a></li>
          <li><a class="cible hover:text-primary-ink" href="#transactions-financieres">5. Transactions financières</a></li>
          <li><a class="cible hover:text-primary-ink" href="#obligations-de-l-utilisateur">6. Obligations de l'utilisateur</a></li>
          <li><a class="cible hover:text-primary-ink" href="#suspension-et-resiliation">7. Suspension et résiliation</a></li>
          <li><a class="cible hover:text-primary-ink" href="#responsabilite">8. Responsabilité</a></li>
          <li><a class="cible hover:text-primary-ink" href="#modification-des-cgu">9. Modification des CGU</a></li>
          <li><a class="cible hover:text-primary-ink" href="#contact">10. Contact</a></li>
    </ol>
  </nav>
  <article class="lg:row-start-2">
    <div class="lecture">
<h2 id="objet">1. Objet</h2>
<p>Les présentes Conditions Générales d'Utilisation (« CGU ») régissent l'accès et l'utilisation de la plateforme MonRevenu, accessible via son site web et son application mobile. En créant un compte, vous acceptez sans réserve les présentes CGU.</p>
<h2 id="inscription-et-compte-utilisateur">2. Inscription et compte utilisateur</h2>
<p>L'inscription est réservée aux personnes physiques âgées d'au moins 18 ans, disposant d'un numéro de téléphone valide des Comores ou du Sénégal. Vous vous engagez à fournir des informations exactes et à jour, et à maintenir la confidentialité de votre mot de passe. Toute activité effectuée depuis votre compte est présumée effectuée par vous.</p>
<h2 id="services-proposes">3. Services proposés</h2>
<p>MonRevenu permet notamment de suivre ses revenus et dépenses, d'effectuer des dépôts, retraits et transferts, de participer à un programme de parrainage, et d'accéder à des formations. La disponibilité de ces services peut évoluer sans préavis.</p>
<h2 id="programme-de-parrainage">4. Programme de parrainage</h2>
<p>Le système de parrainage permet de percevoir une commission pour chaque filleul inscrit via votre lien personnel, selon les conditions en vigueur sur la plateforme. MonRevenu se réserve le droit de suspendre ou d'annuler toute commission obtenue par fraude, création de faux comptes, ou usage abusif du système.</p>
<h2 id="transactions-financieres">5. Transactions financières</h2>
<p>Les dépôts, retraits et transferts effectués sur la plateforme sont sous votre entière responsabilité. Vérifiez toujours les montants et destinataires avant de valider une opération. MonRevenu ne pourra être tenu responsable d'une transaction erronée résultant d'une saisie incorrecte de votre part.</p>
<h2 id="obligations-de-l-utilisateur">6. Obligations de l'utilisateur</h2>
<ul>
  <li>Ne pas créer de faux comptes ou usurper l'identité d'autrui</li>
  <li>Ne pas utiliser la plateforme à des fins frauduleuses ou illégales</li>
  <li>Ne pas tenter de contourner les mesures de sécurité du site</li>
  <li>Signaler toute activité suspecte sur votre compte</li>
</ul>
<h2 id="suspension-et-resiliation">7. Suspension et résiliation</h2>
<p>MonRevenu se réserve le droit de suspendre ou clôturer tout compte en cas de non-respect des présentes CGU, de fraude avérée ou suspectée, ou de fourniture d'informations fausses.</p>
<h2 id="responsabilite">8. Responsabilité</h2>
<p>MonRevenu met tout en œuvre pour assurer la disponibilité et la sécurité de la plateforme, sans pouvoir garantir une disponibilité continue et sans interruption. MonRevenu ne saurait être tenu responsable des dommages indirects résultant de l'utilisation du service.</p>
<h2 id="modification-des-cgu">9. Modification des CGU</h2>
<p>MonRevenu peut modifier les présentes CGU à tout moment. Les utilisateurs seront informés de toute modification substantielle. La poursuite de l'utilisation du service après modification vaut acceptation des nouvelles conditions.</p>
<h2 id="contact">10. Contact</h2>
<p>Pour toute question relative aux présentes CGU, vous pouvez nous contacter via la messagerie intégrée à l'application.</p>
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
