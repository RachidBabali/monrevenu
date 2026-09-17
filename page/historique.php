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
    // Récupérer la liste des transactions (table réelle : transactions_monrevenu)
    $query = $pdo->prepare("SELECT * FROM transactions_monrevenu WHERE user_id = ? ORDER BY created_at DESC");
    $query->execute([$user_id]);
    $transactions = $query->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
}
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Mes Transactions</title>
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

<?php include __DIR__ . '/../sections/navbar.php'; ?>

<div class="lg:ml-64 min-h-screen flex flex-col">

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

  <div class="px-4 lg:px-8 py-4 max-w-2xl w-full mx-auto flex gap-2 overflow-x-auto scrollbar-hide">
    <span class="bg-[#0F2D37] text-white text-[12px] font-medium px-4 py-2 rounded-full cursor-pointer whitespace-nowrap">Tous</span>
    <span class="bg-white dark:bg-[#141E33] text-slate-500 text-[12px] font-medium px-4 py-2 rounded-full shadow-sm border border-slate-100 dark:border-slate-800 cursor-pointer whitespace-nowrap">Dépôt de fonds</span>
    <span class="bg-white dark:bg-[#141E33] text-slate-500 text-[12px] font-medium px-4 py-2 rounded-full shadow-sm border border-slate-100 dark:border-slate-800 cursor-pointer whitespace-nowrap">Retrait</span>
    <span class="bg-white dark:bg-[#141E33] text-slate-500 text-[12px] font-medium px-4 py-2 rounded-full shadow-sm border border-slate-100 dark:border-slate-800 cursor-pointer whitespace-nowrap">Commissions</span>
  </div>

  <main class="flex-1 p-4 lg:p-6 max-w-2xl w-full mx-auto">
    <div class="flex flex-col gap-3">
      
      <?php if (empty($transactions)): ?>
        <div class="bg-white dark:bg-[#141E33] rounded-[24px] p-8 text-center shadow-sm border border-slate-100 dark:border-slate-800">
          <p class="text-slate-400 text-[14px]">Aucune transaction pour le moment.</p>
        </div>
      <?php else: ?>

        <?php foreach ($transactions as $tx):
          // Types qui créditent le solde (argent qui entre) vs qui le débitent (argent qui sort)
          // Valeurs réelles de l'ENUM `type` : depot, retrait, commission, achat_service, jeu_gain, jeu_perte
          $isCredit = in_array(strtolower($tx['type']), ['depot', 'commission', 'jeu_gain']);
          $isFailed = strtolower($tx['status']) === 'echoue';
          $isPending = strtolower($tx['status']) === 'en_attente';

          $libelles_type = [
              'depot'         => 'Dépôt de fonds',
              'retrait'       => 'Retrait',
              'commission'    => 'Commission',
              'achat_service' => 'Achat de service',
              'jeu_gain'      => 'Gain de jeu',
              'jeu_perte'     => 'Perte de jeu',
          ];
          $libelle_type = $libelles_type[strtolower($tx['type'])] ?? ucfirst($tx['type']);
        ?>
          
          <div class="bg-white dark:bg-[#141E33] rounded-[24px] p-4 flex items-center justify-between shadow-sm border border-slate-100/70 dark:border-slate-800/50">
            <div class="flex items-center gap-4">
              <?php if($isFailed): ?>
                <div class="w-11 h-11 rounded-full bg-rose-50 dark:bg-rose-950/20 text-rose-500 flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
              <?php elseif($isCredit): ?>
                <div class="w-11 h-11 rounded-full bg-[#EEFDF4] dark:bg-emerald-950/20 text-[#22C55E] flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="17" y1="7" x2="7" y2="17"/><polyline points="17 17 7 17 7 7"/></svg>
                </div>
              <?php else: ?>
                <div class="w-11 h-11 rounded-full bg-[#F1F3F6] dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                </div>
              <?php endif; ?>

              <div>
                <p class="font-bold text-[14px] text-slate-800 dark:text-slate-100 tracking-wide">
                  <?= htmlspecialchars($libelle_type) ?>
                </p>
                <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                  <?php if ($isFailed): ?>
                    Échouée
                  <?php elseif ($isPending): ?>
                    En attente
                  <?php else: ?>
                    <?= !empty($tx['description']) ? htmlspecialchars($tx['description']) : 'Portefeuille MonRevenu' ?>
                  <?php endif; ?>
                </p>
              </div>
            </div>
            
            <div class="text-right">
              <p class="font-bold text-[15px] tracking-wide <?= $isCredit && !$isFailed ? 'text-[#22C55E]' : 'text-slate-800 dark:text-white' ?>">
                <?= $isFailed ? '' : ($isCredit ? '+ ' : '- ') ?><?= number_format($tx['amount'], 0, ',', ' ') ?> KMF
              </p>
              <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                <?= date('d.m.Y', strtotime($tx['created_at'])) ?>
              </p>
            </div>
          </div>

        <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </main>
  
  <?php include __DIR__ . '/../sections/navbar.php'; ?>
</div>

<script>
function toggleTheme(){
  document.documentElement.classList.toggle('dark');
  localStorage.setItem('theme',document.documentElement.classList.contains('dark')?'dark':'light');
}
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
</script>
</body>
</html>