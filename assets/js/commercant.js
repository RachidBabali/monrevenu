// Espace commercant : commission affichee en direct sous le prix saisi (meme regle que le serveur).
(function () {
  'use strict';
  var prix = document.getElementById('prix');
  var sortie = document.getElementById('commission-calculee');
  if (!prix || !sortie) return;

  var seuil = parseInt(prix.getAttribute('data-seuil'), 10) || 10000;
  var basse = parseInt(prix.getAttribute('data-basse'), 10) || 500;
  var haute = parseInt(prix.getAttribute('data-haute'), 10) || 1000;
  var devise = sortie.getAttribute('data-devise') || 'FCFA';

  function formater(n) {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ' + devise;
  }
  function maj() {
    var v = parseFloat(String(prix.value).replace(',', '.'));
    if (!isFinite(v) || v <= 0) { sortie.textContent = formater(basse) + ' ou ' + formater(haute); return; }
    sortie.textContent = formater(v <= seuil ? basse : haute);
  }
  prix.addEventListener('input', maj);
  maj();
})();
