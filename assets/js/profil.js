// Profil : code secret en majuscules et verification de la confirmation.
(function () {
  'use strict';
  ['mot_de_passe_actuel', 'nouveau_mot_de_passe', 'confirmation_mot_de_passe'].forEach(function (id) {
    var champ = document.getElementById(id);
    if (champ) champ.addEventListener('input', function () { champ.value = champ.value.toUpperCase(); });
  });
  var form = document.getElementById('form-password');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    var nouveau = document.getElementById('nouveau_mot_de_passe');
    var confirmation = document.getElementById('confirmation_mot_de_passe');
    var erreur = document.getElementById('err-confirmation-code');
    var different = nouveau.value !== confirmation.value;
    erreur.hidden = !different;
    confirmation.setAttribute('aria-invalid', different ? 'true' : 'false');
    if (different) { e.preventDefault(); confirmation.focus(); }
  }, true);
})();
