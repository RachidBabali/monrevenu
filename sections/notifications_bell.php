<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<div class="relative" data-notif-bell>
  <button type="button" class="notif-bell-toggle relative w-9 h-9 lg:w-10 lg:h-10 rounded-full lg:rounded-xl bg-white/15 lg:bg-slate-50 lg:dark:bg-slate-800 lg:border lg:border-slate-200 lg:dark:border-slate-700 flex items-center justify-center" aria-label="Notifications">
    <svg class="w-4 h-4 lg:w-[18px] lg:h-[18px] text-white lg:text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <span class="notif-badge hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold items-center justify-center">0</span>
  </button>

  <div class="notif-panel hidden absolute right-0 mt-2 w-80 max-w-[90vw] bg-white dark:bg-[#141E33] border border-slate-100 dark:border-slate-800 rounded-2xl shadow-xl z-50 overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-800">
      <p class="font-bold text-[13px]">Notifications</p>
      <button type="button" class="notif-marquer-tout text-[11px] font-semibold text-brand hover:underline">Tout marquer lu</button>
    </div>
    <div class="notif-liste max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
      <p class="notif-vide px-4 py-6 text-center text-[12px] text-slate-400">Aucune notification pour le moment.</p>
    </div>
    <button type="button" class="notif-activer-push hidden w-full text-center text-[11px] font-semibold text-brand py-2.5 border-t border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
      🔔 Activer les notifications push
    </button>
  </div>
</div>