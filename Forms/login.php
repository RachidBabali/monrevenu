<?php
/**
 * login.php — Formulaire de connexion sécurisé
 * À placer dans : Forms/login.php
 * Inclus depuis : index.php (racine)
 */

// Générer token CSRF si absent
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Messages d'erreur
$errors = [
    'champs_manquants' => 'Veuillez remplir tous les champs.',
    'identifiants'      => 'Numéro de téléphone ou code secret incorrect.',
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

// Ajoute le nombre de tentatives restantes au message "identifiants incorrects"
if ($error === 'identifiants' && $reste_tentatives !== null) {
    if ($reste_tentatives > 0) {
        $message_erreur .= ' Il vous reste ' . $reste_tentatives . ' tentative' . ($reste_tentatives > 1 ? 's' : '') . ' avant blocage temporaire.';
    } else {
        $message_erreur .= ' Attention, un nouvel échec bloquera cet appareil temporairement.';
    }
}
?>

<!-- CARD CONNEXION -->
<div class="card-wrap">
  <div class="card">
    <div class="card-title">Connexion</div>
    <div class="card-hint">Entrez votre numéro et votre code secret pour accéder à votre espace</div>

    <?php if ($error): ?>
      <div class="msg err" style="display:block;"><?= htmlspecialchars($message_erreur) ?></div>
    <?php endif; ?>
    <?php if ($success === 'inscription'): ?>
      <div class="msg ok" style="display:block;">✓ Compte créé ! Vous pouvez vous connecter.</div>
    <?php endif; ?>
    <?php if ($success === 'mdp_reinitialise'): ?>
      <div class="msg ok" style="display:block;">✓ Code secret réinitialisé ! Vous pouvez vous connecter.</div>
    <?php endif; ?>

    <form method="POST" action="/includs/login_handler.php" id="loginForm" novalidate>

      <!-- Token CSRF -->
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

      <!-- Numéro de téléphone -->
      <div class="field">
        <label for="loginPhone">Numéro de téléphone</label>
        <div class="field-row">
          <span class="field-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.21 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </span>
          <input type="tel" id="loginPhone" name="phone" 
                 placeholder="Ex: 3212345" 
                 autocomplete="username" 
                 inputmode="tel" 
                 maxlength="20" required>
        </div>
        <p class="field-error" id="err-loginPhone" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
      </div>

      <!-- Code secret -->
      <div class="field">
        <label for="loginCode">Code secret</label>
        <div class="field-row">
          <span class="field-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <input type="password" id="loginCode" name="code" 
                 placeholder="A1B2" 
                 autocomplete="current-password" 
                 maxlength="4" required
                 style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;">
          <button class="eye-btn" type="button" id="eyeBtn" onclick="togglePwd()" aria-label="Afficher/masquer le code">
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
        <p class="field-error" id="err-loginCode" style="color:#dc2626; font-size:11px; margin-top:4px; font-weight:600; display:none;"></p>
      </div>

      <!-- Mot de passe oublié -->
      <div class="forgot-row">
        <a href="/mot_de_passe_oublie.php">Code secret oublié ?</a>
      </div>

      <!-- Bouton -->
      <button type="submit" class="btn-submit">SE CONNECTER</button>

    </form>

    <!-- Séparateur -->
    <div class="divider">
      <div class="divider-line"></div>
      <span class="divider-txt">ou continuer avec</span>
      <div class="divider-line"></div>
    </div>
    
    <div class="bottom-note">
      Pas encore de compte ?
<a href="/inscription.php">Créer un compte</a>
    </div>

    <p class="legal">
      En créant un compte, vous acceptez nos <a href="/conditions.php">conditions d'utilisation</a>
      et notre <a href="/confidentialite.php">politique de confidentialité</a>
    </p>
  </div>
</div>

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
document.getElementById('loginCode').addEventListener('input', function (e) {
  e.target.value = e.target.value.toUpperCase();
});

function afficherErreurLogin(id, message) {
  const el = document.getElementById('err-' + id);
  if (!el) return;
  el.textContent = message;
  el.style.display = message ? 'block' : 'none';
}

document.getElementById('loginPhone').addEventListener('blur', function (e) {
  const nettoye = e.target.value.replace(/\D/g, '');
  afficherErreurLogin('loginPhone', (nettoye.length < 6) ? 'Numéro de téléphone trop court.' : '');
});

document.getElementById('loginCode').addEventListener('blur', function (e) {
  afficherErreurLogin('loginCode', (e.target.value.length > 0 && e.target.value.length !== 4) ? 'Le code fait 4 caractères.' : '');
});

document.getElementById('loginForm').addEventListener('submit', function (e) {
  const phone = document.getElementById('loginPhone').value.replace(/\D/g, '');
  const code = document.getElementById('loginCode').value.trim();
  let bloque = false;

  if (phone.length < 6) { afficherErreurLogin('loginPhone', 'Numéro de téléphone trop court.'); bloque = true; }
  if (code.length !== 4) { afficherErreurLogin('loginCode', 'Le code fait 4 caractères.'); bloque = true; }

  if (bloque) e.preventDefault();
});
</script>