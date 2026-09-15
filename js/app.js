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

// ── Cloche de notifications + abonnement aux notifications push ───────────
// Ne s'active que sur les pages qui incluent le composant
// sections/notifications_bell.php (identifiable par [data-notif-bell]).
(function initNotifications() {
  const cloches = document.querySelectorAll('[data-notif-bell]');
  if (!cloches.length) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]')?.content || '';

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
  }

  function rafraichirCloche(cloche) {
    fetch('/includs/notifications_api.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;
        const badge = cloche.querySelector('.notif-badge');
        if (data.unread > 0) {
          badge.textContent = data.unread > 99 ? '99+' : data.unread;
          badge.classList.remove('hidden');
          badge.classList.add('flex');
        } else {
          badge.classList.add('hidden');
          badge.classList.remove('flex');
        }

        const liste = cloche.querySelector('.notif-liste');
        if (!data.notifications.length) {
          liste.innerHTML = '<p class="notif-vide px-4 py-6 text-center text-[12px] text-slate-400">Aucune notification pour le moment.</p>';
          return;
        }
        liste.innerHTML = data.notifications.map((n) => `
          <button type="button" data-id="${n.id}" data-lu="${n.non_lu ? '0' : '1'}"
            class="notif-item w-full text-left px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors ${n.non_lu ? 'bg-brand/5' : ''}">
            <p class="text-[12.5px] text-slate-700 dark:text-slate-200 leading-snug">${n.message}</p>
            <p class="text-[10px] text-slate-400 mt-1">${n.date}</p>
          </button>
        `).join('');
      })
      .catch(() => {});
  }

  function activerAbonnementPush(cloche) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !vapidPublicKey) return;

    navigator.serviceWorker.ready.then((registration) => {
      registration.pushManager.getSubscription().then((subExistant) => {
        if (subExistant) return; // déjà abonné sur cet appareil
        const boutonActiver = cloche.querySelector('.notif-activer-push');
        if (Notification.permission === 'denied') return; // l'utilisateur a déjà refusé
        boutonActiver?.classList.remove('hidden');
      });
    });
  }

  function demanderAbonnementPush(cloche) {
    navigator.serviceWorker.ready.then((registration) => {
      registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
      }).then((subscription) => {
        return fetch('/includs/push_subscribe.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'subscribe', csrf_token: csrfToken, ...subscription.toJSON() })
        });
      }).then(() => {
        cloche.querySelector('.notif-activer-push')?.classList.add('hidden');
      }).catch((err) => console.warn('Abonnement push refusé ou échoué :', err));
    });
  }

  cloches.forEach((cloche) => {
    const toggle = cloche.querySelector('.notif-bell-toggle');
    const panel = cloche.querySelector('.notif-panel');

    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const estOuvert = !panel.classList.contains('hidden');
      document.querySelectorAll('.notif-panel').forEach((p) => p.classList.add('hidden'));
      if (!estOuvert) {
        panel.classList.remove('hidden');
        rafraichirCloche(cloche);
      }
    });

    cloche.querySelector('.notif-marquer-tout').addEventListener('click', () => {
      fetch('/includs/notifications_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'marquer_tout_lu', csrf_token: csrfToken })
      }).then(() => rafraichirCloche(cloche));
    });

    cloche.querySelector('.notif-liste').addEventListener('click', (e) => {
      const item = e.target.closest('.notif-item');
      if (!item || item.dataset.lu === '1') return;
      fetch('/page/marquer-lu.php?id=' + item.dataset.id).then(() => rafraichirCloche(cloche));
    });

    cloche.querySelector('.notif-activer-push').addEventListener('click', () => demanderAbonnementPush(cloche));

    rafraichirCloche(cloche);
    activerAbonnementPush(cloche);
    setInterval(() => rafraichirCloche(cloche), 30000);
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.notif-panel').forEach((p) => p.classList.add('hidden'));
  });
})();