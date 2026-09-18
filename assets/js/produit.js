// Page produit publique : total en direct et verification du formulaire (la page fonctionne sans ce script).
(function () {
  'use strict';
  var form = document.getElementById('form-commande');
  if (!form) return;

  var quantite = document.getElementById('quantite');
  var prix = parseFloat(quantite.getAttribute('data-prix')) || 0;

  function fcfa(v) {
    return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
  }
  function majTotal() {
    var q = Math.max(1, parseInt(quantite.value, 10) || 1);
    document.getElementById('recap-quantite').textContent = q;
    document.getElementById('recap-total').textContent = fcfa(q * prix);
  }
  quantite.addEventListener('input', majTotal);

  function erreur(id, message) {
    var el = document.getElementById('err-' + id);
    el.textContent = message;
    el.hidden = !message;
    document.getElementById(id).setAttribute('aria-invalid', message ? 'true' : 'false');
    return !message;
  }

  var regles = {
    nom_client: function (v) { return v.trim() ? '' : 'Indiquez votre nom.'; },
    telephone_client: function (v) {
      var n = v.replace(/[^\d+]/g, '');
      return /^(\+221|00221)?7[0-8]\d{7}$/.test(n) || /^(\+269|00269)?[34]\d{6}$/.test(n)
        ? '' : 'Numéro invalide. Exemple pour le Sénégal : 77 123 45 67.';
    }
  };

  Object.keys(regles).forEach(function (id) {
    var el = document.getElementById(id);
    el.addEventListener('blur', function () { if (el.value) erreur(id, regles[id](el.value)); });
  });

  form.addEventListener('submit', function (e) {
    var ok = true;
    Object.keys(regles).forEach(function (id) {
      if (!erreur(id, regles[id](document.getElementById(id).value))) ok = false;
    });
    if (!ok) {
      e.preventDefault();
      form.querySelector('[aria-invalid="true"]').focus();
    }
  }, true);
})();
