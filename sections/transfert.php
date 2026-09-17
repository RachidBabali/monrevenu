<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /index.php'); exit();
}

$user_id       = $_SESSION['user_id'];
$user_fullname = $_SESSION['user_fullname'] ?? 'Utilisateur';
$user_initials = strtoupper(substr($user_fullname, 0, 2));
$user_role     = $_SESSION['user_role'] ?? 'client';

// Solde
$stmt = $pdo->prepare("SELECT balance, phone FROM users_monrevenu WHERE id = ?");
$stmt->execute([$user_id]);
$sender  = $stmt->fetch();
$balance = $sender['balance'] ?? 0;

$error = $success = '';

// ── AJAX vérification numéro ────────────────────────────────────────────────
if (isset($_GET['check_phone'])) {
    header('Content-Type: application/json');
    $phone = trim($_GET['check_phone']);
    $stmt  = $pdo->prepare("SELECT fullname, phone FROM users_monrevenu WHERE phone = ? AND id != ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$phone, $user_id]);
    $u = $stmt->fetch();
    echo $u ? json_encode(['found'=>true,'name'=>$u['fullname'],'phone'=>$u['phone']]) : json_encode(['found'=>false]);
    exit();
}

// ── TRAITEMENT ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Session expirée.';
    } else {
        $phone_dest = trim($_POST['phone_dest'] ?? '');
        $montant    = floatval($_POST['montant'] ?? 0);

        if (!$phone_dest || !$montant)        { $error = 'Veuillez remplir tous les champs.'; }
        elseif ($phone_dest === $sender['phone']) { $error = 'Vous ne pouvez pas vous envoyer de l\'argent.'; }
        elseif ($montant < 100)               { $error = 'Montant minimum : 100 FCFA.'; }
        elseif ($montant > $balance)          { $error = 'Solde insuffisant.'; }
        else {
            $stmt = $pdo->prepare("SELECT id, fullname FROM users_monrevenu WHERE phone = ? AND id != ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$phone_dest, $user_id]);
            $dest = $stmt->fetch();

            if (!$dest) {
                $error = 'Ce numéro n\'existe pas sur MonRevenu.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $ref = 'TRF-' . strtoupper(uniqid());
                    $pdo->prepare("UPDATE users_monrevenu SET balance = balance - ? WHERE id = ?")->execute([$montant, $user_id]);
                    $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?")->execute([$montant, $dest['id']]);
                    $pdo->prepare("INSERT INTO transactions_monrevenu (user_id,type,amount,reference,status,description,created_at) VALUES (?,?,?,?,'complete',?,NOW())")->execute([$user_id,'retrait',$montant,$ref,'Transfert vers '.$dest['fullname']]);
                    $pdo->prepare("INSERT INTO transactions_monrevenu (user_id,type,amount,reference,status,description,created_at) VALUES (?,?,?,?,'complete',?,NOW())")->execute([$dest['id'],'depot',$montant,$ref.'-R','Reçu de '.$user_fullname]);
                    $pdo->commit();
                    $balance -= $montant;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $success = 'Transfert de '.number_format($montant,0,',','.').' FCFA envoyé à '.$dest['fullname'].' !';
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $error = 'Erreur lors du transfert. Réessayez.';
                }
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Transfert – MonRevenu</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sora:['Sora','sans-serif']}}}}</script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    body{font-family:'Sora',sans-serif;}
    .scrollbar-hide::-webkit-scrollbar{display:none;}
    .scrollbar-hide{-ms-overflow-style:none;scrollbar-width:none;}
  </style>
</head>
<body class="bg-slate-50 dark:bg-[#0B1120] text-slate-900 dark:text-slate-100 min-h-screen">

<!-- SIDEBAR -->
<aside id="sidebar" class="fixed top-0 left-0 bottom-0 w-64 bg-[#1246A0] flex flex-col z-50 -translate-x-64 lg:translate-x-0 transition-transform duration-300">
  <div class="flex items-center gap-3 px-6 py-7">
    <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center font-black text-white text-lg">M</div>
    <span class="text-white font-bold text-lg">Mon<span class="opacity-55 font-normal">Revenu</span></span>
  </div>
  <nav class="flex-1 overflow-y-auto px-3 pb-4 scrollbar-hide">
    <a href="/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/65 hover:text-white hover:bg-white/10 font-medium text-[13.5px] mb-1 transition-all">
      <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Accueil
    </a>
    <a href="/depot.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/65 hover:text-white hover:bg-white/10 font-medium text-[13.5px] mb-1 transition-all">
      <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Déposer
    </a>
    <a href="/transfert.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-white bg-white/15 border-l-4 border-white font-semibold text-[13.5px] mb-1">
      <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>Transférer
    </a>
    <a href="/retrait.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/65 hover:text-white hover:bg-white/10 font-medium text-[13.5px] mb-1 transition-all">
      <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17V3"/><path d="m6 11 6 6 6-6"/><path d="M19 21H5"/></svg>Retirer
    </a>
  </nav>
  <div class="border-t border-white/10 px-4 py-4 flex items-center gap-3">
    <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-sm"><?= $user_initials ?></div>
    <div class="flex-1 min-w-0">
      <p class="text-white font-semibold text-[13px] truncate"><?= htmlspecialchars($user_fullname) ?></p>
      <p class="text-white/50 text-[11px]"><?= ucfirst($user_role) ?></p>
    </div>
    <a href="/logout.php" class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center hover:bg-white/20">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</aside>
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>

<div class="lg:ml-64 min-h-screen flex flex-col">

  <!-- MOBILE HEADER -->
  <header class="lg:hidden bg-[#1246A0] px-4 pt-4 pb-6 flex items-center gap-3">
    <button onclick="toggleSidebar()" class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
      <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div>
      <p class="text-white font-bold text-[15px]">Transfert d'argent</p>
      <p class="text-white/60 text-[11px]">Envoyer vers un numéro MonRevenu</p>
    </div>
  </header>

  <!-- TOPBAR desktop -->
  <header class="hidden lg:flex items-center bg-white dark:bg-[#141E33] border-b border-slate-100 dark:border-slate-800 px-8 h-16 sticky top-0 z-30">
    <h1 class="font-bold text-[16px]">Transfert d'argent</h1>
  </header>

  <main class="flex-1 flex items-start justify-center px-4 py-8 pb-28 lg:pb-8">
    <div class="w-full max-w-md">

      <!-- Solde -->
      <div class="bg-gradient-to-r from-[#1246A0] to-[#3B82F6] rounded-2xl p-5 mb-6 shadow-lg text-center">
        <p class="text-white/70 text-[12px] mb-1">Votre solde</p>
        <p class="text-white font-extrabold text-[30px]"><?= number_format($balance,0,',','.')  ?> <span class="text-base font-normal opacity-70">FCFA</span></p>
      </div>

      <!-- Succès -->
      <?php if($success): ?>
      <div class="flex items-center gap-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-400 rounded-2xl p-4 mb-5">
        <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <p class="text-[13px] font-semibold"><?= htmlspecialchars($success) ?></p>
      </div>
      <?php endif; ?>

      <!-- Erreur -->
      <?php if($error): ?>
      <div class="flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-600 dark:text-red-400 rounded-2xl p-4 mb-5">
        <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-[13px] font-semibold"><?= htmlspecialchars($error) ?></p>
      </div>
      <?php endif; ?>

      <!-- FORMULAIRE -->
      <div class="bg-white dark:bg-[#141E33] rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm p-6">

        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>

          <!-- Numéro -->
          <div class="mb-4">
            <label class="block text-[13px] font-semibold text-slate-700 dark:text-slate-300 mb-2">
              Numéro du destinataire
            </label>
            <div class="relative">
              <input type="tel" name="phone_dest" id="phone_dest"
                     placeholder="Ex: 77 000 00 00"
                     class="w-full px-4 py-3 pr-12 rounded-xl border-2 border-slate-200 dark:border-slate-700
                            bg-white dark:bg-slate-800 text-[14px] text-slate-800 dark:text-white
                            focus:outline-none focus:border-[#1246A0] transition-colors"
                     autocomplete="off" maxlength="20"
                     oninput="checkPhone(this.value)"/>
              <!-- Icône statut -->
              <div id="statusIcon" class="absolute right-4 top-1/2 -translate-y-1/2"></div>
            </div>

            <!-- Carte destinataire trouvé -->
            <div id="recipientCard" class="hidden mt-3 flex items-center gap-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-xl p-3">
              <div class="w-10 h-10 rounded-full bg-[#1246A0] flex items-center justify-center flex-shrink-0">
                <span id="recipientInitials" class="text-white font-bold text-sm"></span>
              </div>
              <div>
                <p id="recipientName" class="font-bold text-[13px] text-slate-800 dark:text-white"></p>
                <p id="recipientPhone" class="text-[11px] text-slate-400"></p>
              </div>
              <svg class="w-5 h-5 text-green-500 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>

            <!-- Numéro introuvable -->
            <p id="notFoundMsg" class="hidden mt-2 text-[12px] text-red-500 font-medium">
              ❌ Ce numéro n'existe pas sur MonRevenu.
            </p>
          </div>

          <!-- Montant -->
          <div class="mb-6">
            <label class="block text-[13px] font-semibold text-slate-700 dark:text-slate-300 mb-2">
              Montant (FCFA)
            </label>
            <div class="relative">
              <input type="number" name="montant" id="montant"
                     placeholder="0" min="100" max="<?= $balance ?>"
                     class="w-full px-4 py-3 pr-16 rounded-xl border-2 border-slate-200 dark:border-slate-700
                            bg-white dark:bg-slate-800 text-[14px] text-slate-800 dark:text-white
                            focus:outline-none focus:border-[#1246A0] transition-colors"/>
              <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[13px] font-semibold text-slate-400">FCFA</span>
            </div>
            <!-- Montants rapides -->
            <div class="flex gap-2 mt-2">
              <?php foreach([500,1000,2000,5000] as $q): ?>
              <button type="button" onclick="document.getElementById('montant').value=<?= $q ?>"
                      class="flex-1 text-[11px] font-semibold py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700
                             text-slate-600 dark:text-slate-300 hover:bg-blue-50 hover:text-[#1246A0] transition-colors">
                <?= number_format($q,0,',','.') ?> F
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Bouton Envoyer -->
          <button type="submit" id="submitBtn" disabled
                  class="w-full flex items-center justify-center gap-2
                         bg-[#1246A0] hover:bg-[#1A5FCC]
                         disabled:bg-slate-200 dark:disabled:bg-slate-700
                         disabled:text-slate-400 disabled:cursor-not-allowed
                         text-white font-bold text-[14px] py-3.5 rounded-xl
                         transition-all shadow-md shadow-blue-500/20">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"/>
              <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            Envoyer
          </button>

        </form>
      </div>

    </div>
  </main>

  <!-- MOBILE NAV -->
  <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-[#141E33] border-t border-slate-100 dark:border-slate-800 z-40 h-[70px] flex items-center justify-around px-2">
    <a href="/dashboard.php" class="flex flex-col items-center gap-1 flex-1 py-2"><svg class="w-[22px] h-[22px] stroke-slate-400" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><span class="text-[9.5px] text-slate-400">Accueil</span></a>
    <a href="/services.php" class="flex flex-col items-center gap-1 flex-1 py-2"><svg class="w-[22px] h-[22px] stroke-slate-400" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg><span class="text-[9.5px] text-slate-400">Services</span></a>
    <a href="/depot.php" class="flex flex-col items-center gap-1 flex-1 -mt-5"><div class="w-14 h-14 rounded-full bg-[#1246A0] flex items-center justify-center shadow-lg"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div><span class="text-[9.5px] text-slate-400 mt-1">Payer</span></a>
    <a href="/messagerie.php" class="flex flex-col items-center gap-1 flex-1 py-2"><svg class="w-[22px] h-[22px] stroke-slate-400" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span class="text-[9.5px] text-slate-400">Messages</span></a>
    <a href="/profil.php" class="flex flex-col items-center gap-1 flex-1 py-2"><svg class="w-[22px] h-[22px] stroke-slate-400" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg><span class="text-[9.5px] text-slate-400">Profil</span></a>
  </nav>

</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-64');document.getElementById('sidebarOverlay').classList.toggle('hidden');}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-64');document.getElementById('sidebarOverlay').classList.add('hidden');}

let phoneOk = false;
let timer   = null;

function checkPhone(val) {
  clearTimeout(timer);
  const phone      = val.trim();
  const icon       = document.getElementById('statusIcon');
  const card       = document.getElementById('recipientCard');
  const notFound   = document.getElementById('notFoundMsg');
  const btn        = document.getElementById('submitBtn');

  card.classList.add('hidden');
  notFound.classList.add('hidden');
  phoneOk = false;
  btn.disabled = true;

  if (phone.length < 6) { icon.innerHTML = ''; return; }

  // Spinner
  icon.innerHTML = '<svg class="w-5 h-5 text-slate-400 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';

  timer = setTimeout(() => {
    fetch(`/transfert.php?check_phone=${encodeURIComponent(phone)}`)
      .then(r => r.json())
      .then(data => {
        if (data.found) {
          phoneOk = true;
          icon.innerHTML = '<svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
          document.getElementById('recipientInitials').textContent = data.name.substring(0,2).toUpperCase();
          document.getElementById('recipientName').textContent     = data.name;
          document.getElementById('recipientPhone').textContent    = data.phone;
          card.classList.remove('hidden');
          btn.disabled = false;
        } else {
          phoneOk = false;
          icon.innerHTML = '<svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
          notFound.classList.remove('hidden');
          btn.disabled = true;
        }
      });
  }, 600);
}
</script>
</body>
</html>