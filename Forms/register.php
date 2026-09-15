<?php
/**
 * register.php — Formulaire d'inscription sécurisé (en modale)
 * À placer dans : Forms/register.php
 * Inclus depuis : index.php (racine)
 */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [
    'champs_manquants'   => 'Tous les champs sont obligatoires.',
    'nom_invalide'       => 'Nom complet invalide (2 à 100 caractères).',
    'email_invalide'     => 'Format d\'email invalide (exemple@domaine.com).',
    'phone_non_comorien' => 'Merci de saisir un numéro comorien valide (ex: 3000000 ou 4000000).',
    'phone_invalide'     => 'Merci de saisir un numéro de téléphone valide pour le pays sélectionné.',
    'code_invalide'      => 'Le mot de passe doit contenir au moins 8 caractères.',
    'code_different'     => 'Les mots de passe ne correspondent pas.',
    'conditions'         => 'Vous devez accepter les conditions générales.',
    'existe_deja'        => 'Cet email ou téléphone est déjà utilisé.',
    'birthdate_invalide' => 'Vous devez avoir au moins 18 ans pour vous inscrire.',
    'age_insuffisant'    => 'Vous devez avoir au moins 18 ans pour vous inscrire.',
    'methode_invalide'   => 'Merci de choisir un canal de vérification (WhatsApp ou Email).',
    'csrf'               => 'Session expirée, veuillez réessayer.',
    'serveur'            => 'Erreur serveur. Veuillez réessayer.',
];

$champ_en_erreur = [
    'nom_invalide'       => 'fullname',
    'email_invalide'     => 'email',
    'existe_deja'        => 'email',
    'phone_non_comorien' => 'phone',
    'phone_invalide'     => 'phone',
    'code_invalide'      => 'code',
    'code_different'     => 'confirmCode',
    'conditions'         => 'acceptTerms',
    'birthdate_invalide' => 'birthdate',
    'age_insuffisant'    => 'birthdate',
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

function afficherErreurChamp(string $nomChamp, string $champErrone, array $errors, string $error): void {
    if ($nomChamp === $champErrone && $error) {
        echo '<p class="text-xs text-red-600 font-semibold mt-1.5">⚠ '
            . htmlspecialchars($errors[$error] ?? 'Champ invalide.') . '</p>';
    }
}
function classeChampErreur(string $nomChamp, string $champErrone): string {
    return ($nomChamp === $champErrone) ? 'border-color:#dc2626 !important;' : '';
}

$date_max_18ans = date('Y-m-d', strtotime('-18 years'));

$ouvrir_register = ($error || $success || $parrain_id_recu > 0) ? "document.addEventListener('DOMContentLoaded', function(){ openModal('modal-register'); });" : '';
?>

<div id="modal-register" class="mr-modal hidden" onclick="if(event.target===this) closeModal('modal-register')">
  <div class="mr-modal__panel bg-white rounded-2xl shadow-2xl w-full max-w-md relative p-7 md:p-8 max-h-[90vh] overflow-y-auto">

    <button type="button" onclick="closeModal('modal-register')" aria-label="Fermer" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-mr-navy transition-colors">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>

    <h2 class="text-lg font-extrabold text-mr-navy mb-1">Créer un compte</h2>
    <p class="text-sm text-mr-navy-soft mb-6">Rejoignez MonRevenu et développez vos revenus</p>

    <?php if ($error && !$champ_errone): ?>
      <div class="text-sm font-semibold rounded-xl px-4 py-3 mb-5 bg-red-50 text-red-600"><?= htmlspecialchars($errors[$error] ?? 'Une erreur est survenue.') ?></div>
    <?php endif; ?>
    <?php if ($success === 'inscription'): ?>
      <div class="text-sm font-semibold rounded-xl px-4 py-3 mb-5 bg-emerald-50 text-emerald-600">✓ Compte créé avec succès ! Vous pouvez vous connecter.</div>
    <?php endif; ?>
    <?php if ($parrain_id_recu > 0): ?>
      <div class="text-sm font-semibold rounded-xl px-4 py-3 mb-5 bg-emerald-50 text-emerald-600">🎉 Vous avez été invité(e) par un membre MonRevenu !</div>
    <?php endif; ?>

    <form method="POST" action="/includs/register_handler.php" id="registerForm" novalidate class="space-y-4">

      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="parrain_id" value="<?= $parrain_id_recu ?>">

      <!-- Nom complet -->
      <div>
        <label for="fullName" class="block text-xs font-bold text-mr-navy-soft uppercase tracking-wide mb-1.5">Nom complet</label>
        <div class="flex items-center gap-2.5 border border-slate-200 rounded-xl px-3.5 py-3 focus-within:border-mr-blue focus-within:ring-2 focus-within:ring-mr-blue/10 transition-shadow" style="<?= classeChampErreur('fullname', $champ_errone) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="text-slate-400 shrink-0"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/></svg>
          <input type="text" id="fullName" name="fullname"
                 placeholder="Jean Dupont"
                 value="<?= $old_fullname ?>"
                 autocomplete="name"
                 maxlength="100" required
                 class="flex-1 min-w-0 outline-none text-sm text-mr-navy bg-transparent placeholder:text-slate-300">
        </div>
        <p class="text-xs text-red-600 font-semibold mt-1.5" id="err-fullName" style="display:none;"></p>
        <?php afficherErreurChamp('fullname', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Email -->
      <div>
        <label for="email" class="block text-xs font-bold text-mr-navy-soft uppercase tracking-wide mb-1.5">Adresse email</label>
        <div class="flex items-center gap-2.5 border border-slate-200 rounded-xl px-3.5 py-3 focus-within:border-mr-blue focus-within:ring-2 focus-within:ring-mr-blue/10 transition-shadow" style="<?= classeChampErreur('email', $champ_errone) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="text-slate-400 shrink-0"><rect x="2" y="4" width="20" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" stroke="currentColor" stroke-width="2"/></svg>
          <input type="email" id="email" name="email"
                 placeholder="exemple@domaine.com"
                 value="<?= $old_email ?>"
                 autocomplete="email"
                 maxlength="150" required
                 class="flex-1 min-w-0 outline-none text-sm text-mr-navy bg-transparent placeholder:text-slate-300">
        </div>
        <p class="text-xs text-red-600 font-semibold mt-1.5" id="err-email" style="display:none;"></p>
        <?php afficherErreurChamp('email', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Date de naissance -->
      <div>
        <label for="birthdate" class="block text-xs font-bold text-mr-navy-soft uppercase tracking-wide mb-1.5">Date de naissance</label>
        <div class="flex items-center gap-2.5 border border-slate-200 rounded-xl px-3.5 py-3 focus-within:border-mr-blue focus-within:ring-2 focus-within:ring-mr-blue/10 transition-shadow" style="<?= classeChampErreur('birthdate', $champ_errone) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="text-slate-400 shrink-0"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2"/></svg>
          <input type="date" id="birthdate" name="birthdate"
                 value="<?= $old_birthdate ?>"
                 max="<?= $date_max_18ans ?>"
                 required
                 class="flex-1 min-w-0 outline-none text-sm text-mr-navy bg-transparent">
        </div>
        <p class="text-[11px] text-slate-400 mt-1.5">Vous devez avoir au moins 18 ans.</p>
        <p class="text-xs text-red-600 font-semibold mt-1" id="err-birthdate" style="display:none;"></p>
        <?php afficherErreurChamp('birthdate', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Téléphone -->
      <div>
        <label for="phone" class="block text-xs font-bold text-mr-navy-soft uppercase tracking-wide mb-1.5">Numéro de téléphone</label>
        <div class="flex items-center gap-2 border border-slate-200 rounded-xl px-2 py-1 focus-within:border-mr-blue focus-within:ring-2 focus-within:ring-mr-blue/10 transition-shadow" style="<?= classeChampErreur('phone', $champ_errone) ?>">
          <select id="phoneCountry" name="phone_country" class="text-sm font-bold text-mr-navy bg-transparent outline-none py-2 pl-1.5 pr-1 shrink-0">
            <option value="KM">🇰🇲 +269</option>
            <option value="SN">🇸🇳 +221</option>
          </select>
          <span class="w-px h-5 bg-slate-200 shrink-0"></span>
          <input type="tel" id="phone" name="phone"
                 placeholder="3000000 ou 4000000"
                 value="<?= $old_phone ?>"
                 autocomplete="tel"
                 inputmode="numeric"
                 maxlength="9" required
                 class="flex-1 min-w-0 outline-none text-sm text-mr-navy bg-transparent placeholder:text-slate-300 py-2">
        </div>
        <p class="text-xs text-red-600 font-semibold mt-1.5" id="err-phone" style="display:none;"></p>
        <?php afficherErreurChamp('phone', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Canal de vérification (email uniquement) -->
      <input type="hidden" name="verification_method" value="email">

      <!-- Mot de passe -->
      <div>
        <label for="code" class="block text-xs font-bold text-mr-navy-soft uppercase tracking-wide mb-1.5">Mot de passe</label>
        <div class="flex items-center gap-2.5 border border-slate-200 rounded-xl px-3.5 py-3 focus-within:border-mr-blue focus-within:ring-2 focus-within:ring-mr-blue/10 transition-shadow" style="<?= classeChampErreur('code', $champ_errone) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="text-slate-400 shrink-0"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="2"/></svg>
          <input type="password" id="code" name="code"
                 placeholder="8 caractères minimum"
                 autocomplete="new-password"
                 minlength="8" maxlength="64" required
                 class="flex-1 min-w-0 outline-none text-sm text-mr-navy bg-transparent placeholder:text-slate-300">
          <button class="eye-btn text-slate-400 hover:text-mr-blue transition-colors shrink-0" type="button" onclick="togglePwdField('code', 'eyeOpenCode', 'eyeOffCode')" aria-label="Afficher/masquer le mot de passe">
            <svg id="eyeOpenCode" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eyeOffCode" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
        <p class="text-[11px] text-slate-400 mt-1.5">8 caractères minimum. Mélangez lettres et chiffres pour plus de sécurité.</p>
        <p class="text-xs text-red-600 font-semibold mt-1" id="err-code" style="display:none;"></p>
        <?php afficherErreurChamp('code', $champ_errone, $errors, $error); ?>
      </div>

      <!-- Confirmation mot de passe -->
      <div>
        <label for="confirmCode" class="block text-xs font-bold text-mr-navy-soft uppercase tracking-wide mb-1.5">Confirmer le mot de passe</label>
        <div class="flex items-center gap-2.5 border border-slate-200 rounded-xl px-3.5 py-3 focus-within:border-mr-blue focus-within:ring-2 focus-within:ring-mr-blue/10 transition-shadow" style="<?= classeChampErreur('confirmCode', $champ_errone) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="text-slate-400 shrink-0"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="2"/></svg>
          <input type="password" id="confirmCode" name="confirm_code"
                 placeholder="8 caractères minimum"
                 autocomplete="new-password"
                 minlength="8" maxlength="64" required
                 class="flex-1 min-w-0 outline-none text-sm text-mr-navy bg-transparent placeholder:text-slate-300">
          <button class="eye-btn text-slate-400 hover:text-mr-blue transition-colors shrink-0" type="button" onclick="togglePwdField('confirmCode', 'eyeOpenConfirm', 'eyeOffConfirm')" aria-label="Afficher/masquer le mot de passe">
            <svg id="eyeOpenConfirm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eyeOffConfirm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
        <p class="text-xs text-red-600 font-semibold mt-1.5" id="err-confirmCode" style="display:none;"></p>
        <?php afficherErreurChamp('confirmCode', $champ_errone, $errors, $error); ?>
      </div>

      <!-- CGU -->
      <div class="flex items-start gap-2.5 pt-1">
        <input type="checkbox" id="acceptTerms" name="acceptTerms" required class="mt-0.5 w-4 h-4 accent-mr-blue">
        <label for="acceptTerms" class="text-xs text-mr-navy-soft leading-relaxed">
          J'accepte les <a href="/conditions.php" class="font-bold text-mr-blue">conditions générales</a> et la <a href="/confidentialite.php" class="font-bold text-mr-blue">politique de confidentialité</a>
        </label>
      </div>
      <p class="text-xs text-red-600 font-semibold" id="err-acceptTerms" style="display:none;"></p>
      <?php afficherErreurChamp('acceptTerms', $champ_errone, $errors, $error); ?>

      <button type="submit" class="w-full bg-mr-blue hover:bg-mr-blue-dark text-white font-bold text-sm py-3.5 rounded-full transition-colors">
        S'INSCRIRE
      </button>
    </form>

    <div class="flex items-center gap-3 my-5">
      <div class="flex-1 h-px bg-slate-100"></div>
      <span class="text-[11px] text-slate-400 whitespace-nowrap">ou s'inscrire avec</span>
      <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <div id="googleBtnRegister" class="flex justify-center mb-5"></div>

    <p class="text-center text-sm text-mr-navy-soft">
      Déjà un compte ?
      <button type="button" onclick="closeModal('modal-register'); openModal('modal-login')" class="font-bold text-mr-blue hover:text-mr-blue-dark">Se connecter</button>
    </p>
  </div>
</div>

<?php if ($ouvrir_register): ?>
<script><?= $ouvrir_register ?></script>
<?php endif; ?>

<script>
const champCode = document.getElementById('code');
const champConfirmCode = document.getElementById('confirmCode');
const champBirthdate = document.getElementById('birthdate');

// Affichage / masquage des mots de passe
function togglePwdField(inputId, eyeOpenId, eyeOffId) {
  const input = document.getElementById(inputId);
  const eyeOpen = document.getElementById(eyeOpenId);
  const eyeOff = document.getElementById(eyeOffId);
  if (input.type === 'password') {
    input.type = 'text';
    eyeOpen.style.display = 'none';
    eyeOff.style.display = 'block';
  } else {
    input.type = 'password';
    eyeOpen.style.display = 'block';
    eyeOff.style.display = 'none';
  }
}

function afficherErreur(id, message) {
  const el = document.getElementById('err-' + id);
  if (!el) return;
  el.textContent = message;
  el.style.display = message ? 'block' : 'none';
}

function validerCodeSecret(valeur) {
  if (valeur.length < 8) return 'Le mot de passe doit contenir au moins 8 caractères.';
  return '';
}

function validerTelephone(valeur, pays) {
  const nettoye = valeur.replace(/\D/g, '');
  let local = nettoye;
  if (nettoye.startsWith('00269')) local = nettoye.slice(5);
  else if (nettoye.startsWith('00221')) local = nettoye.slice(5);
  else if (nettoye.startsWith('269') && nettoye.length === 10) local = nettoye.slice(3);
  else if (nettoye.startsWith('221') && nettoye.length === 12) local = nettoye.slice(3);

  if (pays === 'SN') {
    if (!/^7\d{8}$/.test(local)) return 'Numéro sénégalais invalide (ex: 771234567).';
  } else {
    if (!/^[34]\d{6}$/.test(local)) return 'Numéro comorien invalide (ex: 3212345 ou 4212345).';
  }
  return '';
}

const champPhoneCountry = document.getElementById('phoneCountry');
const champPhone = document.getElementById('phone');

function appliquerPlaceholderTelephone() {
  if (champPhoneCountry.value === 'SN') {
    champPhone.placeholder = 'Ex: 771234567';
    champPhone.maxLength = 9;
  } else {
    champPhone.placeholder = '3000000 ou 4000000';
    champPhone.maxLength = 7;
  }
}
appliquerPlaceholderTelephone();
champPhoneCountry.addEventListener('change', appliquerPlaceholderTelephone);

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
  afficherErreur('phone', validerTelephone(e.target.value, champPhoneCountry.value));
});

champCode.addEventListener('blur', function (e) {
  afficherErreur('code', validerCodeSecret(e.target.value));
});

champConfirmCode.addEventListener('blur', function (e) {
  if (e.target.value && champCode.value && e.target.value !== champCode.value) {
    afficherErreur('confirmCode', 'Les mots de passe ne correspondent pas.');
  } else {
    afficherErreur('confirmCode', '');
  }
});

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

  const erreurTel = validerTelephone(phone, champPhoneCountry.value);
  if (erreurTel) { afficherErreur('phone', erreurTel); bloque = true; }

  const erreurCode = validerCodeSecret(code);
  if (erreurCode) { afficherErreur('code', erreurCode); bloque = true; }

  if (code !== confirmCode) { afficherErreur('confirmCode', 'Les mots de passe ne correspondent pas.'); bloque = true; }

  if (!terms) { afficherErreur('acceptTerms', 'Vous devez accepter les conditions générales.'); bloque = true; }

  if (bloque) {
    e.preventDefault();
    document.querySelector('.field-error[style*="block"], p[style*="display: block"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});

// ── Inscription / connexion avec Google ───────────────────────────────────
window.handleGoogleCredential = function (response) {
  const csrf = document.querySelector('#registerForm input[name="csrf_token"]').value;
  fetch('includs/google_auth_handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'credential=' + encodeURIComponent(response.credential) + '&csrf_token=' + encodeURIComponent(csrf)
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        window.location.href = data.redirect || '/dashboard.php';
      } else {
        alert(data.error || 'Échec de la connexion avec Google.');
      }
    })
    .catch(() => alert('Erreur réseau, réessayez.'));
};

function initGoogleButtonRegister() {
  if (!window.google || !google.accounts || !google.accounts.id) {
    setTimeout(initGoogleButtonRegister, 300);
    return;
  }
  const clientId = document.querySelector('meta[name="google-signin-client_id"]')?.content;
  if (!clientId || clientId.includes('YOUR_GOOGLE_CLIENT_ID')) return;

  google.accounts.id.initialize({ client_id: clientId, callback: handleGoogleCredential });
  google.accounts.id.renderButton(document.getElementById('googleBtnRegister'), {
    theme: 'outline', size: 'large', shape: 'pill', text: 'signup_with', width: 320
  });
}
document.addEventListener('DOMContentLoaded', initGoogleButtonRegister);
</script>