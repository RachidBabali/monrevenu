// Banniere de verification WhatsApp : compte a rebours, copie du code, nouveau code.
(function () {
  'use strict';
  var bloc = document.querySelector('.mr-verif-banner');
  if (!bloc) return;

  var numeroBusiness = bloc.getAttribute('data-numero');
  var expireAtMs = parseInt(bloc.getAttribute('data-expire'), 10) || 0;
  var elCode = document.getElementById('mr-code-verif');
  var elCompteARebours = document.getElementById('mr-compte-a-rebours');
  var elLienWame = document.getElementById('mr-lien-wame');
  var btnCopier = document.getElementById('mr-btn-copier');
  var btnRegenerer = document.getElementById('mr-btn-regenerer');
  var intervalId;

  function majLienWame(code) {
    elLienWame.href = 'https://wa.me/' + numeroBusiness + '?text=' + encodeURIComponent(code);
  }

  function tickCompteARebours() {
    var restantSec = Math.max(0, Math.round((expireAtMs - Date.now()) / 1000));
    var m = String(Math.floor(restantSec / 60)).padStart(2, '0');
    var s = String(restantSec % 60).padStart(2, '0');
    elCompteARebours.textContent = m + ':' + s;
    if (restantSec === 0) {
      elCompteARebours.textContent = '00:00, demandez un nouveau code';
      clearInterval(intervalId);
    }
  }
  intervalId = setInterval(tickCompteARebours, 1000);
  tickCompteARebours();

  btnCopier.addEventListener('click', function () {
    var libelle = btnCopier.querySelector('[data-libelle]');
    (window.MR ? MR.copier(elCode.textContent.trim()) : navigator.clipboard.writeText(elCode.textContent.trim())).then(function () {
      libelle.textContent = 'Code copié';
      setTimeout(function () { libelle.textContent = 'Copier'; }, 2000);
    });
  });

  btnRegenerer.addEventListener('click', function () {
    var libelle = btnRegenerer.querySelector('[data-libelle]');
    btnRegenerer.disabled = true;
    libelle.textContent = 'Envoi en cours';
    fetch('/includs/regenerer_code_whatsapp.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
      }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          elCode.textContent = data.code;
          expireAtMs = data.expire_at_ms;
          majLienWame(data.code);
          clearInterval(intervalId);
          intervalId = setInterval(tickCompteARebours, 1000);
          tickCompteARebours();
          if (window.MR) MR.toast('Nouveau code généré.');
        } else if (window.MR) {
          MR.toast(data.message || 'Impossible de générer un nouveau code. Réessayez dans quelques secondes.');
        }
      })
      .catch(function () { if (window.MR) MR.toast('Connexion impossible. Vérifiez votre réseau et réessayez.'); })
      .finally(function () {
        btnRegenerer.disabled = false;
        libelle.textContent = 'Nouveau code';
      });
  });
})();
