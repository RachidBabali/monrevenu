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
// 3. CLÉ SECRÈTE ET RÈGLE DE COMMISSION — partagées avec boutique.php
//    (includs/affiliation_helpers.php) pour éviter toute divergence.
// ============================================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';

// ============================================================
// 3ter. VALIDATION DU NUMÉRO (Sénégal +221 ou Comores +269 uniquement)
// ============================================================
/**
 * Vérifie que le numéro correspond à un format sénégalais (+221) ou
 * comorien (+269). Accepte avec ou sans indicatif, espaces retirés.
 */
function validerTelephoneSenegalOuComores(string $tel): bool
{
    // On retire tout sauf les chiffres et le signe +
    $tel = preg_replace('/[^\d+]/', '', $tel);

    // Sénégal : +221 suivi de 9 chiffres commençant par 7 (mobile)
    if (preg_match('/^(\+221|00221)?7[0-8]\d{7}$/', $tel)) {
        return true;
    }

    // Comores : +269 suivi de 7 chiffres commençant par 3 ou 4 (mobile)
    if (preg_match('/^(\+269|00269)?[34]\d{6}$/', $tel)) {
        return true;
    }

    return false;
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
    // is_active=1 AND status='active' : un compte suspendu ou supprimé ne doit
    // plus jamais faire créditer de commission via un ancien lien d'affiliation.
    $stmtRef = $pdo->prepare("SELECT id, fullname, role FROM users_monrevenu WHERE id = ? AND is_active = 1 AND status = 'active' LIMIT 1");
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
        $error = "Votre session a expiré. Rechargez la page puis validez à nouveau la commande.";
    } elseif (!$produit) {
        $error = "Ce produit n'est plus disponible.";
    } elseif (!$vendeur) {
        $error = "Ce lien n'est plus valide : la commande ne peut pas être enregistrée. Demandez un nouveau lien à la personne qui vous l'a envoyé.";
    } else {
        $nom_client       = trim($_POST['nom_client'] ?? '');
        $telephone_client = trim($_POST['telephone_client'] ?? '');
        $adresse_client   = trim($_POST['adresse_client'] ?? '');
        $quantite         = max(1, (int) ($_POST['quantite'] ?? 1));

        if ($nom_client === '' || $telephone_client === '') {
            $error = "Indiquez votre nom et votre numéro WhatsApp.";
        } elseif (mb_strlen($nom_client) > 120 || mb_strlen($telephone_client) > 30) {
            $error = "Le nom ou le numéro renseigné est trop long.";
        } elseif (!validerTelephoneSenegalOuComores($telephone_client)) {
            $error = "Numéro WhatsApp invalide. Indiquez un numéro du Sénégal (+221) ou des Comores (+269).";
        } else {
            $prix_unitaire        = (float) $produit['prix'];
            $commission_unitaire  = calculerCommission($prix_unitaire);
            $commission_totale    = $commission_unitaire * $quantite;

            try {
                $stmtVente = $pdo->prepare(
                    "INSERT INTO vendeur_ventes
                        (produit_id, vendeur_id, quantite, prix_unitaire, commission_pct, commission_earn,
                         nom_client, telephone_client, adresse_client, statut, commission_creditee)
                     VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 'en_attente', 0)"
                );
                $stmtVente->execute([
                    $produit['id'],
                    $vendeur['id'],
                    $quantite,
                    $prix_unitaire,
                    $commission_totale,
                    $nom_client,
                    $telephone_client,
                    $adresse_client !== '' ? $adresse_client : null,
                ]);

                require_once __DIR__ . '/includs/notifications.php';
                envoyerNotification(
                    $pdo,
                    (int) $vendeur['id'],
                    "Nouvelle commande en attente sur votre lien : " . $quantite . " x " . $produit['nom'] . ". Commission prévue : " . formaterMontant($commission_totale) . ".",
                    'Nouvelle vente en attente',
                    '/page/historique.php'
                );

                $_SESSION['flash_message_commande'] = "Merci {$nom_client}, votre commande est enregistrée. Vous serez contacté sur WhatsApp au {$telephone_client} pour confirmer la livraison. Le paiement se fait à la livraison.";
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            } catch (PDOException $e) {
                $error = "La commande n'a pas pu être enregistrée. Réessayez dans un instant.";
            }
        }
    }
}

// ============================================================
// 6ter. DONNÉES POUR L'APERÇU DE PARTAGE (Open Graph / Twitter Card)
// ============================================================
if ($produit) {
    $og_titre       = $produit['nom'] . ' : ' . formaterMontant($produit['prix']);
    $og_description = !empty($produit['description'])
        ? mb_substr(trim($produit['description']), 0, 160)
        : 'Produit disponible sur MonRevenu, paiement à la livraison.';
    $imageProduit = $produit['image'] ?? '';
    if (str_starts_with($imageProduit, 'data:')) {
        // Une data URI n'est pas utilisable comme image de partage (WhatsApp/Facebook
        // exigent une vraie URL http/https) : on retombe sur le placeholder générique.
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
    $og_titre       = 'Produit indisponible | MonRevenu';
    $og_description = 'Ce lien n\'est plus valide ou le produit n\'est plus disponible à la vente.';
    $og_image       = BASE_URL . '/assets/img/produit-placeholder.png';
}
$og_url = BASE_URL . '/produit.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
$image_brute = (string) ($produit['image'] ?? '');
$image_produit = $image_brute === '' || str_starts_with($image_brute, 'data:') ? $image_brute
    : (preg_match('#^https?://#', $image_brute) ? $image_brute : '/admin/' . $image_brute);
$quantite_saisie = max(1, (int) ($_POST['quantite'] ?? 1));
$prix_produit = (float) ($produit['prix'] ?? 0);

$titre_page       = $og_titre;
$description_page = $og_description;
$page_publique    = true;
$head_supp = '<meta property="og:type" content="product">'
    . '<meta property="og:title" content="' . e($og_titre) . '">'
    . '<meta property="og:description" content="' . e($og_description) . '">'
    . '<meta property="og:image" content="' . e($og_image) . '">'
    . '<meta property="og:image:width" content="800"><meta property="og:image:height" content="800">'
    . '<meta property="og:url" content="' . e($og_url) . '">'
    . '<meta property="og:site_name" content="MonRevenu">'
    . ($produit ? '<meta property="product:price:amount" content="' . $prix_produit . '"><meta property="product:price:currency" content="' . DEVISE_ISO . '">' : '')
    . '<meta name="twitter:card" content="summary_large_image">'
    . '<meta name="twitter:title" content="' . e($og_titre) . '">'
    . '<meta name="twitter:description" content="' . e($og_description) . '">'
    . '<meta name="twitter:image" content="' . e($og_image) . '">';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/head.php';
?>
<body class="bg-bg">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>
<header class="border-b border-line bg-surface">
  <div class="conteneur flex h-14 items-center">
    <a href="/" class="flex items-center gap-2" aria-label="MonRevenu, accueil">
      <img src="/assets/img/logo-64.png" alt="" width="28" height="28" class="h-7 w-7">
      <span class="font-semibold text-primary-ink">MonRevenu</span>
    </a>
  </div>
</header>

<main id="contenu" class="conteneur py-4 lg:py-10">
  <?php if (!$produit): ?>
    <div class="carte mx-auto max-w-md">
      <div class="vide">
        <?= ico('package', 'ico-40') ?>
        <h1 class="vide-titre">Ce produit n'est plus disponible</h1>
        <p class="vide-texte">Le lien a peut-être expiré ou le produit a été retiré du catalogue. Demandez un nouveau lien à la personne qui vous l'a envoyé.</p>
        <a class="btn btn-sm btn-secondaire mt-2" href="/">Aller sur MonRevenu</a>
      </div>
    </div>

  <?php elseif ($message): ?>
    <div class="carte mx-auto max-w-md p-6 text-center">
      <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success-soft text-success"><?= ico('circle-check', 'ico-24') ?></span>
      <h1 class="mt-4 text-xl font-semibold">Commande enregistrée</h1>
      <p class="mt-2 text-text-2" role="status"><?= e($message) ?></p>
      <dl class="recap mt-5 text-left">
        <div class="recap-ligne"><dt>Produit</dt><dd><?= e($produit['nom']) ?></dd></div>
        <div class="recap-ligne"><dt>Prix unitaire</dt><dd class="montant"><?= formaterMontant($prix_produit) ?></dd></div>
      </dl>
    </div>

  <?php else: ?>
    <div class="grid gap-6 lg:grid-cols-2 lg:gap-10">
      <div class="carte overflow-hidden lg:sticky lg:top-6 lg:self-start">
        <div class="produit-image">
          <?php if ($image_produit !== ''): ?>
            <img src="<?= e($image_produit) ?>" alt="<?= e($produit['nom']) ?>" width="800" height="800" fetchpriority="high" decoding="async">
          <?php else: ?>
            <div class="flex items-center justify-center text-text-3"><?= ico('image', 'ico-40', 'Pas de photo') ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="flex min-w-0 flex-col gap-5">
        <div>
          <h1 class="text-2xl font-semibold lg:text-3xl"><?= e($produit['nom']) ?></h1>
          <p class="montant mt-2 text-3xl"><?= formaterMontant($prix_produit) ?></p>
          <ul class="mt-3 flex flex-col gap-1.5 text-sm text-text-2">
            <li class="flex items-center gap-2"><?= ico('truck', 'ico-16 text-text-3') ?>Paiement à la livraison</li>
            <li class="flex items-center gap-2"><?= ico('user', 'ico-16 text-text-3') ?>Commande sans création de compte</li>
          </ul>
        </div>

        <?php if (!empty($produit['description'])): ?>
          <div class="max-w-lecture text-text-2"><?= nl2br(e($produit['description'])) ?></div>
        <?php endif; ?>

        <section class="carte" aria-labelledby="titre-commande">
          <h2 id="titre-commande" class="carte-entete carte-titre">Commander</h2>
          <form action="" method="POST" id="form-commande" class="flex flex-col gap-4 p-4" novalidate>
            <?php if (!empty($error)): ?>
              <p class="alerte alerte-danger" role="alert"><?= ico('circle-alert') ?><span><?= e($error) ?></span></p>
            <?php endif; ?>
            <?php if (!$vendeur): ?>
              <p class="alerte alerte-attention"><?= ico('circle-alert') ?><span>Ce lien n'est pas reconnu. Vous pouvez consulter le produit, mais la commande n'est pas possible depuis ce lien.</span></p>
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <div class="champ">
              <label class="champ-label" for="nom_client">Nom complet</label>
              <input class="champ-saisie" type="text" id="nom_client" name="nom_client" required maxlength="120" autocomplete="name" autocapitalize="words"
                     value="<?= e($_POST['nom_client'] ?? '') ?>" aria-describedby="err-nom_client">
              <p class="champ-erreur" id="err-nom_client" hidden></p>
            </div>

            <div class="champ">
              <label class="champ-label" for="telephone_client">Numéro WhatsApp</label>
              <input class="champ-saisie" type="tel" id="telephone_client" name="telephone_client" required maxlength="30" autocomplete="tel" inputmode="tel"
                     pattern="^(\+221|00221)?7[0-8][0-9]{7}$|^(\+269|00269)?[34][0-9]{6}$" placeholder="77 123 45 67"
                     value="<?= e($_POST['telephone_client'] ?? '') ?>" aria-describedby="aide-telephone err-telephone_client">
              <p class="champ-aide" id="aide-telephone">Vous serez contacté sur ce numéro pour confirmer la livraison.</p>
              <p class="champ-erreur" id="err-telephone_client" hidden></p>
            </div>

            <div class="champ">
              <label class="champ-label" for="adresse_client">Adresse de livraison <span class="font-normal text-text-3">(facultatif)</span></label>
              <textarea class="champ-saisie" id="adresse_client" name="adresse_client" rows="2" autocomplete="street-address"><?= e($_POST['adresse_client'] ?? '') ?></textarea>
            </div>

            <div class="champ">
              <label class="champ-label" for="quantite">Quantité</label>
              <input class="champ-saisie chiffres w-28" type="number" id="quantite" name="quantite" min="1" max="99" step="1" inputmode="numeric" value="<?= $quantite_saisie ?>" data-prix="<?= $prix_produit ?>">
            </div>

            <div class="-mx-4 -mb-4 flex flex-col gap-3 border-t border-line bg-surface p-4 max-lg:sticky max-lg:bottom-0 max-lg:pb-[max(16px,env(safe-area-inset-bottom))]">
              <p class="flex items-center justify-between gap-3 text-sm text-text-2" aria-live="polite">
                <span><span id="recap-quantite" class="chiffres"><?= $quantite_saisie ?></span> x <?= e(formaterMontant($prix_produit)) ?></span>
                <span>Total <strong class="montant ml-1 text-lg text-text" id="recap-total"><?= formaterMontant($prix_produit * $quantite_saisie) ?></strong></span>
              </p>
              <button type="submit" name="action_commander" class="btn btn-primaire btn-bloc"<?= !$vendeur ? ' disabled' : '' ?>><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Passer la commande</span></button>
            </div>
          </form>
        </section>
      </div>
    </div>
  <?php endif; ?>
</main>

<footer class="conteneur flex flex-wrap gap-x-6 gap-y-2 py-8 text-sm text-text-3">
  <a class="hover:text-primary-ink" href="/conditions.php">Conditions générales</a>
  <a class="hover:text-primary-ink" href="/confidentialite.php">Confidentialité</a>
  <a class="hover:text-primary-ink" href="mailto:contact@monrevenu.xyz">contact@monrevenu.xyz</a>
</footer>

<div id="toasts" class="toasts" role="status" aria-live="polite"></div>
<script src="<?= e(actif('/assets/js/app-shell.js')) ?>"></script>
<script src="<?= e(actif('/assets/js/produit.js')) ?>"></script>
</body>
</html>
