<?php
/**
 * sections/activer_notifications.php : bloc d'activation des notifications, visible dans l'espace connecte.
 * L'abonnement ne part jamais au chargement : il faut un clic de l'utilisateur (regle des navigateurs).
 * Etats geres par assets/js/notifications.js : non demande, autorise, refuse, non pris en charge, indisponible.
 */
require_once __DIR__ . '/../includs/ui.php';
$vapid_present = function_exists('env') ? (string) env('VAPID_PUBLIC_KEY', '') !== '' : false;
?>
<section class="carte flex flex-col gap-3 p-4" data-bloc-push data-vapid="<?= $vapid_present ? '1' : '0' ?>" aria-labelledby="t-push">
  <div class="flex items-start gap-3">
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary-ink"><?= ico('bell') ?></span>
    <div class="min-w-0 flex-1">
      <h2 id="t-push" class="text-base font-semibold text-text">Notifications sur cet appareil</h2>
      <p class="mt-1 text-sm text-text-2" data-push-texte>Recevez une alerte quand une commande arrive, quand une commission est créditée ou quand un retrait est payé.</p>
      <p class="mt-2 hidden text-sm text-text-2" data-push-aide-ios>Sur iPhone, ajoutez d'abord MonRevenu à votre écran d'accueil : bouton Partager, puis « Sur l'écran d'accueil ». Ouvrez ensuite l'application depuis l'icône.</p>
      <p class="mt-2 hidden text-sm text-text-2" data-push-aide-refus>Les notifications sont bloquées pour ce site. Ouvrez les réglages du navigateur, section Notifications, autorisez monrevenu.xyz, puis revenez ici.</p>
    </div>
  </div>
  <div class="flex flex-wrap gap-2">
    <button type="button" class="btn btn-sm btn-primaire hidden" data-push-activer><?= ico('bell', 'ico-16') ?>Activer les notifications</button>
    <button type="button" class="btn btn-sm btn-secondaire hidden" data-push-test><?= ico('megaphone', 'ico-16') ?>M'envoyer un essai</button>
    <button type="button" class="btn btn-sm btn-discret hidden" data-push-desactiver><?= ico('eye-off', 'ico-16') ?>Désactiver sur cet appareil</button>
  </div>
</section>
