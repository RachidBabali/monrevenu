/* ============================================================
   MonRevenu — js/app.js
   Gestion des modales (connexion / inscription) et du menu mobile.
   Les modales elles-mêmes (HTML) seront ajoutées dans les
   sections "Formulaire de connexion" et "Formulaire d'inscription".
   ============================================================ */

function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove('hidden');
  requestAnimationFrame(() => modal.classList.add('is-open'));
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove('is-open');
  setTimeout(() => modal.classList.add('hidden'), 200);
  document.body.style.overflow = '';
}

function closeAllModals() {
  document.querySelectorAll('.mr-modal').forEach((modal) => {
    modal.classList.remove('is-open');
    setTimeout(() => modal.classList.add('hidden'), 200);
  });
  document.body.style.overflow = '';
}

// Fermeture avec Échap
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeAllModals();
});

// Menu mobile
function toggleMobileMenu() {
  const menu = document.getElementById('mrMobileMenu');
  const btn = document.getElementById('mrMobileBtn');
  if (!menu) return;
  const isOpen = !menu.classList.contains('hidden');
  menu.classList.toggle('hidden');
  btn?.setAttribute('aria-expanded', String(!isOpen));
}

// ── Connexion / inscription avec Google (initialisation unique) ───────────
// IMPORTANT : google.accounts.id.initialize() ne doit être appelé qu'UNE
// SEULE fois par page. L'appeler deux fois (une pour la modale de connexion,
// une pour celle d'inscription) fait planter la bibliothèque interne de
// Google (erreur "Cannot read properties of undefined (reading 'Ab')" dans
// credential_button_library) et laisse un verrou de mise en page
// (overflow: hidden sur <html>) posé pour toujours, bloquant le scroll de
// toute la page. Ne pas dupliquer cette initialisation dans les modales.

function handleGoogleCredential(response) {
  const form = document.getElementById('loginForm') || document.getElementById('registerForm');
  const csrf = form ? form.querySelector('input[name="csrf_token"]').value : '';

  fetch('includs/google_auth_handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'credential=' + encodeURIComponent(response.credential) + '&csrf_token=' + encodeURIComponent(csrf)
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        window.location.href = data.redirect || '/dashboard.php';
        return;
      }
      const message = data.error || 'Échec de la connexion avec Google.';
      const err = document.getElementById('err-loginPhone');
      if (err) {
        err.textContent = message;
        err.style.display = 'block';
      } else {
        alert(message);
      }
    })
    .catch(() => {
      const err = document.getElementById('err-loginPhone');
      if (err) {
        err.textContent = 'Erreur réseau, réessayez.';
        err.style.display = 'block';
      } else {
        alert('Erreur réseau, réessayez.');
      }
    });
}

function initGoogleSignIn() {
  if (!window.google || !google.accounts || !google.accounts.id) {
    setTimeout(initGoogleSignIn, 300);
    return;
  }
  const clientId = document.querySelector('meta[name="google-signin-client_id"]')?.content;
  if (!clientId || clientId.includes('YOUR_GOOGLE_CLIENT_ID')) return;

  // Un seul appel à initialize() pour toute la page.
  google.accounts.id.initialize({ client_id: clientId, callback: handleGoogleCredential });

  const loginContainer = document.getElementById('googleBtnLogin');
  if (loginContainer) {
    google.accounts.id.renderButton(loginContainer, {
      theme: 'outline', size: 'large', shape: 'pill', text: 'continue_with', width: 320
    });
  }

  const registerContainer = document.getElementById('googleBtnRegister');
  if (registerContainer) {
    google.accounts.id.renderButton(registerContainer, {
      theme: 'outline', size: 'large', shape: 'pill', text: 'signup_with', width: 320
    });
  }
}
document.addEventListener('DOMContentLoaded', initGoogleSignIn);

// ── Filet de sécurité anti-verrouillage de scroll ──────────────────────────
// Le SDK Google Identity Services (notamment son flux FedCM, en cours de
// migration côté Google) peut poser un verrou de mise en page sur <html> ou
// <body> (overflow caché, hauteur figée) le temps d'afficher son interface,
// et ne jamais le retirer si ce flux reste bloqué (cas connu quand les
// cookies tiers sont bloqués, ex. Brave par défaut). Ce filet de sécurité
// détecte un tel verrou et le retire automatiquement tant qu'aucune de nos
// propres modales (.mr-modal.is-open) n'est réellement ouverte.
function restaurerScrollSiNecessaire() {
  if (document.querySelector('.mr-modal.is-open')) return;
  [document.documentElement, document.body].forEach((el) => {
    const style = getComputedStyle(el);
    if (style.overflow === 'hidden' || style.overflowY === 'hidden' || style.overflowX === 'hidden') {
      el.style.setProperty('overflow', 'visible', 'important');
      el.style.setProperty('height', 'auto', 'important');
    }
  });
}
new MutationObserver(restaurerScrollSiNecessaire).observe(document.documentElement, {
  attributes: true, attributeFilter: ['style', 'class']
});
new MutationObserver(restaurerScrollSiNecessaire).observe(document.body, {
  attributes: true, attributeFilter: ['style', 'class']
});
setInterval(restaurerScrollSiNecessaire, 1000);

// Enregistrement du service worker (rend le site installable / PWA)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .catch((err) => console.warn('Échec enregistrement service worker :', err));
  });
}