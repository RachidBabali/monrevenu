<?php
session_start();

// 1. Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=mon_revenu_db;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Vérification de l'authentification (optionnel si vos vidéos sont publiques, mais conseillé)
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /index.php'); exit();
}

// Gestion de la recherche
$recherche = trim($_GET['search'] ?? '');

try {
    if ($recherche !== '') {
        // Requête avec filtre de recherche
        $stmt = $pdo->prepare("SELECT f.*, u.fullname as auteur 
                               FROM formations f 
                               LEFT JOIN users_monrevenu u ON f.user_id = u.id 
                               WHERE f.titre LIKE ? OR f.description LIKE ? 
                               ORDER BY f.date_publication DESC");
        $stmt->execute(["%$recherche%", "%$recherche%"]);
    } else {
        // Requête globale (Toutes les vidéos)
        $stmt = $pdo->query("SELECT f.*, u.fullname as auteur 
                             FROM formations f 
                             LEFT JOIN users_monrevenu u ON f.user_id = u.id 
                             ORDER BY f.date_publication DESC");
    }
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $formations = [];
}
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Formations Vidéo</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    (function () {
      var theme = localStorage.getItem('theme');
      if (theme === 'dark') document.documentElement.classList.add('dark');
      else document.documentElement.classList.remove('dark');
    })();
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

<div class="min-h-screen flex flex-col pb-24 lg:pl-64">
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
   <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
      
      <div>
        <h1 class="font-bold text-[18px] text-slate-800 dark:text-white">Formations & Vidéos</h1>
        <p class="text-[11px] text-slate-400">Apprenez à votre rythme</p>
      </div>

      <form action="" method="GET" class="w-full sm:w-96 relative flex items-center">
        <input type="text" name="search" value="<?= htmlspecialchars($recherche) ?>" placeholder="Rechercher un cours ou un mot-clé..."
               class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-full pl-5 pr-12 py-2.5 text-[13px] text-slate-800 dark:text-slate-100 font-medium outline-none focus:border-[#1246A0] dark:focus:border-blue-500 transition-all">
        <button type="submit" class="absolute right-2 p-1.5 text-slate-400 hover:text-[#1246A0] transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        </button>
      </form>

      <button onclick="toggleTheme()" type="button" aria-label="Changer le thème" class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-900 flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0">
        <svg class="w-4 h-4 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg>
      </button>

    </div>
  </header>

  <main class="flex-1 px-4 lg:px-8 py-6 max-w-7xl w-full mx-auto">

    <?php if (empty($formations)): ?>
      <div class="text-center py-20 flex flex-col items-center justify-center">
        <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-900 flex items-center justify-center text-slate-400 mb-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5"/></svg>
        </div>
        <h3 class="font-bold text-[15px] text-slate-700 dark:text-slate-300">Aucune formation disponible</h3>
        <p class="text-[12px] text-slate-400 mt-1">Revenez plus tard ou modifiez votre recherche.</p>
      </div>
    <?php else: ?>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-5 gap-y-8">
        <?php foreach ($formations as $video): ?>
          <div class="group flex flex-col gap-3 bg-white dark:bg-[#141E33] p-3 rounded-[20px] shadow-sm border border-slate-100/60 dark:border-slate-800/40 transition-all hover:shadow-md">
            
            <div class="relative aspect-video w-full rounded-xl overflow-hidden bg-slate-900 shadow-inner">
              <?php if (!empty($video['video'])): ?>
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?= htmlspecialchars($video['video']) ?>" title="<?= htmlspecialchars($video['titre']) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-slate-500 text-[12px]">Vidéo indisponible</div>
              <?php endif; ?>
            </div>

            <div class="flex gap-2.5 px-1 pb-1">
              <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-slate-800 flex items-center justify-center font-bold text-[11px] text-[#1246A0] dark:text-blue-400 shrink-0">
                <?= strtoupper(substr($video['auteur'] ?? 'A', 0, 1)) ?>
              </div>

              <div class="flex flex-col gap-0.5">
                <h3 class="font-bold text-[13px] text-slate-800 dark:text-white leading-tight group-hover:text-[#1246A0] dark:group-hover:text-blue-400 transition-colors line-clamp-2">
                  <?= htmlspecialchars($video['titre']) ?>
                </h3>
                <p class="text-[11px] text-slate-400 font-medium mt-0.5"><?= htmlspecialchars($video['auteur'] ?? 'Administrateur') ?></p>
                <p class="text-[10px] text-slate-400">
                  Publié le <?= date('d/m/Y', strtotime($video['date_publication'])) ?>
                </p>
                
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 line-clamp-2 leading-relaxed">
                  <?= htmlspecialchars($video['description']) ?>
                </p>
              </div>
            </div>

          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </main>

  <?php include __DIR__ . '/../sections/navbar.php'; ?>
</div>

<script>
function toggleTheme(){
  document.documentElement.classList.toggle('dark');
  localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
}
</script>
</body>
</html>