// Feuille de retrait : ouverture automatique, recapitulatif en direct, verification avant envoi.
(function () {
  'use strict';
  var feuille = document.getElementById('retraitModal');
  if (!feuille) return;

  var champ = document.getElementById('r_montant');
  var erreur = document.getElementById('r_montant_err');
  var recapMontant = document.getElementById('r_recap_montant');
  var recapSolde = document.getElementById('r_recap_solde');
  var solde = parseFloat(champ.getAttribute('data-solde')) || 0;
  var minimum = parseFloat(champ.getAttribute('data-minimum')) || 0;

  function fcfa(v) {
    var n = Math.round(Math.abs(v)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return (v < 0 ? '-' : '') + n + ' FCFA';
  }

  function verifier(afficher) {
    var v = parseFloat(champ.value) || 0;
    recapMontant.textContent = fcfa(v);
    recapSolde.textContent = fcfa(solde - v);
    var message = '';
    if (!champ.value) message = 'Saisissez le montant à retirer.';
    else if (v < minimum) message = 'Le minimum de retrait est de ' + fcfa(minimum) + '.';
    else if (v > solde) message = 'Ce montant dépasse votre solde de ' + fcfa(solde) + '.';
    if (afficher) {
      erreur.hidden = !message;
      erreur.querySelector('span').textContent = message;
      champ.setAttribute('aria-invalid', message ? 'true' : 'false');
    }
    return !message;
  }

  champ.addEventListener('input', function () { verifier(champ.getAttribute('aria-invalid') === 'true'); });
  ['r_methode', 'r_numero'].forEach(function (id) {
    var el = document.getElementById(id);
    el.addEventListener('change', function () {
      if (el.value.trim()) { el.setAttribute('aria-invalid', 'false'); document.getElementById(id + '_err').hidden = true; }
    });
  });
  champ.addEventListener('blur', function () { if (champ.value) verifier(true); });

  document.getElementById('retraitForm').addEventListener('submit', function (e) {
    var ok = verifier(true);
    var form = e.target;
    ['r_methode', 'r_numero'].forEach(function (id) {
      var el = document.getElementById(id);
      var vide = !el.value.trim();
      el.setAttribute('aria-invalid', vide ? 'true' : 'false');
      document.getElementById(id + '_err').hidden = !vide;
      if (vide) ok = false;
    });
    if (!ok) {
      e.preventDefault();
      var premier = form.querySelector('[aria-invalid="true"]');
      if (premier) premier.focus();
    }
  }, true);

  var demande = location.hash === '#retrait' && !feuille.hasAttribute('data-envoye')
    && !champ.form.querySelector('button[type="submit"]').disabled;
  if (location.hash === '#retrait') history.replaceState(null, '', location.pathname + location.search);
  if (feuille.hasAttribute('data-ouvrir-auto') || demande) {
    window.addEventListener('DOMContentLoaded', function () { if (window.MR) MR.ouvrir('retraitModal'); });
  }
})();
