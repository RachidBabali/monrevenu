<?php
/**
 * login.php, Formulaire de connexion sécurisé (en modale)
 * À placer dans : Forms/login.php
 * Inclus depuis : index.php (racine)
 */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [
    'champs_manquants' => 'Indiquez votre email ou numéro de téléphone et votre mot de passe.',
    'identifiants'      => 'Identifiant ou mot de passe incorrect. Vérifiez votre saisie.',
    'compte_inactif'    => 'Ce compte est désactivé. Écrivez à contact@monrevenu.xyz pour le réactiver.',
    'trop_tentatives'   => 'Trop de tentatives depuis cet appareil. Réessayez dans __RESTE__ minute(s).',
    'compte_bloque'     => 'Ce compte est bloqué après plusieurs tentatives échouées. Réessayez dans __RESTE__ minute(s).',
    'csrf'              => 'Votre session a expiré. Rechargez la page puis reconnectez-vous.',
    'serveur'           => 'La connexion a échoué pour une raison technique. Réessayez dans un instant.',
];

$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$reste   = isset($_GET['reste']) ? max(1, (int) $_GET['reste']) : null;
$reste_tentatives = isset($_GET['reste_tentatives']) ? (int) $_GET['reste_tentatives'] : null;

$message_erreur = $errors[$error] ?? ($error ? 'La connexion a échoué. Réessayez.' : '');
if ($reste !== null) {
    $message_erreur = str_replace('__RESTE__', (string) $reste, $message_erreur);
}

if ($error === 'identifiants' && $reste_tentatives !== null) {
    if ($reste_tentatives > 0) {
        $message_erreur .= ' Il vous reste ' . $reste_tentatives . ' tentative' . ($reste_tentatives > 1 ? 's' : '') . ' avant blocage temporaire.';
    } else {
        $message_erreur .= ' Attention, un nouvel échec bloquera cet appareil temporairement.';
    }
}

// La feuille s'ouvre seule au retour d'une erreur de connexion ou d'un message de succes
$flash_login = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$ouvrir_login = isset($errors[$error]) || in_array($success, ['inscription', 'mdp_reinitialise', 'compte_supprime'], true) || $flash_login !== '';
$messages_succes = [
    'inscription'      => 'Compte créé. Vous pouvez vous connecter.',
    'mdp_reinitialise' => 'Mot de passe modifié. Connectez-vous avec le nouveau mot de passe.',
    'compte_supprime'  => 'Votre compte a été supprimé.',
];
?>

<dialog id="modal-login" class="feuille mr-modal" aria-labelledby="titre-login"<?= $ouvrir_login ? ' data-ouvrir-auto' : '' ?>>
  <div class="poignee"></div>
  <div class="feuille-entete">
    <h2 class="feuille-titre" id="titre-login">Connexion</h2>
    <button type="button" class="btn btn-icone btn-discret" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
  </div>
  <div class="feuille-corps flex flex-col gap-4">
    <?php if ($error && isset($errors[$error])): ?>
      <p class="alerte alerte-danger" role="alert"><?= ico('circle-alert') ?><span><?= e($message_erreur) ?></span></p>
    <?php endif; ?>
    <?php if ($flash_login !== ''): ?>
      <p class="alerte alerte-succes" role="status"><?= ico('circle-check') ?><span><?= e($flash_login) ?></span></p>
    <?php endif; ?>
    <?php if (isset($messages_succes[$success])): ?>
      <p class="alerte alerte-succes" role="status"><?= ico('circle-check') ?><span><?= e($messages_succes[$success]) ?></span></p>
    <?php endif; ?>

    <form method="POST" action="includs/login_handler.php" id="loginForm" novalidate class="flex flex-col gap-4">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <div class="champ">
        <label class="champ-label" for="loginPhone">Email ou numéro de téléphone</label>
        <input class="champ-saisie" type="text" id="loginPhone" name="identifiant" autocomplete="username" inputmode="email"
               maxlength="150" required placeholder="nom@exemple.com ou 77 123 45 67" aria-describedby="err-loginPhone">
        <p class="champ-erreur field-error" id="err-loginPhone" hidden></p>
      </div>

      <div class="champ">
        <div class="flex items-baseline justify-between gap-3">
          <label class="champ-label" for="loginCode">Mot de passe</label>
          <a href="/mot_de_passe_oublie.php" class="lien text-sm">Mot de passe oublié</a>
        </div>
        <div class="relative">
          <input class="champ-saisie pr-12" type="password" id="loginCode" name="code" autocomplete="current-password"
                 maxlength="64" required aria-describedby="err-loginCode">
          <button class="eye-btn btn btn-icone btn-discret absolute right-0.5 top-1/2 -translate-y-1/2" type="button" id="eyeBtn" data-afficher-mdp="loginCode" aria-label="Afficher le mot de passe" aria-pressed="false"><?= ico('eye') ?></button>
        </div>
        <p class="champ-erreur field-error" id="err-loginCode" hidden></p>
      </div>

      <button type="submit" class="btn btn-primaire btn-bloc"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Se connecter</span></button>
    </form>

    <div class="flex items-center gap-3 text-xs text-text-3"><span class="h-px flex-1 bg-line"></span>ou<span class="h-px flex-1 bg-line"></span></div>
    <div id="googleBtnLogin" class="flex min-h-[44px] justify-center"></div>

    <p class="text-center text-sm text-text-2">
      Pas encore de compte ?
      <button type="button" class="lien" data-basculer="modal-register">Créer un compte</button>
    </p>
  </div>
</dialog>
