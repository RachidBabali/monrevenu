<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
exigerAffiliationDebloquee($pdo);

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: /index.php'); exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_enregistrer_vente'])) {

    $token_recu = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token_recu)) {
        $_SESSION['flash_error'] = "Session expirée, merci de réessayer.";
        header('Location: mon-stock.php'); exit();
    }

    $produit_id = (int) ($_POST['produit_id'] ?? 0);
    $quantite   = (int) ($_POST['quantite'] ?? 0);

    if ($produit_id <= 0 || $quantite <= 0) {
        $_SESSION['flash_error'] = "Merci de choisir un produit et une quantité valide.";
        header('Location: mon-stock.php'); exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT sr.quantite_disponible, vp.nom_produit, vp.prix_vente, vp.commission_fixe
             FROM stocks_revendeurs sr
             JOIN produits_stock vp ON vp.id = sr.produit_id
             WHERE sr.user_id = ? AND sr.produit_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$user_id, $produit_id]);
        $ligne = $stmt->fetch();

        if (!$ligne) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Ce produit ne fait pas partie de votre stock.";
            header('Location: mon-stock.php'); exit();
        }

        $stock_avant = (int) $ligne['quantite_disponible'];

        if ($quantite > $stock_avant) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Stock insuffisant. Vous disposez seulement de {$stock_avant} unités.";
            header('Location: mon-stock.php'); exit();
        }

        $stock_apres      = $stock_avant - $quantite;
        $prix_unitaire    = (float) $ligne['prix_vente'];
        $montant_total    = $prix_unitaire * $quantite;
        $commission_montant = $quantite * (float) $ligne['commission_fixe'];

        $pdo->prepare("UPDATE stocks_revendeurs SET quantite_disponible = ? WHERE user_id = ? AND produit_id = ?")
            ->execute([$stock_apres, $user_id, $produit_id]);

        $stmtVente = $pdo->prepare(
            "INSERT INTO ventes_stock (user_id, produit_id, quantite, prix_unitaire, montant_total, commission_montant, commission_envoyee, reference)
             VALUES (?, ?, ?, ?, ?, ?, 0, '')"
        );
        $stmtVente->execute([$user_id, $produit_id, $quantite, $prix_unitaire, $montant_total, $commission_montant]);
        $vente_id  = (int) $pdo->lastInsertId();
        $reference = 'VENTE-STOCK-' . $vente_id;

        $pdo->prepare("UPDATE ventes_stock SET reference = ? WHERE id = ?")
            ->execute([$reference, $vente_id]);

        $pdo->prepare(
            "INSERT INTO mouvements_stock (user_id, produit_id, type_mouvement, quantite, stock_avant, stock_apres, reference, effectue_par)
             VALUES (?, ?, 'SALE', ?, ?, ?, ?, ?)"
        )->execute([$user_id, $produit_id, $quantite, $stock_avant, $stock_apres, $reference, $user_id]);

        $pdo->commit();

        $_SESSION['flash_success'] = "Vente enregistrée : {$quantite} × " . htmlspecialchars($ligne['nom_produit']) . ". Commission de " . number_format($commission_montant, 0, ',', ' ') . " KMF en attente d'envoi par l'administration.";

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erreur enregistrement vente stock : ' . $e->getMessage());
        $_SESSION['flash_error'] = "Une erreur est survenue. Veuillez réessayer.";
    }

    header('Location: mon-stock.php'); exit();
}

$stmtStock = $pdo->prepare(
    "SELECT
        vp.id AS produit_id,
        vp.nom_produit,
        vp.image,
        vp.prix_vente,
        vp.commission_fixe,
        sr.quantite_disponible,
        COALESCE((SELECT SUM(vs.quantite) FROM ventes_stock vs WHERE vs.user_id = sr.user_id AND vs.produit_id = sr.produit_id), 0) AS quantite_vendue
     FROM stocks_revendeurs sr
     JOIN produits_stock vp ON vp.id = sr.produit_id
     WHERE sr.user_id = ?
     ORDER BY vp.nom_produit ASC"
);
$stmtStock->execute([$user_id]);
$mon_stock = $stmtStock->fetchAll();

$stmtVentes = $pdo->prepare(
    "SELECT vs.id, vs.quantite, vs.prix_unitaire, vs.montant_total, vs.commission_montant, vs.commission_envoyee, vs.reference, vs.created_at,
            vp.nom_produit, vp.image
     FROM ventes_stock vs
     JOIN produits_stock vp ON vp.id = vs.produit_id
     WHERE vs.user_id = ?
     ORDER BY vs.created_at DESC
     LIMIT 50"
);
$stmtVentes->execute([$user_id]);
$mes_ventes = $stmtVentes->fetchAll();

$stock_total_unites = array_sum(array_column($mon_stock, 'quantite_disponible'));
$nb_produits_stock  = count($mon_stock);
$nb_ventes_total    = count($mes_ventes);
$chiffre_affaires   = array_sum(array_column($mes_ventes, 'montant_total'));
$commissions_gagnees = array_sum(array_column($mes_ventes, 'commission_montant'));
$commissions_en_attente = array_sum(array_map(
    fn($v) => (int) $v['commission_envoyee'] === 0 ? (float) $v['commission_montant'] : 0,
    $mes_ventes
));

define('SEUIL_STOCK_FAIBLE', 5);
$produits_stock_faible = array_filter($mon_stock, fn($p) => (int) $p['quantite_disponible'] <= SEUIL_STOCK_FAIBLE && (int) $p['quantite_disponible'] > 0);
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Mon Stock</title>
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
          fontFamily:{
            display:['"Sora"','sans-serif'],
            sans:['"Inter"','sans-serif']
          },
          colors:{
            ink:{DEFAULT:'#12213D',soft:'#3A4A6B'},
            paper:'#F5F7FB',
            line:'#E1E6F0',
            brand:{DEFAULT:'#1E3F8F',dark:'#152C66',light:'#2F62D6',soft:'#E8EEFC'},
            ok:{DEFAULT:'#137A55',soft:'#E3F5EC'},
            warn:{DEFAULT:'#B4720F',soft:'#FBF0DA'}
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    body{font-family:'Inter',sans-serif;}
    .font-display{font-family:'Sora',sans-serif;}
    .tabular{font-variant-numeric: tabular-nums;}
  </style>
</head>
<body class="bg-paper dark:bg-[#0B1120] text-ink dark:text-slate-100 min-h-screen transition-colors duration-300">

<div class="min-h-screen flex flex-col pb-24 lg:pl-64">

  <header class="px-4 lg:px-8 pt-7 pb-5 max-w-3xl lg:max-w-5xl w-full mx-auto">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button onclick="toggleSidebar()" type="button" aria-label="Ouvrir le menu"
                class="lg:hidden w-9 h-9 rounded-full bg-white dark:bg-[#141E33] flex items-center justify-center border border-line dark:border-slate-800 shrink-0">
          <svg class="w-4 h-4 text-ink dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
        <a href="/dashboard.php" aria-label="Retour au dashboard"
           class="w-9 h-9 rounded-full bg-white dark:bg-[#141E33] flex items-center justify-center border border-line dark:border-slate-800 shrink-0 hover:border-brand/50 transition-colors">
          <svg class="w-4 h-4 text-ink dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
          </svg>
        </a>
      </div>
      <button onclick="toggleTheme()" type="button" aria-label="Changer le thème"
              class="w-9 h-9 rounded-full bg-white dark:bg-[#141E33] flex items-center justify-center border border-line dark:border-slate-800 shrink-0">
        <svg class="w-4 h-4 text-ink dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg>
      </button>
    </div>
    <h1 class="font-display font-bold text-[26px] text-ink dark:text-white mt-4">Mon stock</h1>
    <p class="text-[13px] text-ink/50 dark:text-slate-400 mt-0.5">Suivi de vos produits et de vos commissions</p>
  </header>

  <main class="flex-1 px-4 lg:px-8 max-w-3xl lg:max-w-5xl w-full mx-auto flex flex-col gap-5">

    <?php if (!empty($message_success)): ?>
      <div role="status" class="px-4 py-3.5 bg-ok-soft text-ok text-[13px] font-medium rounded-xl border border-ok/15">
        <?= htmlspecialchars($message_success) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($message_error)): ?>
      <div role="alert" class="px-4 py-3.5 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 text-[13px] font-medium rounded-xl border border-red-200 dark:border-red-500/20">
        <?= htmlspecialchars($message_error) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($produits_stock_faible)): ?>
      <div class="flex flex-col gap-2">
        <?php foreach ($produits_stock_faible as $p): ?>
          <div class="px-4 py-3 bg-warn-soft text-warn text-[12.5px] font-medium rounded-xl border border-warn/15 flex items-center gap-2.5">
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
            <span><span class="font-semibold"><?= htmlspecialchars($p['nom_produit']) ?></span> — plus que <?= (int) $p['quantite_disponible'] ?> unité<?= $p['quantite_disponible'] > 1 ? 's' : '' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <section class="bg-white dark:bg-[#141E33] rounded-2xl border border-line dark:border-slate-800/60 overflow-hidden">
      <div class="px-5 pt-5 pb-4 bg-gradient-to-br from-brand to-brand-light">
        <p class="text-[11.5px] font-semibold text-white/70 uppercase tracking-wide">Commissions gagnées</p>
        <p class="font-display font-bold text-[34px] leading-none text-white mt-2 tabular"><?= number_format($commissions_gagnees, 0, ',', ' ') ?> <span class="text-[16px] font-semibold text-white/70">KMF</span></p>
        <p class="text-[12.5px] text-white/75 mt-1.5">
          dont <span class="font-semibold text-white"><?= number_format($commissions_en_attente, 0, ',', ' ') ?> KMF</span> en attente d'envoi
        </p>
      </div>
      <div class="grid grid-cols-3 divide-x divide-line dark:divide-slate-800/60">
        <div class="px-4 py-3.5">
          <p class="text-[10.5px] text-ink/40 dark:text-slate-500 font-medium">Stock total</p>
          <p class="font-display font-bold text-[18px] text-ink dark:text-white mt-1 tabular"><?= $stock_total_unites ?></p>
          <p class="text-[10.5px] text-ink/35 dark:text-slate-500 mt-0.5"><?= $nb_produits_stock ?> produit<?= $nb_produits_stock > 1 ? 's' : '' ?></p>
        </div>
        <div class="px-4 py-3.5">
          <p class="text-[10.5px] text-ink/40 dark:text-slate-500 font-medium">Ventes</p>
          <p class="font-display font-bold text-[18px] text-ink dark:text-white mt-1 tabular"><?= $nb_ventes_total ?></p>
          <p class="text-[10.5px] text-ink/35 dark:text-slate-500 mt-0.5">enregistrées</p>
        </div>
        <div class="px-4 py-3.5">
          <p class="text-[10.5px] text-ink/40 dark:text-slate-500 font-medium">Chiffre d'affaires</p>
          <p class="font-display font-bold text-[18px] text-ink dark:text-white mt-1 tabular"><?= number_format($chiffre_affaires, 0, ',', ' ') ?></p>
          <p class="text-[10.5px] text-ink/35 dark:text-slate-500 mt-0.5">KMF</p>
        </div>
      </div>
    </section>

    <section class="bg-white dark:bg-[#141E33] rounded-2xl border border-line dark:border-slate-800/60 p-5">
      <h3 class="font-display font-semibold text-[15px] text-ink dark:text-white">Enregistrer une vente</h3>

      <?php if (empty($mon_stock)): ?>
        <p class="text-[12.5px] text-ink/45 dark:text-slate-400 mt-3">Vous n'avez encore aucun produit en stock. L'administration doit vous en attribuer pour que vous puissiez enregistrer des ventes.</p>
      <?php else: ?>
        <form action="" method="POST" class="flex flex-col gap-3.5 mt-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

          <div class="flex flex-col gap-1.5">
            <label for="produit_id" class="text-ink/55 dark:text-slate-400 font-medium text-[12px]">Produit</label>
            <select id="produit_id" name="produit_id" required
                    class="bg-paper dark:bg-slate-900 border border-line dark:border-slate-800 rounded-lg px-3.5 py-2.5 text-[13.5px] text-ink dark:text-slate-100 font-medium outline-none focus:border-brand focus:ring-1 focus:ring-brand/30 transition-all">
              <?php foreach ($mon_stock as $p): ?>
                <option value="<?= (int) $p['produit_id'] ?>" <?= $p['quantite_disponible'] <= 0 ? 'disabled' : '' ?>>
                  <?= htmlspecialchars($p['nom_produit']) ?> — <?= (int) $p['quantite_disponible'] ?> disponible<?= $p['quantite_disponible'] > 1 ? 's' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="flex flex-col gap-1.5">
            <label for="quantite" class="text-ink/55 dark:text-slate-400 font-medium text-[12px]">Quantité vendue</label>
            <input type="number" id="quantite" name="quantite" min="1" required
                   class="bg-paper dark:bg-slate-900 border border-line dark:border-slate-800 rounded-lg px-3.5 py-2.5 text-[13.5px] text-ink dark:text-slate-100 font-medium outline-none focus:border-brand focus:ring-1 focus:ring-brand/30 transition-all">
          </div>

          <button type="submit" name="action_enregistrer_vente"
                  class="bg-gradient-to-r from-brand to-brand-light hover:opacity-90 text-white font-semibold text-[13.5px] py-3 px-4 rounded-lg active:scale-[0.99] transition-all mt-1">
            Enregistrer la vente
          </button>
        </form>
      <?php endif; ?>
    </section>

    <section class="bg-white dark:bg-[#141E33] rounded-2xl border border-line dark:border-slate-800/60 overflow-hidden">
      <h3 class="font-display font-semibold text-[15px] text-ink dark:text-white px-5 pt-5 pb-4">Détail du stock</h3>

      <?php if (empty($mon_stock)): ?>
        <p class="text-[12.5px] text-ink/45 dark:text-slate-400 text-center py-6">Aucun stock attribué pour le moment.</p>
      <?php else: ?>
        <div class="divide-y divide-line dark:divide-slate-800/60">
          <?php foreach ($mon_stock as $p): $stock_initial = (int) $p['quantite_disponible'] + (int) $p['quantite_vendue']; ?>
            <div class="flex items-center gap-3.5 px-5 py-3.5">
              <img src="/admin/<?= htmlspecialchars($p['image']) ?>" alt="" class="w-10 h-10 rounded-lg object-cover border border-line dark:border-slate-800 shrink-0">
              <div class="min-w-0 flex-1">
                <p class="text-[13px] font-semibold text-ink dark:text-white truncate"><?= htmlspecialchars($p['nom_produit']) ?></p>
                <p class="text-[11.5px] text-ink/40 dark:text-slate-500 mt-0.5">Initial <?= $stock_initial ?> · Vendu <?= (int) $p['quantite_vendue'] ?></p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-[16px] font-display font-bold tabular <?= $p['quantite_disponible'] <= SEUIL_STOCK_FAIBLE ? 'text-warn' : 'text-ink dark:text-white' ?>"><?= (int) $p['quantite_disponible'] ?></p>
                <p class="text-[9.5px] text-ink/35 dark:text-slate-500 font-medium">disponible</p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="bg-white dark:bg-[#141E33] rounded-2xl border border-line dark:border-slate-800/60 overflow-hidden">
      <h3 class="font-display font-semibold text-[15px] text-ink dark:text-white px-5 pt-5 pb-4">Historique des ventes</h3>

      <?php if (empty($mes_ventes)): ?>
        <p class="text-[12.5px] text-ink/45 dark:text-slate-400 text-center py-6">Aucune vente enregistrée pour le moment.</p>
      <?php else: ?>
        <div class="divide-y divide-line dark:divide-slate-800/60">
          <?php foreach ($mes_ventes as $v): ?>
            <div class="flex items-center justify-between gap-3 px-5 py-3.5">
              <div class="flex items-center gap-3.5 min-w-0">
                <img src="/admin/<?= htmlspecialchars($v['image']) ?>" alt="" class="w-9 h-9 rounded-lg object-cover border border-line dark:border-slate-800 shrink-0">
                <div class="min-w-0">
                  <p class="text-[13px] font-semibold text-ink dark:text-white truncate">
                    <?= htmlspecialchars($v['nom_produit']) ?> <span class="text-ink/35 dark:text-slate-500 font-normal">#<?= (int) $v['id'] ?></span>
                  </p>
                  <p class="text-[11.5px] text-ink/40 dark:text-slate-500 mt-0.5 tabular">
                    <?= (int) $v['quantite'] ?> × <?= number_format((float) $v['prix_unitaire'], 0, ',', ' ') ?> KMF
                  </p>
                  <p class="text-[10.5px] text-ink/30 dark:text-slate-600 mt-0.5"><?= date('d/m/Y à H:i', strtotime($v['created_at'])) ?></p>
                </div>
              </div>
              <div class="text-right shrink-0">
                <p class="text-[13.5px] font-display font-bold text-ok tabular">+<?= number_format((float) $v['commission_montant'], 0, ',', ' ') ?></p>
                <span class="inline-block mt-1 text-[9.5px] font-semibold px-2 py-0.5 rounded-full <?= (int) $v['commission_envoyee'] === 1 ? 'bg-ok-soft text-ok' : 'bg-warn-soft text-warn' ?>">
                  <?= (int) $v['commission_envoyee'] === 1 ? 'Envoyée' : 'En attente' ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

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