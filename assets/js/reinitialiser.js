// Saisie du code secret en majuscules, comme a l'enregistrement.
['nouveau_code', 'confirmation_code'].forEach(function (id) {
  var champ = document.getElementById(id);
  if (champ) champ.addEventListener('input', function () { champ.value = champ.value.toUpperCase(); });
});
