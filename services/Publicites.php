<?php
session_start();

// ============================================================
// 1. CONNEXION À LA BASE DE DONNÉES
// ============================================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';

// ============================================================
// 2. SESSION / SÉCURITÉ
// ============================================================
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /index.php'); exit();
}
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: /index.php'); exit();
}

// ============================================================
// 3. JETON CSRF
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// 4. CONSTANTE : LIMITE GLOBALE DE PUBS PAR JOUR (anti-abus)
// ============================================================
define('LIMITE_PUBS_PAR_JOUR', 10);

$ip_visiteur = $_SERVER['REMOTE_ADDR'] ?? 'inconnu';

// ============================================================
// 5. ENDPOINT AJAX — DÉMARRER LE VISIONNAGE D'UNE PUB
// ============================================================
// Enregistre l'heure de début côté serveur. C'est CETTE heure, et non un
// minuteur JS, qui sera utilisée pour valider la réclamation du gain.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_commencer_pub'])) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['ok' => false, 'erreur' => 'Session expirée, merci de recharger la page.']);
        exit;
    }

    $publicite_id = (int) ($_POST['publicite_id'] ?? 0);

    try {
        // Vérifie la limite globale journalière (tous types de pubs confondus)
        $stmtLimite = $pdo->prepare("SELECT COUNT(*) FROM vues_publicites WHERE user_id = ? AND jour = CURDATE()");
        $stmtLimite->execute([$user_id]);
        if ((int) $stmtLimite->fetchColumn() >= LIMITE_PUBS_PAR_JOUR) {
            echo json_encode(['ok' => false, 'erreur' => 'Vous avez atteint la limite de ' . LIMITE_PUBS_PAR_JOUR . ' publicités aujourd\'hui.']);
            exit;
        }

        // Vérifie que la pub existe et est active
        $stmtPub = $pdo->prepare("SELECT id, duree_secondes FROM publicites WHERE id = ? AND actif = 1 LIMIT 1");
        $stmtPub->execute([$publicite_id]);
        $pub = $stmtPub->fetch();
        if (!$pub) {
            echo json_encode(['ok' => false, 'erreur' => 'Publicité indisponible.']);
            exit;
        }

        // Vérifie si une vue existe déjà aujourd'hui pour cette pub
        $stmtExiste = $pdo->prepare("SELECT id, statut, started_at FROM vues_publicites WHERE user_id = ? AND publicite_id = ? AND jour = CURDATE() LIMIT 1");
        $stmtExiste->execute([$user_id, $publicite_id]);
        $existante = $stmtExiste->fetch();

        if ($existante) {
            if ($existante['statut'] === 'termine') {
                echo json_encode(['ok' => false, 'erreur' => 'Vous avez déjà regardé cette publicité aujourd\'hui.']);
                exit;
            }
            // Une session "en_cours" existe déjà (page rechargée, etc.) : on la réutilise
            echo json_encode(['ok' => true, 'vue_id' => $existante['id'], 'duree_secondes' => (int) $pub['duree_secondes']]);
            exit;
        }

        // Nouvelle session de visionnage
        $stmtInsert = $pdo->prepare(
            "INSERT INTO vues_publicites (user_id, publicite_id, jour, ip_address, statut, started_at, created_at)
             VALUES (?, ?, CURDATE(), ?, 'en_cours', NOW(), NOW())"
        );
        $stmtInsert->execute([$user_id, $publicite_id, $ip_visiteur]);

        echo json_encode(['ok' => true, 'vue_id' => (int) $pdo->lastInsertId(), 'duree_secondes' => (int) $pub['duree_secondes']]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur, merci de réessayer.']);
        exit;
    }
}

// ============================================================
// 6. ENDPOINT AJAX — RÉCLAMER LE GAIN
// ============================================================
// Revérifie TOUT côté serveur : temps réellement écoulé depuis started_at,
// jamais la valeur envoyée par le client. Transaction + statut 'termine'
// (protégé par la contrainte UNIQUE) empêchent tout double crédit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reclamer_gain'])) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['ok' => false, 'erreur' => 'Session expirée, merci de recharger la page.']);
        exit;
    }

    $vue_id = (int) ($_POST['vue_id'] ?? 0);

    $pdo->beginTransaction();
    try {
        $stmtVue = $pdo->prepare(
            "SELECT v.id, v.user_id, v.publicite_id, v.statut, v.started_at, p.montant_gain, p.duree_secondes, p.titre
             FROM vues_publicites v
             JOIN publicites p ON p.id = v.publicite_id
             WHERE v.id = ? AND v.user_id = ?
             FOR UPDATE"
        );
        $stmtVue->execute([$vue_id, $user_id]);
        $vue = $stmtVue->fetch();

        if (!$vue) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erreur' => 'Session de visionnage introuvable.']);
            exit;
        }

        if ($vue['statut'] === 'termine') {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erreur' => 'Ce gain a déjà été réclamé.']);
            exit;
        }

        // Vérification serveur du temps réellement écoulé (jamais confiance au client)
        $stmtTemps = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) AS ecoule");
        $stmtTemps->execute([$vue['started_at']]);
        $ecoule = (int) $stmtTemps->fetch()['ecoule'];

        if ($ecoule < (int) $vue['duree_secondes']) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erreur' => 'Temps de visionnage insuffisant.']);
            exit;
        }

        // Tout est valide : on crédite
        $stmtFinaliser = $pdo->prepare("UPDATE vues_publicites SET statut = 'termine' WHERE id = ?");
        $stmtFinaliser->execute([$vue_id]);

        $stmtCredit = $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?");
        $stmtCredit->execute([$vue['montant_gain'], $user_id]);

        $referenceTx = 'PUB-' . $vue_id;
        $stmtTx = $pdo->prepare(
            "INSERT INTO transactions_monrevenu (user_id, type, amount, reference, status, description)
             VALUES (?, 'depot', ?, ?, 'complete', ?)"
        );
        $stmtTx->execute([$user_id, $vue['montant_gain'], $referenceTx, 'Gain publicité : ' . $vue['titre']]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'montant' => (float) $vue['montant_gain'],
            'message' => 'Gain crédité avec succès !'
        ]);
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur, merci de réessayer.']);
        exit;
    }
}

// ============================================================
// 7. CHARGEMENT DE LA PAGE (affichage normal)
// ============================================================
$stmtLimiteAff = $pdo->prepare("SELECT COUNT(*) FROM vues_publicites WHERE user_id = ? AND jour = CURDATE()");
$stmtLimiteAff->execute([$user_id]);
$nb_vues_aujourdhui = (int) $stmtLimiteAff->fetchColumn();
$limite_atteinte = $nb_vues_aujourdhui >= LIMITE_PUBS_PAR_JOUR;

// Récupère les pubs actives + le statut de visionnage de l'utilisateur pour aujourd'hui
$stmtPubs = $pdo->prepare(
    "SELECT p.id, p.titre, p.description, p.image, p.video, p.montant_gain, p.duree_secondes,
            v.id AS vue_id, v.statut AS vue_statut
     FROM publicites p
     LEFT JOIN vues_publicites v ON v.publicite_id = p.id AND v.user_id = ? AND v.jour = CURDATE()
     WHERE p.actif = 1
     ORDER BY p.id DESC"
);
$stmtPubs->execute([$user_id]);
$publicites = $stmtPubs->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Regarder des publicités</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    (function () {
      var theme = localStorage.getItem('theme');
      if (theme === 'dark') document.documentElement.classList.add('dark');
      else document.documentElement.classList.remove('dark');
    })();
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: { sora: ['Sora', 'sans-serif'] },
          colors: {
            brand: { DEFAULT: '#1246A0', mid: '#1A5FCC', light: '#3B82F6', soft: '#EEF4FF' }
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    body { font-family: 'Sora', sans-serif; }
    html, body { overflow-x: hidden; max-width: 100%; }
  </style>
</head>
<body class="bg-[#F8F9FB] dark:bg-[#0B1120] text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-300">

<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>

<div class="min-h-screen flex flex-col pb-24 lg:pl-64">

  <header class="bg-transparent px-4 lg:px-8 pt-6 pb-2 flex items-center justify-between w-full">
    <div class="flex items-center gap-3">
      <button onclick="toggleSidebar()" type="button" aria-label="Ouvrir le menu"
              class="lg:hidden w-10 h-10 rounded-full bg-white dark:bg-[#141E33] shadow-sm flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0">
        <svg class="w-5 h-5 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <div>
        <h1 class="font-bold text-[20px] text-slate-800 dark:text-white">Regarder des publicités</h1>
        <p class="text-[12px] text-slate-400 mt-0.5">Regardez, patientez, encaissez</p>
      </div>
    </div>
    <button onclick="toggleTheme()" type="button" aria-label="Changer le thème"
            class="w-10 h-10 rounded-full bg-white dark:bg-[#141E33] shadow-sm flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0">
      <svg class="w-4 h-4 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg>
    </button>
  </header>

  <main class="flex-1 px-4 lg:px-8 py-4 w-full flex flex-col gap-5">

    <!-- Compteur de quota journalier -->
    <div class="bg-white dark:bg-[#141E33] rounded-2xl p-4 flex items-center justify-between shadow-sm border border-slate-100/70 dark:border-slate-800/50">
      <div>
        <p class="text-[12px] text-slate-400 font-medium">Publicités regardées aujourd'hui</p>
        <p class="font-bold text-[16px] text-slate-800 dark:text-white"><?= $nb_vues_aujourdhui ?> / <?= LIMITE_PUBS_PAR_JOUR ?></p>
      </div>
      <div class="w-24 h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
        <div class="h-full bg-brand" style="width: <?= min(100, ($nb_vues_aujourdhui / LIMITE_PUBS_PAR_JOUR) * 100) ?>%"></div>
      </div>
    </div>

    <?php if ($limite_atteinte): ?>
      <div class="p-4 bg-amber-500/10 text-amber-600 rounded-2xl text-center text-[13px] font-medium border border-amber-500/20">
        Vous avez atteint la limite de <?= LIMITE_PUBS_PAR_JOUR ?> publicités aujourd'hui. Revenez demain pour continuer à gagner !
      </div>
    <?php endif; ?>

    <!-- Message de résultat (rempli en JS après réclamation) -->
    <div id="message-resultat" class="hidden p-4 rounded-2xl text-center text-[13px] font-medium border"></div>

    <?php if (empty($publicites)): ?>
      <div class="bg-white dark:bg-[#141E33] rounded-[24px] p-10 text-center shadow-sm border border-slate-100/70 dark:border-slate-800/50">
        <p class="text-slate-400 text-[13px] font-medium">Aucune publicité disponible pour le moment.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($publicites as $pub): ?>
          <?php
            $pub_id = (int) $pub['id'];
            $pub_image = !empty($pub['image']) ? '/admin/' . $pub['image'] : '/assets/img/produit-placeholder.png';
            $pub_video = !empty($pub['video']) ? '/admin/' . $pub['video'] : '';
            $deja_vue = $pub['vue_statut'] === 'termine';
          ?>
          <div class="bg-white dark:bg-[#141E33] rounded-[24px] shadow-sm border border-slate-100/70 dark:border-slate-800/50 overflow-hidden flex flex-col">

            <div class="relative aspect-video w-full bg-slate-900 overflow-hidden">
              <img src="<?= htmlspecialchars($pub_image) ?>" alt="" class="w-full h-full object-cover opacity-80">
              <?php if ($deja_vue): ?>
                <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
                  <span class="bg-emerald-500 text-white text-[12px] font-bold px-4 py-2 rounded-full">✓ Déjà regardée aujourd'hui</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="p-5 flex flex-col gap-2 flex-1">
              <h3 class="font-bold text-[14px] text-slate-800 dark:text-white"><?= htmlspecialchars($pub['titre']) ?></h3>
              <p class="text-[11px] text-slate-400 line-clamp-2"><?= htmlspecialchars($pub['description'] ?? '') ?></p>

              <div class="flex items-center justify-between mt-1">
                <span class="font-extrabold text-[16px] text-emerald-500">+<?= number_format((float) $pub['montant_gain'], 0, ',', ' ') ?> KMF</span>
                <span class="text-[11px] text-slate-400"><?= (int) $pub['duree_secondes'] ?>s</span>
              </div>

              <button type="button"
                      onclick="ouvrirPub(<?= $pub_id ?>, '<?= htmlspecialchars($pub_video, ENT_QUOTES) ?>', <?= (int) $pub['duree_secondes'] ?>, <?= (float) $pub['montant_gain'] ?>, '<?= htmlspecialchars($pub['titre'], ENT_QUOTES) ?>')"
                      <?= ($deja_vue || $limite_atteinte || empty($pub_video)) ? 'disabled' : '' ?>
                      class="mt-2 w-full bg-brand hover:bg-brand-mid disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-[12px] py-2.5 rounded-xl transition-all active:scale-[0.98]">
                <?= $deja_vue ? 'Déjà regardée' : (empty($pub_video) ? 'Vidéo indisponible' : 'Regarder & gagner') ?>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>

  <?php include __DIR__ . '/../sections/navbar.php'; ?>
</div>

<!-- ============================ MODALE DE VISIONNAGE ============================ -->
<div id="modal-pub" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-[#141E33] rounded-[24px] max-w-lg w-full overflow-hidden">
    <div class="aspect-video w-full bg-black">
      <video id="video-pub" class="w-full h-full" controls controlsList="nodownload noplaybackrate" disablepictureinpicture></video>
    </div>
    <div class="p-5">
      <h3 id="titre-pub-modal" class="font-bold text-[14px] text-slate-800 dark:text-white mb-3"></h3>

      <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden mb-2">
        <div id="barre-progression" class="h-full bg-brand transition-all duration-1000" style="width:0%"></div>
      </div>
      <p id="texte-progression" class="text-[11px] text-slate-400 text-center mb-4">Regardez la vidéo en entier...</p>

      <button type="button" id="btn-reclamer" disabled
              class="w-full bg-emerald-500 hover:bg-emerald-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-[13px] py-3 rounded-xl transition-all active:scale-[0.98]">
        Patientez...
      </button>
      <button type="button" onclick="fermerModal()" class="w-full mt-2 text-slate-400 text-[12px] font-medium py-2">
        Fermer
      </button>
    </div>
  </div>
</div>

<script>
const csrfToken = <?= json_encode($csrf_token) ?>;
let vueIdCourante = null;
let dureeCourante = 0;
let minuteurInterval = null;

function toggleTheme() {
  document.documentElement.classList.toggle('dark');
  localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
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
function closeSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (sidebar && overlay) {
    sidebar.classList.add('-translate-x-full');
    sidebar.classList.remove('translate-x-0');
    overlay.classList.add('hidden');
  }
}

async function ouvrirPub(publiciteId, videoUrl, dureeSecondes, montant, titre) {
  if (!videoUrl) return;

  // Démarre la session de visionnage côté serveur (source de vérité du temps écoulé)
  const form = new FormData();
  form.append('action_commencer_pub', '1');
  form.append('csrf_token', csrfToken);
  form.append('publicite_id', publiciteId);

  const reponse = await fetch('', { method: 'POST', body: form });
  const data = await reponse.json();

  if (!data.ok) {
    afficherMessageResultat(false, data.erreur);
    return;
  }

  vueIdCourante = data.vue_id;
  dureeCourante = data.duree_secondes;

  document.getElementById('titre-pub-modal').textContent = titre;
  const video = document.getElementById('video-pub');
  video.src = videoUrl;
  video.play().catch(() => {});

  document.getElementById('modal-pub').classList.remove('hidden');
  document.getElementById('modal-pub').classList.add('flex');

  const btnReclamer = document.getElementById('btn-reclamer');
  btnReclamer.disabled = true;
  btnReclamer.textContent = 'Patientez...';
  btnReclamer.onclick = () => reclamerGain();

  let secondesEcoulees = 0;
  clearInterval(minuteurInterval);
  minuteurInterval = setInterval(() => {
    secondesEcoulees++;
    const pourcentage = Math.min(100, (secondesEcoulees / dureeCourante) * 100);
    document.getElementById('barre-progression').style.width = pourcentage + '%';

    if (secondesEcoulees >= dureeCourante) {
      clearInterval(minuteurInterval);
      btnReclamer.disabled = false;
      btnReclamer.textContent = 'Réclamer mon gain';
      document.getElementById('texte-progression').textContent = 'Vous pouvez réclamer votre gain !';
    } else {
      document.getElementById('texte-progression').textContent =
        'Encore ' + (dureeCourante - secondesEcoulees) + ' seconde(s)...';
    }
  }, 1000);
}

async function reclamerGain() {
  const btnReclamer = document.getElementById('btn-reclamer');
  btnReclamer.disabled = true;
  btnReclamer.textContent = 'Traitement...';

  const form = new FormData();
  form.append('action_reclamer_gain', '1');
  form.append('csrf_token', csrfToken);
  form.append('vue_id', vueIdCourante);

  const reponse = await fetch('', { method: 'POST', body: form });
  const data = await reponse.json();

  fermerModal();

  if (data.ok) {
    afficherMessageResultat(true, '✅ ' + data.montant.toLocaleString('fr-FR') + ' KMF crédités sur votre solde !');
    setTimeout(() => location.reload(), 1800);
  } else {
    afficherMessageResultat(false, data.erreur);
  }
}

function fermerModal() {
  clearInterval(minuteurInterval);
  const video = document.getElementById('video-pub');
  video.pause();
  video.src = '';
  document.getElementById('modal-pub').classList.add('hidden');
  document.getElementById('modal-pub').classList.remove('flex');
}

function afficherMessageResultat(succes, texte) {
  const bloc = document.getElementById('message-resultat');
  bloc.textContent = texte;
  bloc.classList.remove('hidden', 'bg-emerald-500/10', 'text-emerald-500', 'border-emerald-500/20', 'bg-red-500/10', 'text-red-500', 'border-red-500/20');
  if (succes) {
    bloc.classList.add('bg-emerald-500/10', 'text-emerald-500', 'border-emerald-500/20');
  } else {
    bloc.classList.add('bg-red-500/10', 'text-red-500', 'border-red-500/20');
  }
  bloc.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
</body>
</html>