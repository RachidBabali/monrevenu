<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
exigerConnexion();

$user_id       = $_SESSION['user_id'];
$user_fullname = $_SESSION['user_fullname'] ?? 'Utilisateur';
$user_initials = strtoupper(substr($user_fullname, 0, 2));
$prenom        = explode(' ', $user_fullname)[0];

// Solde + phone + rôle (requis par wallet.php) + phone_verified (bannière WhatsApp)
$stmt = $pdo->prepare("SELECT balance, role, phone, phone_verified FROM users_monrevenu WHERE id = ?");
$stmt->execute([$user_id]);
$sender         = $stmt->fetch();
$balance        = $sender['balance'] ?? 0;
$role           = $sender['role'] ?? 'client';
$phone_verifie  = (int) ($sender['phone_verified'] ?? 0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="theme-color" content="#1246A0"/>
  <meta name="mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-capable" content="yes"/>
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
  <meta name="vapid-public-key" content="<?= htmlspecialchars(env('VAPID_PUBLIC_KEY', '')) ?>"/>
  <title>MonRevenu – Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sora:['Sora','sans-serif']},colors:{brand:{DEFAULT:'#1246A0',mid:'#1A5FCC',light:'#3B82F6',soft:'#EEF4FF'}}}}}</script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    body{font-family:'Sora',sans-serif;}
    .scrollbar-hide::-webkit-scrollbar{display:none;}
    .scrollbar-hide{-ms-overflow-style:none;scrollbar-width:none;}
  </style>
</head>
<body class="bg-slate-50 dark:bg-[#0B1120] text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-300">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/sections/navbar.php'; ?>

<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>

<div class="lg:ml-64 min-h-screen flex flex-col">

  <header class="hidden lg:flex items-center justify-between bg-white dark:bg-[#141E33] border-b border-slate-100 dark:border-slate-800 px-8 h-16 sticky top-0 z-30">
    <h1 class="font-bold text-[16px]">Tableau de bord</h1>
    <div class="flex items-center gap-2">
      <button onclick="openSearch()" class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-[13px] text-slate-400">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Rechercher...
      </button>
      <?php include $_SERVER['DOCUMENT_ROOT'] . '/sections/notifications_bell.php'; ?>
      <button onclick="toggleTheme()" class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center">
        <svg class="w-[18px] h-[18px] text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
      </button>
    </div>
  </header>

  <header class="lg:hidden bg-[#1246A0] px-4 pt-4 pb-16 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <button onclick="toggleSidebar()" class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <p class="text-white font-semibold text-[14px]">Bonjour, <?= htmlspecialchars($prenom) ?> 👋</p>
        <p class="text-white/60 text-[11px]">MonRevenu</p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <button onclick="openSearch()" class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </button>
      <?php include $_SERVER['DOCUMENT_ROOT'] . '/sections/notifications_bell.php'; ?>
      <button onclick="toggleTheme()" class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg>
      </button>
    </div>
  </header>

  <main class="flex-1 pb-24 lg:pb-8">

    <?php if (!empty($message_success)): ?>
      <div role="status" class="mx-4 lg:mx-8 mt-4 flex items-center gap-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-400 rounded-2xl p-4">
        <p class="text-[13px] font-semibold"><?= htmlspecialchars($message_success) ?></p>
      </div>
    <?php endif; ?>
    <?php if (!empty($message_error)): ?>
      <div role="alert" class="mx-4 lg:mx-8 mt-4 flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-600 dark:text-red-400 rounded-2xl p-4">
        <p class="text-[13px] font-semibold"><?= htmlspecialchars($message_error) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($phone_verifie !== 1): ?>
      <div class="mx-4 lg:mx-8 mt-4">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/sections/banniere_verification_whatsapp.php'; ?>
      </div>
    <?php endif; ?>

    <div class="hidden lg:block px-8 pt-6 pb-2">
      <h2 class="text-[22px] font-bold">Bonjour, <?= htmlspecialchars($user_fullname) ?> 👋</h2>
      <p class="text-slate-400 text-[14px] mt-1"><?= date('d F Y') ?></p>
    </div>

    <div class="lg:ml-0 -mt-10 lg:mt-0 relative z-10 lg:grid lg:grid-cols-[1fr_360px] lg:gap-6 lg:px-8 lg:pt-4">

      <div>
        <?php include __DIR__ . '/sections/wallet.php'; ?>
        <?php include __DIR__ . '/sections/services.php'; ?>
      </div>

      <div class="lg:pt-4">
        <?php include __DIR__ . '/sections/jeux.php'; ?>
      </div>

    </div>

  </main>
       <?php include __DIR__ . '/sections/navbar.php'; ?>

</div>

<div id="searchOverlay" class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-20 opacity-0 pointer-events-none transition-opacity duration-200" onclick="closeSearch(event)">
  <div class="bg-white dark:bg-[#141E33] rounded-2xl w-full max-w-lg mx-4 p-5 shadow-2xl">
    <div class="flex items-center gap-3 border-2 border-[#1246A0] rounded-xl px-4 py-2.5">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#1246A0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="searchInput" type="text" placeholder="Rechercher un service..." class="flex-1 bg-transparent outline-none text-[15px] text-slate-800 dark:text-white placeholder-slate-400"/>
    </div>
    <div class="flex flex-wrap gap-2 mt-3">
      <?php foreach(['Taxi','Cosmétique','Vêtements','Parfums','Formation'] as $tag): ?>
      <span class="bg-slate-100 dark:bg-slate-700 text-slate-500 text-[12px] px-3 py-1.5 rounded-full cursor-pointer hover:bg-blue-50 hover:text-[#1246A0] transition-colors"><?= $tag ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function toggleTheme(){document.documentElement.classList.toggle('dark');localStorage.setItem('theme',document.documentElement.classList.contains('dark')?'dark':'light');}
(function(){if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark');})();
function openSearch(){document.getElementById('searchOverlay').classList.remove('opacity-0','pointer-events-none');setTimeout(()=>document.getElementById('searchInput').focus(),100);}
function closeSearch(e){if(e.target===document.getElementById('searchOverlay'))document.getElementById('searchOverlay').classList.add('opacity-0','pointer-events-none');}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.getElementById('searchOverlay').classList.add('opacity-0','pointer-events-none');});

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            overlay.classList.add('hidden');
        }
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay.classList.add('hidden');
    }
}
</script>
</body>
</html>