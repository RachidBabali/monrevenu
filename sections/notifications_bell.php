<?php
// Cloche de notifications (comportement : assets/js/notifications.js). Classes notif-* conservees.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . '/../includs/ui.php';
?>
<div class="relative" data-notif-bell>
  <button type="button" class="notif-bell-toggle btn btn-icone btn-discret relative text-text-2" aria-label="Notifications" aria-expanded="false" aria-controls="notif-panel">
    <?= ico('bell') ?>
    <span class="notif-badge absolute right-1.5 top-1.5 hidden h-4 min-w-[16px] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold leading-none text-on-primary chiffres">0</span>
  </button>

  <div id="notif-panel" class="notif-panel carte absolute right-0 z-40 mt-2 hidden w-80 max-w-[calc(100vw-32px)] overflow-hidden shadow-pop">
    <div class="flex items-center justify-between gap-3 border-b border-line py-2 pl-4 pr-2">
      <p class="text-sm font-semibold">Notifications</p>
      <button type="button" class="notif-marquer-tout btn btn-sm btn-discret">Tout marquer comme lu</button>
    </div>
    <div class="notif-liste max-h-96 overflow-y-auto">
      <p class="notif-vide px-4 py-8 text-center text-sm text-text-3">Aucune notification.</p>
    </div>
    <button type="button" class="notif-activer-push hidden w-full border-t border-line px-4 py-3 text-sm font-medium text-primary-ink hover:bg-surface-2">
      Recevoir les notifications sur ce téléphone
    </button>
    <a href="/page/messagerie.php" class="block border-t border-line px-4 py-3 text-center text-sm font-medium text-primary-ink hover:bg-surface-2">Voir tous les messages</a>
  </div>
</div>
