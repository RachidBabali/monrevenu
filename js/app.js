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

// Enregistrement du service worker (rend le site installable / PWA)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .catch((err) => console.warn('Échec enregistrement service worker :', err));
  });
}