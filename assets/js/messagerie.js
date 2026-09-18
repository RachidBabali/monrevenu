// Messagerie : marquer un message ou tous les messages comme lus.
(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function marquerVisuel(carte) {
    carte.classList.remove('bg-primary-soft');
    carte.removeAttribute('data-non-lu');
    var id = carte.getAttribute('data-message-id');
    var pastille = document.getElementById('pastille-' + id);
    if (pastille) pastille.remove();
    var bouton = carte.querySelector('[data-marquer-lu]');
    if (bouton) bouton.remove();
  }

  document.addEventListener('click', function (e) {
    var bouton = e.target.closest('[data-marquer-lu]');
    if (!bouton) return;
    var id = bouton.getAttribute('data-marquer-lu');
    fetch('/page/marquer-lu.php?id=' + encodeURIComponent(id))
      .then(function (r) { if (r.ok) marquerVisuel(document.getElementById('carte-message-' + id)); })
      .catch(function () { MR.toast('Action impossible. Vérifiez votre connexion.'); });
  });

  var tout = document.getElementById('tout-marquer-lu');
  if (tout) {
    tout.addEventListener('click', function () {
      fetch('/includs/notifications_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'marquer_tout_lu', csrf_token: csrf })
      }).then(function (r) {
        if (!r.ok) throw new Error();
        document.querySelectorAll('[data-non-lu]').forEach(marquerVisuel);
        tout.remove();
        document.getElementById('resume-messages').textContent = 'Tous vos messages sont lus.';
      }).catch(function () { MR.toast('Action impossible. Vérifiez votre connexion.'); });
    });
  }
})();
