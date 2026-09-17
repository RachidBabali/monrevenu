<?php
/**
 * login.php — Formulaire de connexion sécurisé (en modale)
 * À placer dans : Forms/login.php
 * Inclus depuis : index.php (racine)
 */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [
    'champs_manquants' => 'Veuillez remplir tous les champs.',
    'identifiants'      => 'Numéro de téléphone ou mot de passe incorrect.',
    'compte_inactif'    => 'Votre compte est désactivé. Contactez le support.',
    'trop_tentatives'   => 'Trop de tentatives depuis cet appareil. Réessayez dans __RESTE__ minute(s).',
    'compte_bloque'     => 'Ce compte a été bloqué après plusieurs tentatives échouées. Réessayez dans __RESTE__ minute(s).',
    'csrf'              => 'Session expirée, veuillez réessayer.',
    'serveur'           => 'Erreur serveur. Veuillez réessayer.',
];

$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$reste   = isset($_GET['reste']) ? max(1, (int) $_GET['reste']) : null;
$reste_tentatives = isset($_GET['reste_tentatives']) ? (int) $_GET['reste_tentatives'] : null;

$message_erreur = $errors[$error] ?? ($error ? 'Une erreur est survenue.' : '');
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

// Ouvre automatiquement la modale si on revient ici avec une erreur/succès de connexion
$ouvrir_login = ($error || $success) ? "document.addEventListener('DOMContentLoaded', function(){ openModal('modal-login'); });" : '';
?>

<div id="modal-login" class="mr-modal hidden" onclick="if(event.target===this) closeModal('modal-login')">
  <div class="mr-modal__panel bg-white rounded-2xl shadow-2xl w-full max-w-md relative p-7 md:p-8 max-h-[90vh] overflow-y-auto">

    <button type="button" onclick="closeModal('modal-login')" aria-label="Fermer" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-mr-navy transition-colors">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>

    <h2 class="text-lg font-extrabold text-mr-navy mb-1">Connexion</h2>
    <p class="text-sm text-mr-navy-soft mb-6">Entrez votre numéro et votre mot de passe pour accéder à votre espace</p>

    <?php if ($error): ?>
      <div class="text-sm font-semibold rounded-xl px-4 py-3 mb-5 bg-red-50 text-red-600"><?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>
    <?php if ($success === 'inscription'): ?>
      <div class="text-sm font-semibold rounded-xl px-4 py-3 mb-5 bg-emerald-50 text-emerald-600">✓ Compte créé ! Vous pouvez vous connecter.</div>
    <?php endif; ?>
    <?php if ($success === 'mdp_reinitialise'): ?>
      <div class="text-sm font-semibold rounded-xl px-4 py-3 mb-5 bg-emerald-50 text-emerald-600">✓ Mot de passe réinitialisé ! Vous pouvez vous connecter.</div>
    <?php endif; ?>
    <?php if ($success === 'compte_supprime'): ?>
      <div class="text-sm font-semibold rounded-xl px-4 py-3 mb-5 bg-emerald-50 text-emerald-600">✓ Votre compte a bien été supprimé.</div>
    <?php endif; ?>

    <form method="POST" action="includs/login_handler.php" id="loginForm" novalidate class="space-y-4">

      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

      <!-- Email ou numéro de téléphone -->
      <div>
        <label for="loginPhone" class="block text-xs font-bold text-mr-navy-soft uppercase tracking-wide mb-1.5">Email ou numéro de téléphone</label>
        <div class="flex items-center gap-2.5 border border-slate-200 rounded-xl px-3.5 py-3 focus-within:border-mr-blue focus-within:ring-2 focus-within:ring-mr-blue/10 transition-shadow">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="text-slate-400 shrink-0"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.21 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <input type="text" id="loginPhone" name="identifiant"
                 placeholder="exemple@domaine.com ou 3212345"
                 autocomplete="username"
                 maxlength="150" required
                 class="flex-1 min-w-0 outline-none text-sm text-mr-navy bg-transparent placeholder:text-slate-300">
        </div>
        <p class="field-error text-xs text-red-600 font-semibold mt-1.5" id="err-loginPhone" style="display:none;"></p>
      </div>

      <!-- Mot de passe -->
      <div>
        <label for="loginCode" class="block text-xs font-bold text-mr-navy-soft uppercase tracking-wide mb-1.5">Mot de passe</label>
        <div class="flex items-center gap-2.5 border border-slate-200 rounded-xl px-3.5 py-3 focus-within:border-mr-blue focus-within:ring-2 focus-within:ring-mr-blue/10 transition-shadow">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="text-slate-400 shrink-0"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="2"/></svg>
          <input type="password" id="loginCode" name="code"
                 placeholder="Votre mot de passe"
                 autocomplete="current-password"
                 maxlength="64" required
                 class="flex-1 min-w-0 outline-none text-sm text-mr-navy bg-transparent placeholder:text-slate-300">
          <button class="eye-btn text-slate-400 hover:text-mr-blue transition-colors shrink-0" type="button" id="eyeBtn" onclick="togglePwd()" aria-label="Afficher/masquer le mot de passe">
            <svg id="iconEyeOpen" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="iconEyeOff" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
        <p class="field-error text-xs text-red-600 font-semibold mt-1.5" id="err-loginCode" style="display:none;"></p>
      </div>

      <div class="text-right -mt-2">
        <a href="/mot_de_passe_oublie.php" class="text-xs font-bold text-mr-blue hover:text-mr-blue-dark">Mot de passe oublié ?</a>
      </div>

      <button type="submit" class="w-full bg-mr-blue hover:bg-mr-blue-dark text-white font-bold text-sm py-3.5 rounded-full transition-colors">
        SE CONNECTER
      </button>
    </form>

    <div class="flex items-center gap-3 my-5">
      <div class="flex-1 h-px bg-slate-100"></div>
      <span class="text-[11px] text-slate-400 whitespace-nowrap">ou continuer avec</span>
      <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <div id="googleBtnLogin" class="flex justify-center mb-5"></div>

    <p class="text-center text-sm text-mr-navy-soft">
      Pas encore de compte ?
      <button type="button" onclick="closeModal('modal-login'); openModal('modal-register')" class="font-bold text-mr-blue hover:text-mr-blue-dark">Créer un compte</button>
    </p>
  </div>
</div>

<?php if ($ouvrir_login): ?>
<script><?= $ouvrir_login ?></script>
<?php endif; ?>

<script>
function togglePwd() {
  const input   = document.getElementById('loginCode');
  const eyeOpen = document.getElementById('iconEyeOpen');
  const eyeOff  = document.getElementById('iconEyeOff');
  if (input.type === 'password') {
    input.type = 'text';
    eyeOpen.style.display = 'none';
    eyeOff.style.display  = 'block';
  } else {
    input.type = 'password';
    eyeOpen.style.display = 'block';
    eyeOff.style.display  = 'none';
  }
}

function afficherErreurLogin(id, message) {
  const el = document.getElementById('err-' + id);
  if (!el) return;
  el.textContent = message;
  el.style.display = message ? 'block' : 'none';
}

document.getElementById('loginPhone').addEventListener('blur', function (e) {
  afficherErreurLogin('loginPhone', (e.target.value.trim().length < 5) ? 'Identifiant trop court.' : '');
});

document.getElementById('loginCode').addEventListener('blur', function (e) {
  afficherErreurLogin('loginCode', (e.target.value.length > 0 && e.target.value.length < 8) ? 'Le mot de passe doit contenir au moins 8 caractères.' : '');
});

document.getElementById('loginForm').addEventListener('submit', function (e) {
  const identifiant = document.getElementById('loginPhone').value.trim();
  const code = document.getElementById('loginCode').value.trim();
  let bloque = false;

  if (identifiant.length < 5) { afficherErreurLogin('loginPhone', 'Identifiant trop court.'); bloque = true; }
  if (code.length < 8) { afficherErreurLogin('loginCode', 'Le mot de passe doit contenir au moins 8 caractères.'); bloque = true; }

  if (bloque) e.preventDefault();
});

// ── Connexion avec Google ─────────────────────────────────────────────────
window.handleGoogleCredential = function (response) {
  const csrf = document.querySelector('#loginForm input[name="csrf_token"]').value;
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
        afficherErreurLogin('loginPhone', data.error || 'Échec de la connexion avec Google.');
      }
    })
    .catch(() => {
      afficherErreurLogin('loginPhone', 'Erreur réseau, réessayez.');
    });
};

function initGoogleButtonLogin() {
  if (!window.google || !google.accounts || !google.accounts.id) {
    setTimeout(initGoogleButtonLogin, 300);
    return;
  }
  const clientId = document.querySelector('meta[name="google-signin-client_id"]')?.content;
  if (!clientId || clientId.includes('YOUR_GOOGLE_CLIENT_ID')) return;

  google.accounts.id.initialize({ client_id: clientId, callback: handleGoogleCredential });
  google.accounts.id.renderButton(document.getElementById('googleBtnLogin'), {
    theme: 'outline', size: 'large', shape: 'pill', text: 'continue_with', width: 320
  });
}
document.addEventListener('DOMContentLoaded', initGoogleButtonLogin);
</script>