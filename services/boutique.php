<?php
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /index.php'); exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
exigerTelephoneVerifie($pdo);

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$user_id) {
    header('Location: /index.php'); exit();
}

$user_fullname = $_SESSION['user_fullname'] ?? 'Utilisateur';
$user_initials = strtoupper(substr($user_fullname, 0, 2)) ?: 'U';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$recherche = trim($_GET['q'] ?? '');

$produits = [];
try {
    if ($recherche !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, nom_produit AS nom, description, image, prix_vente AS prix
             FROM vendeur_produits
             WHERE statut = 'actif' AND nom_produit LIKE ?
             ORDER BY nom_produit ASC"
        );
        $stmt->execute(['%' . $recherche . '%']);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, nom_produit AS nom, description, image, prix_vente AS prix
             FROM vendeur_produits
             WHERE statut = 'actif'
             ORDER BY nom_produit ASC"
        );
        $stmt->execute();
    }
    $produits = $stmt->fetchAll();
} catch (PDOException $e) {
    $message_error = "Impossible de charger le catalogue pour le moment.";
    $produits = [];
}

$protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
define('BASE_URL_SITE', $protocole . $_SERVER['HTTP_HOST']);
define('SECRET_AFFILIATION', 'change-moi-avec-une-longue-cle-aleatoire-unique');

define('SEUIL_PRIX_COMMISSION', 10000);
define('COMMISSION_BASSE', 500);
define('COMMISSION_HAUTE', 1000);

function calculerCommission(float $prix): int
{
    return $prix <= SEUIL_PRIX_COMMISSION ? COMMISSION_BASSE : COMMISSION_HAUTE;
}

function slugify(string $texte): string
{
    $texte = iconv('UTF-8', 'ASCII//TRANSLIT', $texte) ?: $texte;
    $texte = strtolower($texte);
    $texte = preg_replace('/[^a-z0-9]+/', '-', $texte);
    $texte = trim($texte, '-');
    return $texte !== '' ? $texte : 'produit';
}

function genererTokenAffiliation(int $produitId, int $refId): string
{
    $signature = hash_hmac('sha256', $produitId . '|' . $refId, SECRET_AFFILIATION);
    $donnees   = $produitId . '|' . $refId . '|' . $signature;
    return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
}

$total_produits = count($produits);
$commission_moyenne = $total_produits > 0
    ? array_sum(array_map(fn($p) => calculerCommission((float) ($p['prix'] ?? 0)), $produits)) / $total_produits
    : 0;
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Catalogue Affiliation</title>
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
          fontFamily: {
            display: ['"Sora"', 'sans-serif'],
            sans: ['"Inter"', 'sans-serif']
          },
          colors: {
            ink: { DEFAULT: '#12213D', soft: '#3A4A6B' },
            paper: '#F5F7FB',
            line: '#E1E6F0',
            brand: { DEFAULT: '#1E3F8F', dark: '#152C66', light: '#2F62D6', soft: '#E8EEFC' },
            ok: { DEFAULT: '#137A55', soft: '#E3F5EC' },
            warn: { DEFAULT: '#B4720F', soft: '#FBF0DA' }
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .font-display { font-family: 'Sora', sans-serif; }
    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    html, body { overflow-x: hidden; max-width: 100%; }
    .tabular { font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="bg-paper dark:bg-[#0B1120] text-ink dark:text-slate-100 min-h-screen transition-colors duration-300">

<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>

<div class="min-h-screen flex flex-col pb-24 lg:pl-64">

  <header class="bg-transparent px-4 lg:px-8 pt-6 pb-2 flex items-center justify-between w-full">
    <div class="flex items-center gap-3">
      <button onclick="toggleSidebar()" type="button" aria-label="Ouvrir le menu"
              class="lg:hidden w-10 h-10 rounded-full bg-white dark:bg-[#141E33] flex items-center justify-center border border-line dark:border-slate-800 shrink-0">
        <svg class="w-5 h-5 text-ink dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <a href="/dashboard.php" aria-label="Retour au dashboard"
         class="w-10 h-10 rounded-full bg-white dark:bg-[#141E33] flex items-center justify-center border border-line dark:border-slate-800 shrink-0 hover:border-brand/40 transition-colors">
        <svg class="w-5 h-5 text-ink dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
      </a>
      <div>
        <h1 class="font-display font-bold text-[20px] text-ink dark:text-white leading-tight">Catalogue Affiliation</h1>
        <p class="text-[12px] text-ink/45 dark:text-slate-400 mt-0.5">Générez et partagez vos liens d'affiliation en un clic</p>
      </div>
    </div>
    <button onclick="toggleTheme()" type="button" aria-label="Changer le thème"
            class="w-10 h-10 rounded-full bg-white dark:bg-[#141E33] flex items-center justify-center border border-line dark:border-slate-800 shrink-0">
      <svg class="w-4 h-4 text-ink dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg>
    </button>
  </header>

  <main class="flex-1 px-4 lg:px-8 py-4 w-full flex flex-col gap-5">

    <form action="" method="GET" class="relative w-full sm:w-96">
      <input type="text" name="q" value="<?= htmlspecialchars($recherche) ?>" placeholder="Chercher un produit..."
             class="w-full bg-white dark:bg-[#141E33] border border-line dark:border-slate-800 rounded-full pl-5 pr-12 py-3 text-[13px] font-medium text-ink dark:text-white outline-none focus:border-brand transition-colors">
      <button type="submit" aria-label="Rechercher" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-gradient-to-br from-brand to-brand-light text-white flex items-center justify-center">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      </button>
    </form>

    <?php if (!empty($message_success)): ?>
      <div role="status" class="p-4 bg-ok-soft text-ok text-[13px] font-medium rounded-xl border border-ok/15 text-center">
        <?= htmlspecialchars($message_success) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($message_error)): ?>
      <div role="alert" class="p-4 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 text-[13px] font-medium rounded-xl border border-red-200 dark:border-red-500/20 text-center">
        <?= htmlspecialchars($message_error) ?>
      </div>
    <?php endif; ?>

    <?php if (empty($produits)): ?>
      <div class="bg-white dark:bg-[#141E33] rounded-2xl p-10 text-center border border-line dark:border-slate-800">
        <p class="text-ink/45 dark:text-slate-400 text-[13px] font-medium">
          <?= $recherche !== '' ? "Aucun produit ne correspond à \"" . htmlspecialchars($recherche) . "\"." : "Aucun produit disponible pour le moment." ?>
        </p>
      </div>
    <?php else: ?>
      <div class="flex items-center justify-between px-1">
        <h2 class="font-semibold text-[11.5px] text-ink/45 dark:text-slate-400 uppercase tracking-wide">
          <?= $total_produits ?> produit<?= $total_produits > 1 ? 's' : '' ?> disponible<?= $total_produits > 1 ? 's' : '' ?>
        </h2>
        <span class="text-[12px] text-ink/45 dark:text-slate-400">Commission moyenne : <span class="font-semibold text-brand tabular"><?= number_format($commission_moyenne, 0, ',', ' ') ?> FCFA</span></span>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <?php foreach ($produits as $produit): ?>
          <?php
            $produit_id          = (int) $produit['id'];
            $produit_nom         = $produit['nom'] ?? 'Produit sans nom';
            $produit_description = $produit['description'] ?? '';
            $produit_image       = !empty($produit['image']) ? '/admin/' . $produit['image'] : '/assets/img/produit-placeholder.png';
            $produit_prix_brut   = (float) ($produit['prix'] ?? 0);
            $produit_prix        = number_format($produit_prix_brut, 0, ',', ' ');

            $commission_montant_brut = calculerCommission($produit_prix_brut);
            $commission_montant      = number_format($commission_montant_brut, 0, ',', ' ');

            $slug = slugify($produit_nom);
            $token = genererTokenAffiliation($produit_id, (int) $user_id);
            $lien_affiliation = BASE_URL_SITE . '/produit/' . $slug . '/' . $token;
          ?>
          <div class="group relative bg-white dark:bg-[#141E33] rounded-xl border border-line dark:border-slate-800 overflow-hidden flex flex-col transition-all duration-200 hover:shadow-lg hover:shadow-brand/10 dark:hover:shadow-none hover:-translate-y-0.5 hover:border-brand/40">

            <div class="relative w-full aspect-square bg-brand-soft dark:bg-slate-800/40 flex items-center justify-center p-4">
              <span class="absolute top-2.5 left-2.5 z-10 bg-gradient-to-r from-brand to-brand-light backdrop-blur-sm text-white text-[10px] font-bold px-2 py-1 rounded-full shadow-sm">
                +<?= $commission_montant ?> FCFA
              </span>
              <img src="<?= htmlspecialchars($produit_image) ?>" alt="<?= htmlspecialchars($produit_nom) ?>"
                   class="max-w-full max-h-full w-auto h-auto object-contain transition-transform duration-300 group-hover:scale-105" loading="lazy"
                   onerror="this.src='/assets/img/produit-placeholder.png'">
            </div>

            <div class="p-3.5 flex flex-col gap-2 flex-1">

              <div>
                <h3 class="text-[12.5px] font-semibold text-ink dark:text-slate-100 leading-snug line-clamp-2 min-h-[32px]">
                  <?= htmlspecialchars($produit_nom) ?>
                </h3>
                <?php if (!empty($produit_description)): ?>
                  <p class="text-[10.5px] text-ink/40 dark:text-slate-500 mt-0.5 line-clamp-1"><?= htmlspecialchars($produit_description) ?></p>
                <?php endif; ?>
              </div>

              <div class="flex items-baseline gap-1">
                <span class="font-display font-bold text-[16px] text-ink dark:text-white tabular"><?= $produit_prix ?></span>
                <span class="text-[10px] text-ink/40 dark:text-slate-500 font-semibold">FCFA</span>
              </div>

              <div class="flex items-center justify-between bg-brand-soft dark:bg-brand/10 rounded-lg px-2.5 py-1.5">
                <span class="text-[10px] text-brand dark:text-blue-300 font-semibold">Commission</span>
                <span class="text-[11px] font-bold text-brand dark:text-blue-300 tabular"><?= $commission_montant ?> FCFA</span>
              </div>

              <div class="mt-auto pt-1">
                <button type="button" data-produit-id="<?= $produit_id ?>"
                        class="btn-generer-lien w-full bg-gradient-to-r from-brand to-brand-light hover:opacity-90 text-white font-semibold text-[11.5px] py-2.5 rounded-xl transition-all active:scale-[0.97] flex items-center justify-center gap-1.5 shadow-sm shadow-brand/20">
                  <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                  Générer le lien
                </button>

                <div id="lien-zone-<?= $produit_id ?>" class="hidden items-center gap-1.5 mt-2">
                  <input type="text" readonly id="lien-input-<?= $produit_id ?>" value="<?= htmlspecialchars($lien_affiliation) ?>"
                         class="flex-1 min-w-0 bg-paper dark:bg-slate-900 border border-line dark:border-slate-800 rounded-lg px-2.5 py-2 text-[10px] text-ink/55 dark:text-slate-400 outline-none truncate font-mono">
                  <button type="button" onclick="copierLien(<?= $produit_id ?>)"
                          class="btn-copier shrink-0 bg-ink dark:bg-slate-700 hover:bg-ink/90 text-white text-[10px] font-bold py-2 px-3 rounded-lg transition-all active:scale-[0.97]">
                    Copier
                  </button>
                </div>
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

document.querySelectorAll('.btn-generer-lien').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const produitId = btn.getAttribute('data-produit-id');
    const zone = document.getElementById('lien-zone-' + produitId);
    if (!zone) return;
    zone.classList.remove('hidden');
    zone.classList.add('flex');
    btn.classList.add('hidden');
  });
});

function copierLien(produitId) {
  const input = document.getElementById('lien-input-' + produitId);
  if (!input) return;
  const lien = input.value;

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(lien).then(function () {
      afficherConfirmationCopie(produitId);
    }).catch(function () {
      copierLienAncienneMethode(input, produitId);
    });
  } else {
    copierLienAncienneMethode(input, produitId);
  }
}

function copierLienAncienneMethode(input, produitId) {
  input.select();
  input.setSelectionRange(0, 99999);
  try {
    document.execCommand('copy');
    afficherConfirmationCopie(produitId);
  } catch (err) {
    alert("Impossible de copier automatiquement. Merci de copier le lien manuellement.");
  }
}

function afficherConfirmationCopie(produitId) {
  const zone = document.getElementById('lien-zone-' + produitId);
  if (!zone) return;
  const btnCopier = zone.querySelector('.btn-copier');
  if (!btnCopier) return;

  const texteOriginal = btnCopier.textContent;
  btnCopier.textContent = 'Copié !';
  btnCopier.classList.add('bg-ok');
  setTimeout(function () {
    btnCopier.textContent = texteOriginal;
    btnCopier.classList.remove('bg-ok');
  }, 1800);
}
</script>
</body>
</html>