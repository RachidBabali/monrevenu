<?php
session_start();

// ============================================================
// 1. CONNEXION À LA BASE DE DONNÉES
// ============================================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';

// ============================================================
// 2. DÉTECTION AUTOMATIQUE HTTP / HTTPS
// ============================================================
$protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
define('BASE_URL', $protocole . $_SERVER['HTTP_HOST']);

// ============================================================
// 3. CLÉ SECRÈTE POUR LA SIGNATURE DES LIENS D'AFFILIATION
// ============================================================
define('SECRET_AFFILIATION', 'change-moi-avec-une-longue-cle-aleatoire-unique');

// ============================================================
// 3bis. RÈGLE DE COMMISSION FIXE (identique à boutique.php)
// ============================================================
/**
 * Calcule le montant de commission réel d'un produit à partir de son
 * pourcentage configuré par l'admin (colonne commission_pct).
 */
function calculerCommission(float $prix, float $commission_pct): float
{
    return round($prix * ($commission_pct / 100), 2);
}

// ============================================================
// 4. JETON CSRF (protection du formulaire de commande)
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$error = '';

if (isset($_SESSION['flash_message_commande'])) {
    $message = $_SESSION['flash_message_commande'];
    unset($_SESSION['flash_message_commande']);
}

/**
 * Décode un jeton d'affiliation généré par genererTokenAffiliation() dans
 * boutique.php. Retourne [produit_id, ref_id] si valide, sinon [0, 0].
 */
function decoderTokenAffiliation(string $token): array
{
    $donnees = base64_decode(strtr($token, '-_', '+/'));
    if ($donnees === false) {
        return [0, 0];
    }

    $parties = explode('|', $donnees);
    if (count($parties) !== 3) {
        return [0, 0];
    }

    [$produitId, $refId, $sigRecue] = $parties;
    $produitId = (int) $produitId;
    $refId     = (int) $refId;

    $sigAttendue = hash_hmac('sha256', $produitId . '|' . $refId, SECRET_AFFILIATION);
    if (!hash_equals($sigAttendue, $sigRecue)) {
        return [0, 0];
    }

    return [$produitId, $refId];
}

// ============================================================
// 5. LECTURE ET VALIDATION DES PARAMÈTRES DE L'URL
// ============================================================
$produit_id = 0;
$ref_id     = 0;

if (!empty($_GET['token'])) {
    [$produit_id, $ref_id] = decoderTokenAffiliation($_GET['token']);
} else {
    $produit_id = (int) ($_GET['id'] ?? 0);
    $ref_id_brut = (int) ($_GET['ref'] ?? 0);
    $sig_recue   = $_GET['sig'] ?? '';

    if ($ref_id_brut > 0 && $sig_recue !== '') {
        $sig_attendue = hash_hmac('sha256', $produit_id . '|' . $ref_id_brut, SECRET_AFFILIATION);
        if (hash_equals($sig_attendue, $sig_recue)) {
            $ref_id = $ref_id_brut;
        }
    } elseif ($ref_id_brut > 0) {
        $ref_id = $ref_id_brut;
    }
}

$ref_valide = $ref_id > 0;

$vendeur = null;
if ($ref_valide && $ref_id > 0) {
    $stmtRef = $pdo->prepare("SELECT id, fullname, role FROM users_monrevenu WHERE id = ? LIMIT 1");
    $stmtRef->execute([$ref_id]);
    $vendeur = $stmtRef->fetch();

    if (!$vendeur || !in_array($vendeur['role'], ['affilie', 'agent', 'admin'], true)) {
        $vendeur = null;
    }
}

// ============================================================
// 6. RÉCUPÉRATION DU PRODUIT
// ============================================================
$produit = null;
if ($produit_id > 0) {
    $stmtProduit = $pdo->prepare(
        "SELECT id, nom_produit AS nom, description, image, prix_vente AS prix, commission_pct AS commission_pourcentage
         FROM vendeur_produits
         WHERE id = ? AND statut = 'actif'
         LIMIT 1"
    );
    $stmtProduit->execute([$produit_id]);
    $produit = $stmtProduit->fetch();
}

// ============================================================
// 6bis. TRAITEMENT DE LA COMMANDE (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_commander'])) {

    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error = "Session expirée, merci de recharger la page et réessayer.";
    } elseif (!$produit) {
        $error = "Ce produit n'est plus disponible.";
    } elseif (!$vendeur) {
        $error = "Ce lien de parrainage n'est pas valide, la commande ne peut pas être enregistrée.";
    } else {
        $nom_client       = trim($_POST['nom_client'] ?? '');
        $telephone_client = trim($_POST['telephone_client'] ?? '');
        $adresse_client   = trim($_POST['adresse_client'] ?? '');
        $quantite         = max(1, (int) ($_POST['quantite'] ?? 1));

        if ($nom_client === '' || $telephone_client === '') {
            $error = "Merci de renseigner votre nom et votre numéro WhatsApp.";
        } elseif (mb_strlen($nom_client) > 120 || mb_strlen($telephone_client) > 30) {
            $error = "Le nom ou le numéro renseigné est trop long.";
        } else {
            $prix_unitaire        = (float) $produit['prix'];
            $commission_pct       = (float) $produit['commission_pourcentage'];
            $commission_unitaire  = calculerCommission($prix_unitaire, $commission_pct);
            $commission_totale    = $commission_unitaire * $quantite;

            try {
                $stmtVente = $pdo->prepare(
                    "INSERT INTO vendeur_ventes
                        (produit_id, vendeur_id, quantite, prix_unitaire, commission_pct, commission_earn,
                         nom_client, telephone_client, adresse_client, statut, commission_creditee)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', 0)"
                );
                $stmtVente->execute([
                    $produit['id'],
                    $vendeur['id'],
                    $quantite,
                    $prix_unitaire,
                    $commission_pct,
                    $commission_totale,
                    $nom_client,
                    $telephone_client,
                    $adresse_client !== '' ? $adresse_client : null,
                ]);

                $_SESSION['flash_message_commande'] = "Merci {$nom_client}, votre commande a bien été enregistrée. Le vendeur va vous contacter sur WhatsApp au {$telephone_client} pour confirmer.";
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            } catch (PDOException $e) {
                $error = "Une erreur est survenue lors de l'enregistrement de votre commande. Merci de réessayer.";
            }
        }
    }
}

// ============================================================
// 6ter. DONNÉES POUR L'APERÇU DE PARTAGE (Open Graph / Twitter Card)
// ============================================================
if ($produit) {
    $og_titre       = $produit['nom'] . ' — ' . number_format((float) $produit['prix'], 0, ',', ' ') . ' FCFA';
    $og_description = !empty($produit['description'])
        ? mb_substr(trim($produit['description']), 0, 160)
        : 'Découvrez ce produit disponible sur MonRevenu.';
    $imageProduit = $produit['image'] ?? '';
    if (str_starts_with($imageProduit, 'data:')) {
        // Une data URI n'est pas utilisable comme image de partage (WhatsApp/Facebook
        // exigent une vraie URL http/https) — on retombe sur le placeholder générique.
        $og_image = BASE_URL . '/assets/img/produit-placeholder.png';
    } elseif (preg_match('#^https?://#', $imageProduit)) {
        // Déjà une URL absolue (image hébergée sur R2/cdn.monrevenu.xyz)
        $og_image = $imageProduit;
    } elseif ($imageProduit !== '') {
        // Ancien chemin local relatif (produit créé avant la migration vers R2)
        $og_image = BASE_URL . '/admin/' . $imageProduit;
    } else {
        $og_image = BASE_URL . '/assets/img/produit-placeholder.png';
    }
} else {
    $og_titre       = 'Produit indisponible — MonRevenu';
    $og_description = 'Ce lien n\'est plus valide ou le produit n\'est plus disponible à la vente.';
    $og_image       = BASE_URL . '/assets/img/produit-placeholder.png';
}
$og_url = BASE_URL . '/produit.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($og_titre) ?></title>

  <meta property="og:type" content="product">
  <meta property="og:title" content="<?= htmlspecialchars($og_titre) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
  <meta property="og:image:width" content="800">
  <meta property="og:image:height" content="800">
  <meta property="og:url" content="<?= htmlspecialchars($og_url) ?>">
  <meta property="og:site_name" content="MonRevenu">
  <?php if ($produit): ?>
    <meta property="product:price:amount" content="<?= (float) $produit['prix'] ?>">
    <meta property="product:price:currency" content="XOF">
  <?php endif; ?>

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($og_titre) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($og_description) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($og_image) ?>">

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
    html, body { overflow-x: hidden; max-width: 100%; }
    .tabular { font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="bg-paper dark:bg-[#0B1120] text-ink dark:text-slate-100 min-h-screen transition-colors duration-300">

<div class="min-h-screen flex flex-col items-center px-4 py-8 lg:py-14">

  <header class="w-full max-w-3xl flex items-center justify-between mb-7">
    <div class="flex items-center gap-2.5">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand to-brand-light flex items-center justify-center text-white font-display font-bold text-[15px]">M</div>
      <span class="font-display font-bold text-ink dark:text-white text-[16px] tracking-tight">MonRevenu</span>
    </div>
    <button onclick="toggleTheme()" type="button" aria-label="Changer le thème"
            class="w-9 h-9 rounded-full bg-white dark:bg-[#141E33] flex items-center justify-center border border-line dark:border-slate-800">
      <svg class="w-4 h-4 text-ink dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg>
    </button>
  </header>

  <main class="w-full max-w-3xl">

    <?php if (!$produit): ?>

      <div class="bg-white dark:bg-[#141E33] rounded-2xl p-10 text-center border border-line dark:border-slate-800">
        <p class="font-display font-semibold text-ink dark:text-white text-[16px] mb-2">Produit introuvable</p>
        <p class="text-ink/50 dark:text-slate-400 text-[13px]">Ce lien n'est plus valide ou le produit n'est plus disponible à la vente.</p>
      </div>

    <?php elseif ($message): ?>

      <div class="bg-white dark:bg-[#141E33] rounded-2xl p-10 text-center border border-line dark:border-slate-800">
        <div class="w-14 h-14 rounded-full bg-ok-soft text-ok flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
        </div>
        <p class="font-display font-semibold text-ink dark:text-white text-[17px] mb-1.5">Commande enregistrée</p>
        <p class="text-ink/55 dark:text-slate-400 text-[13px] leading-relaxed max-w-sm mx-auto"><?= htmlspecialchars($message) ?></p>
      </div>

    <?php else: ?>

      <div class="bg-white dark:bg-[#141E33] rounded-2xl border border-line dark:border-slate-800 overflow-hidden">

        <div class="relative w-full h-[260px] sm:h-[320px] bg-brand-soft dark:bg-slate-800/60 flex items-center justify-center p-6">
          <img src="<?= htmlspecialchars(BASE_URL . '/admin/' . $produit['image']) ?>" alt="<?= htmlspecialchars($produit['nom']) ?>"
               class="max-w-full max-h-full w-auto h-auto object-contain"
               onerror="this.src='<?= htmlspecialchars(BASE_URL . '/assets/img/produit-placeholder.png') ?>'">
        </div>

        <div class="p-6 sm:p-8">

          <p class="text-[10.5px] font-semibold text-brand uppercase tracking-[0.12em] mb-2">Article disponible</p>
          <h1 class="font-display font-bold text-[22px] sm:text-[26px] text-ink dark:text-white mb-2 leading-snug"><?= htmlspecialchars($produit['nom']) ?></h1>
          <p class="text-[13px] text-ink/60 dark:text-slate-300 mb-6 leading-relaxed"><?= nl2br(htmlspecialchars($produit['description'])) ?></p>

          <div class="flex items-baseline gap-1.5 mb-7 pb-6 border-b border-line dark:border-slate-800">
            <span class="font-display font-bold text-[28px] sm:text-[32px] text-ink dark:text-white tabular"><?= number_format((float) $produit['prix'], 0, ',', ' ') ?></span>
            <span class="text-[13px] text-ink/45 dark:text-slate-500 font-medium">kmf</span>
          </div>

          <?php if (!empty($error)): ?>
            <div role="alert" class="p-3.5 mb-5 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 text-[13px] font-medium rounded-xl border border-red-200 dark:border-red-500/20 text-center">
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <?php if (!$vendeur): ?>
            <div class="p-3.5 mb-5 bg-warn-soft text-warn text-[12.5px] font-medium rounded-xl border border-warn/20 text-center">
              Ce lien de parrainage n'est pas reconnu. Vous pouvez consulter le produit, mais la commande ne peut pas être passée tant que le lien n'est pas valide.
            </div>
          <?php endif; ?>

          <p class="text-[11.5px] font-semibold text-ink/70 dark:text-slate-300 uppercase tracking-wide mb-3.5">Vos coordonnées</p>

          <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div>
              <label class="block text-[12px] font-medium text-ink/55 dark:text-slate-400 mb-1.5">Nom complet *</label>
              <input type="text" name="nom_client" required value="<?= htmlspecialchars($_POST['nom_client'] ?? '') ?>"
                     class="w-full bg-paper dark:bg-slate-900 border border-line dark:border-slate-700 rounded-lg px-3.5 py-2.5 text-[13.5px] text-ink dark:text-white outline-none focus:border-brand focus:ring-1 focus:ring-brand/25 transition-all">
            </div>

            <div>
              <label class="block text-[12px] font-medium text-ink/55 dark:text-slate-400 mb-1.5">Numéro WhatsApp *</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ok">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2Zm5.8 14.14c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.13.11-1.82-.12-.42-.14-.96-.32-1.65-.62-2.9-1.25-4.79-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3 0-1.42.75-2.12 1.01-2.41.26-.29.58-.36.77-.36.19 0 .39 0 .55.01.18.01.41-.07.64.49.24.58.81 2 .88 2.14.07.15.12.32.02.51-.1.19-.15.31-.3.48-.15.17-.31.37-.44.5-.15.15-.3.31-.13.6.17.29.75 1.24 1.62 2.01 1.11.99 2.05 1.3 2.34 1.45.29.15.46.12.63-.07.17-.19.72-.84.91-1.13.19-.29.38-.24.63-.14.26.1 1.65.78 1.93.92.29.15.48.22.55.34.07.13.07.72-.17 1.4Z"/></svg>
                </span>
                <input type="tel" name="telephone_client" required placeholder="Ex : +269......" value="<?= htmlspecialchars($_POST['telephone_client'] ?? '') ?>"
                       class="w-full bg-paper dark:bg-slate-900 border border-line dark:border-slate-700 rounded-lg pl-9 pr-3.5 py-2.5 text-[13.5px] text-ink dark:text-white outline-none focus:border-brand focus:ring-1 focus:ring-brand/25 transition-all">
              </div>
              <p class="text-[10.5px] text-ink/40 dark:text-slate-500 mt-1.5">Le vendeur vous contactera sur ce numéro via WhatsApp pour confirmer votre commande.</p>
            </div>

            <div>
              <label class="block text-[12px] font-medium text-ink/55 dark:text-slate-400 mb-1.5">Adresse de livraison</label>
              <textarea name="adresse_client" rows="2"
                        class="w-full bg-paper dark:bg-slate-900 border border-line dark:border-slate-700 rounded-lg px-3.5 py-2.5 text-[13.5px] text-ink dark:text-white outline-none focus:border-brand focus:ring-1 focus:ring-brand/25 transition-all"><?= htmlspecialchars($_POST['adresse_client'] ?? '') ?></textarea>
            </div>

            <div>
              <label class="block text-[12px] font-medium text-ink/55 dark:text-slate-400 mb-1.5">Quantité</label>
              <input type="number" name="quantite" value="<?= htmlspecialchars($_POST['quantite'] ?? '1') ?>" min="1"
                     class="w-full bg-paper dark:bg-slate-900 border border-line dark:border-slate-700 rounded-lg px-3.5 py-2.5 text-[13.5px] text-ink dark:text-white outline-none focus:border-brand focus:ring-1 focus:ring-brand/25 transition-all">
            </div>

            <button type="submit" name="action_commander" <?= !$vendeur ? 'disabled' : '' ?>
                    class="w-full bg-gradient-to-r from-brand to-brand-light hover:opacity-90 disabled:opacity-35 disabled:cursor-not-allowed text-white text-[13.5px] font-semibold py-3.5 rounded-lg transition-all mt-1">
              Passer la commande
            </button>
          </form>

        </div>
      </div>

    <?php endif; ?>

  </main>

</div>

<script>
function toggleTheme() {
  document.documentElement.classList.toggle('dark');
  localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
}
</script>
</body>
</html>