// Catalogue : le tri s'applique des qu'on change la liste ; produit cible mis en evidence.
(function () {
  'use strict';
  var tri = document.querySelector('[data-envoi-auto]');
  if (tri) tri.addEventListener('change', function () { tri.form.submit(); });

  var cible = location.hash && document.querySelector(location.hash);
  if (cible && cible.classList.contains('produit')) {
    cible.classList.add('border-primary');
    cible.setAttribute('tabindex', '-1');
    cible.focus({ preventScroll: false });
  }
  document.querySelectorAll('input[id^="lien-input-"]').forEach(function (champ) {
    champ.addEventListener('focus', function () { champ.select(); });
  });
})();
