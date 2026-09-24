// Espace commercant : prix affiche au client, calcule par le serveur sous le prix net saisi.
(function () {
  'use strict';
  var prix = document.getElementById('prix');
  var sortie = document.getElementById('prix-final-calcule');
  if (!prix || !sortie) return;

  var devise = prix.getAttribute('data-devise') || 'FCFA';
  var minuterie = null;

  function formater(n) {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ' + devise;
  }
  function maj() {
    var v = String(prix.value).replace(',', '.');
    fetch('/commercant/prix_final.php?net=' + encodeURIComponent(v), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { sortie.textContent = d && d.ok ? formater(d.prix_final) : '—'; })
      .catch(function () { sortie.textContent = '—'; });
  }
  prix.addEventListener('input', function () { clearTimeout(minuterie); minuterie = setTimeout(maj, 250); });
})();
