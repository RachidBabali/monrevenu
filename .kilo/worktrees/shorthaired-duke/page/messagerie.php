<?php
session_start();
// Connexion à la base de données
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';

// Vérification de la session
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /index.php'); exit();
}

$user_id       = $_SESSION['user_id'];
$user_fullname = $_SESSION['user_fullname'] ?? 'Utilisateur';

try {
    // Récupérer les vrais messages de l'utilisateur depuis la base de données
    $query = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC");
    $query->execute([$user_id]);
    $conversations = $query->fetchAll();
} catch (PDOException $e) {
    $conversations = [];
}

// Prépare les données complètes des messages pour le JS (affichage instantané, sans recharger la page)
$messages_json = [];
foreach ($conversations as $conv) {
    $messages_json[(int) $conv['id']] = [
        'expediteur' => $conv['expediteur'] ?? 'Support',
        'message'    => $conv['message'] ?? $conv['dernier_message'] ?? '',
        'date'       => isset($conv['created_at']) ? date('d/m/Y à H:i', strtotime($conv['created_at'])) : '',
    ];
}
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Messagerie</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config={
      darkMode:'class',
      theme:{
        extend:{
          fontFamily:{sora:['Sora','sans-serif']},
          colors:{brand:{DEFAULT:'#1246A0',mid:'#1A5FCC',light:'#3B82F6',soft:'#EEF4FF'}}
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>body{font-family:'Sora',sans-serif;}</style>
</head>
<body class="bg-[#F8F9FB] dark:bg-[#0B1120] text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-300">

<div class="min-h-screen flex flex-col pb-24">

  <!-- ENTÊTE DE LA PAGE -->
 <header class="bg-transparent px-4 lg:px-6 pt-6 pb-2 flex items-center justify-between max-w-2xl w-full mx-auto">
    <div class="flex items-center gap-3">
      <!-- Bouton menu burger, visible uniquement sur mobile/tablette -->
      <button onclick="toggleSidebar()" type="button" aria-label="Ouvrir le menu"
              class="lg:hidden w-10 h-10 rounded-full bg-white dark:bg-[#141E33] shadow-sm flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0">
        <svg class="w-5 h-5 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6" x2="21" y2="6"/>
          <line x1="3" y1="12" x2="21" y2="12"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>

      <!-- Bouton retour -->
      <a href="/dashboard.php" aria-label="Retour au dashboard"
         class="w-10 h-10 rounded-full bg-white dark:bg-[#141E33] shadow-sm flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0 hover:border-brand/40 transition-colors">
        <svg class="w-5 h-5 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
      </a>

      <h1 class="font-bold text-[20px] text-slate-800 dark:text-white">Mon Profil</h1>
    </div>
    <button onclick="toggleTheme()" type="button" aria-label="Changer le thème" class="w-10 h-10 rounded-full bg-white dark:bg-[#141E33] shadow-sm flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0">
      <svg class="w-4 h-4 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg>
    </button>
  </header>

  <!-- FILTRES DISCRETS -->
  <div class="px-6 py-2 max-w-2xl w-full mx-auto flex gap-2 overflow-x-auto scrollbar-hide">
    <span class="bg-[#1246A0] text-white text-[12px] font-medium px-4 py-2 rounded-full cursor-pointer whitespace-nowrap">Tous les messages</span>
  </div>

  <!-- ZONE DE LISTE DES MESSAGES -->
  <main class="flex-1 px-4 lg:px-6 py-4 max-w-2xl w-full mx-auto">
    <div class="flex flex-col gap-3">
      
      <?php if (empty($conversations)): ?>
        <div class="bg-white dark:bg-[#141E33] rounded-[24px] p-8 text-center shadow-sm border border-slate-100 dark:border-slate-800">
          <p class="text-slate-400 text-[14px]">Votre boîte de réception est vide.</p>
        </div>
      <?php else: ?>

        <?php foreach ($conversations as $conv): 
          $isUnread = isset($conv['statut']) && strtolower($conv['statut']) === 'non_lu';
          $avatarLetters = strtoupper(substr($conv['expediteur'] ?? 'SU', 0, 2));
          $msgId = (int) $conv['id'];
        ?>
          
          <button type="button" onclick="ouvrirMessage(<?= $msgId ?>)"
             class="w-full text-left bg-white dark:bg-[#141E33] rounded-[24px] p-4 flex items-center justify-between shadow-sm border border-slate-100/70 dark:border-slate-800/50 hover:border-[#1246A0]/30 transition-all cursor-pointer group"
             id="carte-message-<?= $msgId ?>">
            <div class="flex items-center gap-4 min-w-0 flex-1">
              
              <!-- AVATAR DYNAMIQUE AVEC COULEUR DU SITE -->
              <div class="w-12 h-12 rounded-full bg-[#EEF4FF] dark:bg-slate-800 flex items-center justify-center font-bold text-[13px] text-[#1246A0] dark:text-blue-400 flex-shrink-0 relative">
                <?= htmlspecialchars($avatarLetters) ?>
                <?php if ($isUnread): ?>
                  <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-[#141E33]" id="pastille-<?= $msgId ?>"></span>
                <?php endif; ?>
              </div>

              <!-- MESSAGE CONTENU DEPUIS LA BDD -->
              <div class="min-w-0 flex-1">
                <p class="font-bold text-[14px] text-slate-800 dark:text-slate-100 tracking-wide truncate">
                  <?= htmlspecialchars($conv['expediteur'] ?? 'Support') ?>
                </p>
                <p class="text-[12px] mt-0.5 truncate <?= $isUnread ? 'font-semibold text-slate-900 dark:text-white' : 'text-slate-400 font-medium' ?>">
                  <?= htmlspecialchars($conv['message'] ?? $conv['dernier_message'] ?? '') ?>
                </p>
              </div>
            </div>
            
            <!-- HEURE ET CHEVRON -->
            <div class="text-right flex flex-col items-end gap-2 ml-3 shrink-0">
              <p class="text-[11px] text-slate-400 font-medium">
                <?= isset($conv['created_at']) ? date('d.m.Y', strtotime($conv['created_at'])) : '' ?>
              </p>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-[#1246A0] dark:group-hover:text-blue-400 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
              </svg>
            </div>
          </button>

        <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </main>
  
  <!-- INCLUSION DE VOTRE NAVBAR GLOBAL -->
  <?php include __DIR__ . '/../sections/navbar.php'; ?>
</div>

<!-- ============================ FENÊTRE DE LECTURE DU MESSAGE (reste sur la même page) ============================ -->
<div id="overlay-message" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden items-end sm:items-center justify-center px-0 sm:px-4">
  <div class="bg-white dark:bg-[#141E33] w-full sm:max-w-md sm:rounded-[24px] rounded-t-[28px] p-6 max-h-[85vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3 min-w-0">
        <div id="modal-avatar" class="w-11 h-11 rounded-full bg-[#EEF4FF] dark:bg-slate-800 flex items-center justify-center font-bold text-[13px] text-[#1246A0] dark:text-blue-400 shrink-0"></div>
        <div class="min-w-0">
          <p id="modal-expediteur" class="font-bold text-[14px] text-slate-800 dark:text-white truncate"></p>
          <p id="modal-date" class="text-[11px] text-slate-400"></p>
        </div>
      </div>
      <button type="button" onclick="fermerMessage()" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0" aria-label="Fermer">✕</button>
    </div>
    <div class="bg-[#F8F9FB] dark:bg-slate-900/50 rounded-2xl p-4">
      <p id="modal-contenu" class="text-[14px] text-slate-700 dark:text-slate-200 leading-relaxed whitespace-pre-line"></p>
    </div>
  </div>
</div>

<script>
function toggleTheme(){
  document.documentElement.classList.toggle('dark');
  localStorage.setItem('theme',document.documentElement.classList.contains('dark')?'dark':'light');
}

// Toutes les données des messages, déjà chargées avec la page (pas de rechargement nécessaire)
const messagesData = <?= json_encode($messages_json, JSON_UNESCAPED_UNICODE) ?>;

function ouvrirMessage(id) {
  const msg = messagesData[id];
  if (!msg) return;

  document.getElementById('modal-avatar').textContent = (msg.expediteur || 'SU').substring(0, 2).toUpperCase();
  document.getElementById('modal-expediteur').textContent = msg.expediteur || 'Support';
  document.getElementById('modal-date').textContent = msg.date || '';
  document.getElementById('modal-contenu').textContent = msg.message || '';

  const overlay = document.getElementById('overlay-message');
  overlay.classList.remove('hidden');
  overlay.classList.add('flex');

  // Retire visuellement le point "non lu" tout de suite
  const pastille = document.getElementById('pastille-' + id);
  if (pastille) pastille.remove();

  // Marque le message comme lu côté serveur, en arrière-plan
  fetch('marquer-lu.php?id=' + id).catch(function () {});
}

function fermerMessage() {
  const overlay = document.getElementById('overlay-message');
  overlay.classList.add('hidden');
  overlay.classList.remove('flex');
}

// Ferme la fenêtre si on clique en dehors de la carte
document.getElementById('overlay-message').addEventListener('click', function (e) {
  if (e.target === this) fermerMessage();
});
</script>
</body>
</html>