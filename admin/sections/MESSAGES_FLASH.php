<?php if ($message_affiche !== ''): ?>
  <p class="alerte alerte-succes" role="status"><?= ico('circle-check') ?><span><?= e($message_affiche) ?></span></p>
<?php endif; ?>
<?php if ($erreur_affichee !== ''): ?>
  <p class="alerte alerte-danger" role="alert"><?= ico('circle-alert') ?><span><?= e($erreur_affichee) ?></span></p>
<?php endif; ?>
