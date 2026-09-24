<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/mot_de_passe.php'; // MOT_DE_PASSE_AIDE
/**
 * register.php, Formulaire d'inscription sécurisé (en modale)
 * À placer dans : Forms/register.php
 * Inclus depuis : index.php (racine)
 */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [
    'champs_manquants'   => 'Remplissez tous les champs du formulaire.',
    'nom_invalide'       => 'Indiquez votre nom complet (2 à 100 caractères).',
    'email_invalide'     => 'Adresse email invalide. Exemple : nom@exemple.com.',
    'phone_invalide'     => 'Numéro invalide pour le marché choisi. Sénégal : 9 chiffres. Comores : 7 chiffres.',
    'code_invalide'      => 'Choisissez un mot de passe d\'au moins 8 caractères, avec une lettre, un chiffre et un caractère spécial.',
    'code_different'     => 'Les deux mots de passe sont différents. Saisissez-les à nouveau.',
    'conditions'         => 'Cochez la case pour accepter les conditions générales.',
    'existe_deja'        => 'Cet email ou ce numéro est déjà utilisé. Connectez-vous ou utilisez-en un autre.',
    'birthdate_invalide' => 'Vous devez avoir au moins 18 ans pour vous inscrire.',
    'boutique_invalide'  => 'Indiquez le nom de votre boutique (2 à 120 caractères).',
    'age_insuffisant'    => 'Vous devez avoir au moins 18 ans pour vous inscrire.',
    'methode_invalide'   => 'Choisissez comment recevoir votre code de vérification.',
    'csrf'               => 'Votre session a expiré. Rechargez la page puis recommencez.',
    'serveur'            => "L'inscription a échoué pour une raison technique. Réessayez dans un instant.",
];

$champ_en_erreur = [
    'nom_invalide'       => 'fullname',
    'email_invalide'     => 'email',
    'existe_deja'        => 'email',
    'phone_invalide'     => 'phone',
    'code_invalide'      => 'code',
    'code_different'     => 'confirmCode',
    'conditions'         => 'acceptTerms',
    'birthdate_invalide' => 'birthdate',
    'age_insuffisant'    => 'birthdate',
    'methode_invalide'   => 'verificationMethod',
    'boutique_invalide'  => 'nomBoutique',
];

$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$champ_errone = $champ_en_erreur[$error] ?? '';

$old_fullname  = htmlspecialchars($_GET['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
$old_email     = htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES, 'UTF-8');
$old_phone     = htmlspecialchars($_GET['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$old_birthdate = htmlspecialchars($_GET['birthdate'] ?? '', ENT_QUOTES, 'UTF-8');
$saisie_inscription = $error !== '' ? ($_SESSION['inscription_saisie'] ?? []) : [];
$type_compte_choisi = ($saisie_inscription['type'] ?? '') === 'commercant' ? 'commercant' : 'affilie';

function afficherErreurChamp(string $nomChamp, string $champErrone, array $errors, string $error): void {
    if ($nomChamp === $champErrone && $error) {
        echo '<p class="champ-erreur">' . ico('circle-alert', 'ico-16 mt-0.5') . '<span>'
            . e($errors[$error] ?? 'Vérifiez ce champ.') . '</span></p>';
    }
}
function attributErreur(string $nomChamp, string $champErrone): string {
    return ($nomChamp === $champErrone) ? ' aria-invalid="true"' : '';
}

$date_max_18ans = date('Y-m-d', strtotime('-18 years'));
$erreurs_connexion = ['champs_manquants', 'identifiants', 'compte_inactif', 'trop_tentatives', 'compte_bloque', 'csrf', 'serveur'];
$ouvrir_register = (isset($errors[$error]) && !in_array($error, $erreurs_connexion, true));
$pays_tel = $_GET['phone_country'] ?? 'SN';
?>

<dialog id="modal-register" class="feuille mr-modal" aria-labelledby="titre-register"<?= $ouvrir_register ? ' data-ouvrir-auto' : '' ?>>
  <div class="poignee"></div>
  <div class="feuille-entete">
    <h2 class="feuille-titre" id="titre-register">Créer un compte</h2>
    <button type="button" class="btn btn-icone btn-discret" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
  </div>
  <div class="feuille-corps flex flex-col gap-4">
    <?php if ($error && isset($errors[$error]) && !$champ_errone && !in_array($error, $erreurs_connexion, true)): ?>
      <p class="alerte alerte-danger" role="alert"><?= ico('circle-alert') ?><span><?= e($errors[$error]) ?></span></p>
    <?php endif; ?>

    <form method="POST" action="/includs/register_handler.php" id="registerForm" novalidate class="flex flex-col gap-4">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <fieldset class="flex flex-col gap-2">
        <legend class="champ-label mb-2">Vous voulez</legend>
        <div class="grid gap-2 sm:grid-cols-2">
          <label class="choix-carte" for="typeAffilie">
            <input class="case" type="radio" id="typeAffilie" name="type_compte" value="affilie"<?= $type_compte_choisi === 'affilie' ? ' checked' : '' ?>>
            <span><span class="block font-medium text-text">Promouvoir des produits</span><span class="block text-sm text-text-2">Vous partagez des liens et touchez une commission.</span></span>
          </label>
          <label class="choix-carte" for="typeCommercant">
            <input class="case" type="radio" id="typeCommercant" name="type_compte" value="commercant"<?= $type_compte_choisi === 'commercant' ? ' checked' : '' ?>>
            <span><span class="block font-medium text-text">Vendre mes produits</span><span class="block text-sm text-text-2">Vous publiez vos produits, les affiliés les font connaître.</span></span>
          </label>
        </div>
      </fieldset>

      <div class="flex flex-col gap-4" data-champs-commercant<?= $type_compte_choisi === 'commercant' ? '' : ' hidden' ?>>
        <div class="champ">
          <label class="champ-label" for="nomBoutique">Nom de la boutique</label>
          <input class="champ-saisie" type="text" id="nomBoutique" name="nom_boutique" maxlength="120" autocomplete="organization"
                 value="<?= e($saisie_inscription['nom_boutique'] ?? '') ?>" aria-describedby="aide-boutique err-nomBoutique"<?= attributErreur('nomBoutique', $champ_errone) ?>>
          <p class="champ-aide" id="aide-boutique">Visible par les affiliés et vos clients. Vos produits seront publiés après validation de votre compte.</p>
          <p class="champ-erreur field-error" id="err-nomBoutique" hidden></p>
          <?php afficherErreurChamp('nomBoutique', $champ_errone, $errors, $error); ?>
        </div>
        <div class="champ">
          <label class="champ-label" for="villeBoutique">Ville <span class="font-normal text-text-3">(facultatif)</span></label>
          <input class="champ-saisie" type="text" id="villeBoutique" name="ville" maxlength="100" autocomplete="address-level2"
                 value="<?= e($saisie_inscription['ville'] ?? '') ?>">
        </div>
      </div>

      <div class="champ">
        <label class="champ-label" for="fullName">Nom complet</label>
        <input class="champ-saisie" type="text" id="fullName" name="fullname" value="<?= $old_fullname ?>" autocomplete="name"
               autocapitalize="words" maxlength="100" required aria-describedby="err-fullName"<?= attributErreur('fullname', $champ_errone) ?>>
        <p class="champ-erreur field-error" id="err-fullName" hidden></p>
        <?php afficherErreurChamp('fullname', $champ_errone, $errors, $error); ?>
      </div>

      <div class="champ">
        <label class="champ-label" for="email">Adresse email</label>
        <input class="champ-saisie" type="email" id="email" name="email" value="<?= $old_email ?>" autocomplete="email" inputmode="email"
               maxlength="150" required placeholder="nom@exemple.com" aria-describedby="err-email"<?= attributErreur('email', $champ_errone) ?>>
        <p class="champ-aide">Votre code de vérification est envoyé à cette adresse.</p>
        <p class="champ-erreur field-error" id="err-email" hidden></p>
        <?php afficherErreurChamp('email', $champ_errone, $errors, $error); ?>
      </div>

      <div class="champ">
        <label class="champ-label" for="birthdate">Date de naissance</label>
        <input class="champ-saisie" type="date" id="birthdate" name="birthdate" value="<?= $old_birthdate ?>" max="<?= e($date_max_18ans) ?>"
               autocomplete="bday" required aria-describedby="aide-birthdate err-birthdate"<?= attributErreur('birthdate', $champ_errone) ?>>
        <p class="champ-aide" id="aide-birthdate">Vous devez avoir au moins 18 ans.</p>
        <p class="champ-erreur field-error" id="err-birthdate" hidden></p>
        <?php afficherErreurChamp('birthdate', $champ_errone, $errors, $error); ?>
      </div>

      <div class="champ">
        <label class="champ-label" for="phone">Numéro WhatsApp</label>
        <div class="champ-groupe">
          <label class="sr-only" for="phoneCountry">Pays</label>
          <select class="champ-saisie w-[118px] shrink-0 rounded-r-none border-r-0 pr-8" id="phoneCountry" name="phone_country" autocomplete="tel-country-code">
            <?php foreach (marches() as $codeMarcheOption => $configMarcheOption): ?>
              <option value="<?= e($codeMarcheOption) ?>"<?= $pays_tel === $codeMarcheOption ? ' selected' : '' ?>><?= e($codeMarcheOption) ?> +<?= e($configMarcheOption['indicatif']) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="champ-saisie" type="tel" id="phone" name="phone" value="<?= $old_phone ?>" autocomplete="tel-national" inputmode="numeric"
                 maxlength="<?= (int) max(array_column(marches(), 'longueur_nationale')) ?>" required aria-describedby="err-phone"<?= attributErreur('phone', $champ_errone) ?>>
        </div>
        <p class="champ-erreur field-error" id="err-phone" hidden></p>
        <?php afficherErreurChamp('phone', $champ_errone, $errors, $error); ?>
      </div>

      <input type="hidden" name="verification_method" value="email">

      <div class="champ">
        <label class="champ-label" for="code">Mot de passe</label>
        <div class="relative">
          <input class="champ-saisie pr-12" type="password" id="code" name="code" autocomplete="new-password" minlength="8" maxlength="64" required
                 aria-describedby="aide-code err-code"<?= attributErreur('code', $champ_errone) ?>>
          <button class="eye-btn btn btn-icone btn-discret absolute right-0.5 top-1/2 -translate-y-1/2" type="button" data-afficher-mdp="code" aria-label="Afficher le mot de passe" aria-pressed="false"><?= ico('eye') ?></button>
        </div>
        <p class="champ-aide" id="aide-code"><?= e(MOT_DE_PASSE_AIDE) ?></p>
        <p class="champ-erreur field-error" id="err-code" hidden></p>
        <?php afficherErreurChamp('code', $champ_errone, $errors, $error); ?>
      </div>

      <div class="champ">
        <label class="champ-label" for="confirmCode">Confirmer le mot de passe</label>
        <div class="relative">
          <input class="champ-saisie pr-12" type="password" id="confirmCode" name="confirm_code" autocomplete="new-password" minlength="8" maxlength="64" required
                 aria-describedby="err-confirmCode"<?= attributErreur('confirmCode', $champ_errone) ?>>
          <button class="eye-btn btn btn-icone btn-discret absolute right-0.5 top-1/2 -translate-y-1/2" type="button" data-afficher-mdp="confirmCode" aria-label="Afficher le mot de passe" aria-pressed="false"><?= ico('eye') ?></button>
        </div>
        <p class="champ-erreur field-error" id="err-confirmCode" hidden></p>
        <?php afficherErreurChamp('confirmCode', $champ_errone, $errors, $error); ?>
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="flex items-start gap-3 text-sm text-text-2" for="acceptTerms">
          <input class="case mt-0.5" type="checkbox" id="acceptTerms" name="acceptTerms" required aria-describedby="err-acceptTerms">
          <span>J'accepte les <a class="lien" href="/conditions.php" target="_blank">conditions générales</a> et la <a class="lien" href="/confidentialite.php" target="_blank">politique de confidentialité</a>.</span>
        </label>
        <p class="champ-erreur field-error" id="err-acceptTerms" hidden></p>
        <?php afficherErreurChamp('acceptTerms', $champ_errone, $errors, $error); ?>
      </div>

      <button type="submit" class="btn btn-primaire btn-bloc"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Créer mon compte</span></button>
    </form>

    <div class="flex flex-col gap-4" data-bloc-google data-masquer-commercant hidden>
      <div class="flex items-center gap-3 text-xs text-text-3"><span class="h-px flex-1 bg-line"></span>ou<span class="h-px flex-1 bg-line"></span></div>
      <div id="googleBtnRegister" class="flex min-h-[44px] justify-center"></div>
    </div>

    <p class="text-center text-sm text-text-2">
      Déjà un compte ?
      <button type="button" class="lien" data-basculer="modal-login">Se connecter</button>
    </p>
  </div>
</dialog>
