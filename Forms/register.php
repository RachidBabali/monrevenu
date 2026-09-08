<?php
/**
 * register.php — Formulaire d'inscription sécurisé
 * À placer dans : Forms/register.php
 * Inclus depuis : inscription.php (racine)
 */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [
    'champs_manquants'   => 'Tous les champs sont obligatoires.',
    'nom_invalide'       => 'Nom complet invalide (2 à 100 caractères).',
    'email_invalide'     => 'Format d\'email invalide (exemple@domaine.com).',
    'phone_non_comorien' => 'Merci de saisir un numéro comorien valide (ex: 3000000 ou 4000000).',
    'code_invalide'      => 'Le code secret doit contenir exactement 2 chiffres et 2 lettres.',
    'code_different'     => 'Les codes secrets ne correspondent pas.',
    'conditions'         => 'Vous devez accepter les conditions générales.',
    'existe_deja'        => 'Cet email ou téléphone est déjà utilisé.',
    'birthdate_invalide' => 'Vous devez avoir au moins 18 ans pour vous inscrire.',
    'methode_invalide'   => 'Merci de choisir un canal de vérification (WhatsApp ou Email).',
    'csrf'               => 'Session expirée, veuillez réessayer.',
    'serveur'            => 'Erreur serveur. Veuillez réessayer.',
];

// Association erreur → champ concerné, pour afficher le message au bon endroit
$champ_en_erreur = [
    'nom_invalide'       => 'fullname',
    'email_invalide'     => 'email',
    'existe_deja'        => 'email',
    'phone_non_comorien' => 'phone',
    'code_invalide'      => 'code',
    'code_different'     => 'confirmCode',
    'conditions'         => 'acceptTerms',
    'birthdate_invalide' => 'birthdate',
    'methode_invalide'   => 'verificationMethod',
];

$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$champ_errone = $champ_en_erreur[$error] ?? '';

$old_fullname  = htmlspecialchars($_GET['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
$old_email     = htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES, 'UTF-8');
$old_phone     = htmlspecialchars($_GET['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$old_birthdate = htmlspecialchars($_GET['birthdate'] ?? '', ENT_QUOTES, 'UTF-8');

$parrain_id_recu = (int) ($_GET['parrain'] ?? 0);

// Petit utilitaire pour ne pas répéter la logique d'affichage à chaque champ
function afficherErreurChamp(string $nomChamp, string $champErrone, array $errors, string $error): void {
    if ($nomChamp === $champErrone && $error) {
        echo '<p class="field-error" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600;">⚠ '
            . htmlspecialchars($errors[$error] ?? 'Champ invalide.') . '</p>';
    }
}
function classeChampErreur(string $nomChamp, string $champErrone): string {
    return ($nomChamp === $champErrone) ? 'border-color:#dc2626 !important;' : '';
}

// Date max autorisée : il y a exactement 18 ans (pour l'attribut HTML max du champ date)
$date_max_18ans = date('Y-m-d', strtotime('-18 years'));
?>

<!-- CARD INSCRIPTION -->
<div class="card-wrap">
  <div class="card">
    <div class="card-title">Créer un compte</div>
    <div class="card-hint">Rejoignez MonRevenu et maîtrisez vos finances</div>

    <?php if ($error && !$champ_errone): ?>
      <div class="msg err"><?= htmlspecialchars($errors[$error] ?? 'Une erreur est survenue.') ?></div>
    <?php endif; ?>
    <?php if ($success === 'inscription'): ?>
      <div class="msg ok">✓ Compte créé avec succès ! Vous pouvez vous connecter.</div>
    <?php endif; ?>
    <?php if ($parrain_id_recu > 0): ?>
      <div class="msg ok" style="margin-bottom: 12px;">🎉 Vous avez été invité(e) par un membre MonRevenu !</div>
    <?php endif; ?>

    <form method="POST" action="/includs/register_handler.php" id="registerForm" novalidate>

      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="parrain_id" value="<?= $parrain_id_recu ?>">

      <!-- Nom complet -->
      <div class="field">
        <label for="fullName">Nom complet</label>
        <div class="field-row" style="<?= classeChampErreur('fullname', $champ_errone) ?>">
          <span class="field-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </span>
          <input type="text" id="fullName" name="fullname" 
                 placeholder="Jean Dupont" 
                 value="<?= $old_fullname ?>"
                 autocomplete="name" 
                 maxlength="100" required>
        </div>
        <p class="field-error" id="err-fullName" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
        <?php afficherErreurChamp('fullname', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Email -->
      <div class="field">
        <label for="email">Adresse email</label>
        <div class="field-row" style="<?= classeChampErreur('email', $champ_errone) ?>">
          <span class="field-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
          </span>
          <input type="email" id="email" name="email" 
                 placeholder="exemple@domaine.com" 
                 value="<?= $old_email ?>"
                 autocomplete="email" 
                 maxlength="150" required>
        </div>
        <p class="field-error" id="err-email" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
        <?php afficherErreurChamp('email', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Date de naissance -->
      <div class="field">
        <label for="birthdate">Date de naissance</label>
        <div class="field-row" style="<?= classeChampErreur('birthdate', $champ_errone) ?>">
          <span class="field-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
          </span>
          <input type="date" id="birthdate" name="birthdate" 
                 value="<?= $old_birthdate ?>"
                 max="<?= $date_max_18ans ?>"
                 required>
        </div>
        <p style="font-size:11px; color:#9ca3af; margin-top:4px;">Vous devez avoir au moins 18 ans.</p>
        <p class="field-error" id="err-birthdate" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
        <?php afficherErreurChamp('birthdate', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Téléphone -->
      <div class="field">
        <label for="phone">Numéro de téléphone (comorien)</label>
        <div class="field-row" style="<?= classeChampErreur('phone', $champ_errone) ?>">
          <span class="field-icon" style="font-weight:700; font-size:13px; color:#64748b; width:auto; padding-right:2px;">+269</span>
          <input type="tel" id="phone" name="phone" 
                 placeholder="3000000 ou 4000000" 
                 value="<?= $old_phone ?>"
                 autocomplete="tel" 
                 inputmode="numeric"
                 pattern="[34][0-9]{6}"
                 maxlength="7" required>
        </div>
        <p class="field-error" id="err-phone" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
        <?php afficherErreurChamp('phone', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Canal de vérification (email uniquement) -->
      <input type="hidden" name="verification_method" value="email">

      <!-- Code secret -->
      <div class="field">
        <label for="code">Code secret (2 chiffres + 2 lettres)</label>
        <div class="field-row" style="<?= classeChampErreur('code', $champ_errone) ?>">
          <span class="field-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <input type="text" id="code" name="code" 
                 placeholder="Ex: A1B2" 
                 autocomplete="new-password" 
                 maxlength="4" minlength="4" required
                 style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;">
        </div>
        <p style="font-size:11px; color:#9ca3af; margin-top:4px;">Exactement 2 chiffres et 2 lettres, dans l'ordre de votre choix (ex: A1B2, 12AB, B4A9).</p>
        <p class="field-error" id="err-code" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
        <?php afficherErreurChamp('code', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Confirmation code secret -->
      <div class="field">
        <label for="confirmCode">Confirmer le code secret</label>
        <div class="field-row" style="<?= classeChampErreur('confirmCode', $champ_errone) ?>">
          <span class="field-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <input type="text" id="confirmCode" name="confirm_code" 
                 placeholder="Ex: A1B2" 
                 autocomplete="off" 
                 maxlength="4" minlength="4" required
                 style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;">
        </div>
        <p class="field-error" id="err-confirmCode" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
        <?php afficherErreurChamp('confirmCode', $champ_errone, $errors, $error); ?>
      </div>

      <!-- CGU -->
      <div class="checkbox-field">
        <input type="checkbox" id="acceptTerms" name="acceptTerms" required>
        <label for="acceptTerms">
          J'accepte les <a href="/conditions.php">conditions générales</a> et la <a href="/confidentialite.php">politique de confidentialité</a>
        </label>
      </div>
      <p class="field-error" id="err-acceptTerms" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
      <?php afficherErreurChamp('acceptTerms', $champ_errone, $errors, $error); ?>

      <button type="submit" class="btn-submit">S'INSCRIRE</button>

    </form>

    <div class="divider">
      <div class="divider-line"></div>
      <span class="divider-txt">ou s'inscrire avec</span>
      <div class="divider-line"></div>
    </div>

    <div class="bottom-note">
      Déjà un compte ? <a href="/index.php">Se connecter</a>
    </div>

    <p class="legal">
      En créant un compte, vous acceptez nos <a href="/conditions.php">conditions d'utilisation</a>
      et notre <a href="/confidentialite.php">politique de confidentialité</a>
    </p>
  </div>
</div>

<script>
// ============================================================
// VALIDATION EN DIRECT (avant même l'envoi au serveur)
// ============================================================
// Le serveur reste la source de vérité (register_handler.php revalide
// tout), mais ceci évite un aller-retour serveur pour les erreurs
// évidentes et guide l'utilisateur en temps réel.

const champCode = document.getElementById('code');
const champConfirmCode = document.getElementById('confirmCode');
const champBirthdate = document.getElementById('birthdate');

// Force la saisie en majuscules en direct pour le code secret
[champCode, champConfirmCode].forEach(function (input) {
  input.addEventListener('input', function (e) {
    e.target.value = e.target.value.toUpperCase();
  });
});

function afficherErreur(id, message) {
  const el = document.getElementById('err-' + id);
  if (!el) return;
  el.textContent = message;
  el.style.display = message ? 'block' : 'none';
}

function validerCodeSecret(valeur) {
  if (valeur.length !== 4) return 'Le code doit faire exactement 4 caractères.';
  const nbChiffres = (valeur.match(/[0-9]/g) || []).length;
  const nbLettres  = (valeur.match(/[A-Z]/g) || []).length;
  if (nbChiffres !== 2 || nbLettres !== 2) return 'Il faut exactement 2 chiffres et 2 lettres.';
  return '';
}

function validerTelephoneComorien(valeur) {
  const nettoye = valeur.replace(/\D/g, '');
  let local = nettoye;
  if (nettoye.startsWith('00269')) local = nettoye.slice(5);
  else if (nettoye.startsWith('269') && nettoye.length === 10) local = nettoye.slice(3);
  if (!/^[34]\d{6}$/.test(local)) return 'Numéro comorien invalide (ex: 3212345 ou 4212345).';
  return '';
}

function calculerAge(dateStr) {
  const naissance = new Date(dateStr);
  const aujourdhui = new Date();
  let age = aujourdhui.getFullYear() - naissance.getFullYear();
  const m = aujourdhui.getMonth() - naissance.getMonth();
  if (m < 0 || (m === 0 && aujourdhui.getDate() < naissance.getDate())) age--;
  return age;
}

function validerBirthdate(valeur) {
  if (!valeur) return 'La date de naissance est obligatoire.';
  if (calculerAge(valeur) < 18) return 'Vous devez avoir au moins 18 ans.';
  return '';
}

document.getElementById('fullName').addEventListener('blur', function (e) {
  const v = e.target.value.trim();
  afficherErreur('fullName', (v.length < 2 || v.length > 100) ? 'Le nom doit faire entre 2 et 100 caractères.' : '');
});

document.getElementById('email').addEventListener('blur', function (e) {
  const v = e.target.value.trim();
  const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  afficherErreur('email', ok ? '' : 'Format d\'email invalide.');
});

champBirthdate.addEventListener('blur', function (e) {
  afficherErreur('birthdate', validerBirthdate(e.target.value));
});

document.getElementById('phone').addEventListener('blur', function (e) {
  afficherErreur('phone', validerTelephoneComorien(e.target.value));
});

champCode.addEventListener('blur', function (e) {
  afficherErreur('code', validerCodeSecret(e.target.value));
});

champConfirmCode.addEventListener('blur', function (e) {
  if (e.target.value && champCode.value && e.target.value !== champCode.value) {
    afficherErreur('confirmCode', 'Les codes secrets ne correspondent pas.');
  } else {
    afficherErreur('confirmCode', '');
  }
});

// Bloque l'envoi si des erreurs évidentes existent encore
document.getElementById('registerForm').addEventListener('submit', function (e) {
  const nom = document.getElementById('fullName').value.trim();
  const email = document.getElementById('email').value.trim();
  const birthdate = champBirthdate.value;
  const phone = document.getElementById('phone').value.trim();
  const code = champCode.value.trim();
  const confirmCode = champConfirmCode.value.trim();
  const terms = document.getElementById('acceptTerms').checked;

  let bloque = false;

  if (nom.length < 2 || nom.length > 100) { afficherErreur('fullName', 'Le nom doit faire entre 2 et 100 caractères.'); bloque = true; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { afficherErreur('email', 'Format d\'email invalide.'); bloque = true; }

  const erreurBirthdate = validerBirthdate(birthdate);
  if (erreurBirthdate) { afficherErreur('birthdate', erreurBirthdate); bloque = true; }

  const erreurTel = validerTelephoneComorien(phone);
  if (erreurTel) { afficherErreur('phone', erreurTel); bloque = true; }

  const erreurCode = validerCodeSecret(code);
  if (erreurCode) { afficherErreur('code', erreurCode); bloque = true; }

  if (code !== confirmCode) { afficherErreur('confirmCode', 'Les codes secrets ne correspondent pas.'); bloque = true; }

  if (!terms) { afficherErreur('acceptTerms', 'Vous devez accepter les conditions générales.'); bloque = true; }

  if (bloque) {
    e.preventDefault();
    document.querySelector('.field-error[style*="block"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});
</script>