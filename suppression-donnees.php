<?php
/**
 * suppression-donnees.php — Page publique de suppression des données
 * Exigée par Meta pour l'app WhatsApp Business. Accessible sans connexion.
 * À placer à la racine du projet.
 */
?>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
$titre_page    = "Suppression de vos données";
$page_publique = true;
include $_SERVER['DOCUMENT_ROOT'] . '/includs/head.php';
?>
<body class="bg-surface">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>
<header class="border-b border-line">
  <div class="conteneur flex h-14 items-center justify-between gap-3">
    <a href="/" class="flex items-center gap-2" aria-label="MonRevenu, accueil">
      <img src="/assets/img/logo-64.png" alt="" width="28" height="28" class="h-7 w-7">
      <span class="font-semibold text-primary-ink">MonRevenu</span>
    </a>
    <a class="lien text-sm" href="/">Retour à l'accueil</a>
  </div>
</header>
<main id="contenu" class="conteneur grid gap-8 py-8 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-x-16 lg:py-14">
  <div class="lg:col-start-2">
    <h1 class="text-2xl font-semibold lg:text-3xl">Suppression de vos données</h1>
    <p class="mt-2 text-sm text-text-3">Dernière mise à jour : <?= date('d/m/Y') ?></p>
  </div>
  <nav class="lg:sticky lg:top-8 lg:row-start-2 lg:self-start" aria-labelledby="titre-sommaire">
    <p id="titre-sommaire" class="text-sm font-semibold text-text">Sommaire</p>
    <ol class="mt-3 flex flex-col gap-2 border-l border-line pl-4 text-sm text-text-2">
          <li><a class="hover:text-primary-ink" href="#quelles-donnees-sont-collectees">Quelles données sont collectées</a></li>
          <li><a class="hover:text-primary-ink" href="#pourquoi-ces-donnees">Pourquoi ces données</a></li>
          <li><a class="hover:text-primary-ink" href="#combien-de-temps-sont-elles-conservees">Combien de temps sont-elles conservées</a></li>
          <li><a class="hover:text-primary-ink" href="#ce-qui-est-conserve-malgre-la-suppression">Ce qui est conservé malgré la suppression</a></li>
          <li><a class="hover:text-primary-ink" href="#comment-demander-la-suppression">Comment demander la suppression</a></li>
    </ol>
  </nav>
  <article class="lg:row-start-2">
    <div class="lecture">
<h2 id="quelles-donnees-sont-collectees">Quelles données sont collectées</h2>
      <p>Lorsque vous créez un compte et utilisez MonRevenu, nous collectons :</p>
      <ul>
        <li>Votre nom complet et votre adresse email</li>
        <li>Votre numéro de téléphone WhatsApp, utilisé pour vérifier votre identité</li>
        <li>Votre historique de ventes, de commissions et de retraits</li>
      </ul>
    <h2 id="pourquoi-ces-donnees">Pourquoi ces données</h2>
      <p>
        Elles servent à créer et sécuriser votre compte, vérifier votre numéro WhatsApp, calculer et verser vos commissions
        d'affiliation, et traiter vos demandes de retrait.
      </p>
    <h2 id="combien-de-temps-sont-elles-conservees">Combien de temps sont-elles conservées</h2>
      <p>
        Tant que votre compte est actif. Si vous demandez la suppression de votre compte, vos données personnelles
        (nom, email, numéro de téléphone, code de vérification WhatsApp) sont effacées sous 30 jours.
      </p>
    <h2 id="ce-qui-est-conserve-malgre-la-suppression">Ce qui est conservé malgré la suppression</h2>
      <p>
        Pour des raisons comptables et légales, l'historique de vos ventes, commissions et retraits déjà effectués est
        conservé, mais il est dissocié de votre identité : votre nom, email et téléphone ne restent associés à aucune
        de ces lignes après suppression.
      </p>
    <h2 id="comment-demander-la-suppression">Comment demander la suppression</h2>
      <p>Depuis votre compte</p>
          <p>
            Connectez-vous, ouvrez votre profil, puis « Supprimer mon compte » en bas de page.
          </p>
          <a href="/page/profil.php#supprimer-compte">Aller à mon profil</a>
        <p>Par email</p>
          <p>
            Écrivez-nous en précisant le numéro de téléphone ou l'email associé au compte à supprimer.
          </p>
          <a href="mailto:contact@monrevenu.xyz?subject=Demande%20de%20suppression%20de%20compte">contact@monrevenu.xyz</a>
        <p>Délai de traitement annoncé : sous 30 jours à compter de la demande.</p>
    </div>
  </article>
</main>
<footer class="border-t border-line">
  <div class="conteneur flex flex-wrap gap-x-6 gap-y-2 py-6 text-sm text-text-3">
    <a class="hover:text-primary-ink" href="/conditions.php">Conditions générales</a>
    <a class="hover:text-primary-ink" href="/confidentialite.php">Confidentialité</a>
    <a class="hover:text-primary-ink" href="/suppression-donnees.php">Suppression des données</a>
    <a class="hover:text-primary-ink" href="mailto:contact@monrevenu.xyz">contact@monrevenu.xyz</a>
  </div>
</footer>
</body>
</html>
