// Profil : verification de la confirmation du nouveau mot de passe.
(function () {
  'use strict';
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
