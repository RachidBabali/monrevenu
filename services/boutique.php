<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/commercant.php';
exigerAffiliationDebloquee($pdo);

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /index.php'); exit();
}

$user_fullname = $_SESSION['user_fullname'] ?? 'Utilisateur';
$user_initials = strtoupper(substr($user_fullname, 0, 2)) ?: 'U';

// Marche de l'affilie : le catalogue ne montre que les produits de son marche
// (les prix et commissions restent dans une seule devise, includs/config_marche.php).
$stmtMarche = $pdo->prepare("SELECT pays_code, phone FROM users_monrevenu WHERE id = ?");
$stmtMarche->execute([$user_id]);
$marche_affilie = definirMarcheCourant(marcheDeCompte($stmtMarche->fetch(PDO::FETCH_ASSOC) ?: null));

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'signaler') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/audit.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/signalement.php';
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'boutique_signalement');
        $_SESSION['flash_error'] = 'Votre session a expiré. Rechargez la page puis recommencez.';
    } else {
        $produit_a_signaler = (int) ($_POST['produit_id'] ?? 0);
        try {
            $resultat = signalerProduit($pdo, $produit_a_signaler, (int) $user_id, (string) ($_POST['motif'] ?? ''));
            $_SESSION['flash_success'] = $resultat['deja_signale']
                ? 'Vous avez déjà signalé ce produit.'
                : 'Signalement enregistré. Merci, l\'équipe MonRevenu va vérifier ce produit.';
        } catch (Throwable $t) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/incident.php';
            $_SESSION['flash_error'] = messageIncident(incidentEnregistrer($pdo, $t, 'boutique/signalement'), "Le signalement n'a pas pu être enregistré.");
        }
    }
    header('Location: /services/boutique.php#produit-' . (int) ($_POST['produit_id'] ?? 0));
    exit();
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$recherche = trim($_GET['q'] ?? '');

$produits = [];
try {
    if ($recherche !== '') {
        $stmt = $pdo->prepare(
            "SELECT vp.id, vp.nom_produit AS nom, vp.description, vp.image, vp.prix_vente AS prix
             FROM vendeur_produits vp " . CATALOGUE_JOINTURE . "
             WHERE " . CATALOGUE_CONDITION . " AND " . catalogueFiltreMarche($marche_affilie) . " AND vp.nom_produit LIKE ?
             ORDER BY vp.nom_produit ASC"
        );
        $stmt->execute(['%' . $recherche . '%']);
    } else {
        $stmt = $pdo->prepare(
            "SELECT vp.id, vp.nom_produit AS nom, vp.description, vp.image, vp.prix_vente AS prix
             FROM vendeur_produits vp " . CATALOGUE_JOINTURE . "
             WHERE " . CATALOGUE_CONDITION . " AND " . catalogueFiltreMarche($marche_affilie) . "
             ORDER BY vp.nom_produit ASC"
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';

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

/* Tri d'affichage (sur la liste deja chargee, requetes inchangees). */
$tris = ['commission' => 'Commission la plus élevée', 'prix' => 'Prix croissant', 'nouveautes' => 'Nouveautés', 'nom' => 'Nom'];
$tri = $_GET['tri'] ?? 'commission';
if (!isset($tris[$tri])) {
    $tri = 'commission';
}
usort($produits, static function ($a, $b) use ($tri) {
    $pa = (float) ($a['prix'] ?? 0);
    $pb = (float) ($b['prix'] ?? 0);
    return match ($tri) {
        'commission' => [calculerCommission($pb), $pb] <=> [calculerCommission($pa), $pa],
        'prix'       => $pa <=> $pb,
        'nouveautes' => (int) $b['id'] <=> (int) $a['id'],
        default      => strcasecmp((string) $a['nom'], (string) $b['nom']),
    };
});

$titre_page = 'Catalogue';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 class="page-titre">Produits à promouvoir</h2>
        <p class="mt-1 text-sm text-text-2">Copiez le lien d'un produit et partagez-le. Vous touchez la commission indiquée sur chaque vente validée.</p>
      </div>
    </div>

    <form action="" method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center" role="search">
      <div class="relative flex-1">
        <label for="recherche-catalogue" class="sr-only">Rechercher un produit</label>
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-3"><?= ico('search') ?></span>
        <input class="champ-saisie pl-10" type="search" id="recherche-catalogue" name="q" value="<?= e($recherche) ?>" placeholder="Rechercher un produit" autocomplete="off" enterkeyhint="search">
      </div>
      <div class="flex gap-2">
        <label for="tri-catalogue" class="sr-only">Trier par</label>
        <select class="champ-saisie sm:w-60" id="tri-catalogue" name="tri" data-envoi-auto>
          <?php foreach ($tris as $cle => $libelle): ?>
            <option value="<?= e($cle) ?>"<?= $tri === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-secondaire shrink-0" type="submit">Afficher</button>
      </div>
    </form>

    <?php if (empty($produits)): ?>
      <div class="carte">
        <div class="vide">
          <?= ico($recherche !== '' ? 'search' : 'store', 'ico-40') ?>
          <?php if ($recherche !== ''): ?>
            <p class="vide-titre">Aucun produit pour "<?= e($recherche) ?>"</p>
            <p class="vide-texte">Vérifiez l'orthographe ou essayez un mot plus court.</p>
            <a class="btn btn-sm btn-secondaire mt-2" href="/services/boutique.php">Effacer la recherche</a>
          <?php else: ?>
            <p class="vide-titre">Le catalogue est vide pour le moment</p>
            <p class="vide-texte">Les produits ajoutés par l'équipe MonRevenu apparaîtront ici.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <p class="text-sm text-text-2">
        <span class="chiffres"><?= $total_produits ?></span> produit<?= $total_produits > 1 ? 's' : '' ?><?= $recherche !== '' ? ' pour "' . e($recherche) . '"' : '' ?>.
        Commission moyenne : <?= montant(round($commission_moyenne)) ?>
        <?php if ($recherche !== ''): ?><a class="lien ml-1" href="/services/boutique.php">Effacer la recherche</a><?php endif; ?>
      </p>

      <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
        <?php foreach ($produits as $i => $produit): ?>
          <?php
            $produit_id          = (int) $produit['id'];
            $produit_nom         = $produit['nom'] ?? 'Produit sans nom';
            $produit_image_brute = (string) ($produit['image'] ?? '');
            $produit_image       = $produit_image_brute === '' ? '' : (preg_match('#^(https?:)?//#', $produit_image_brute) || str_starts_with($produit_image_brute, 'data:') ? $produit_image_brute : '/admin/' . $produit_image_brute);
            $produit_prix_brut   = (float) ($produit['prix'] ?? 0);
            $commission_montant_brut = calculerCommission($produit_prix_brut);

            $slug = slugify($produit_nom);
            $token = genererTokenAffiliation($produit_id, (int) $user_id);
            $lien_affiliation = BASE_URL_SITE . '/produit/' . $slug . '/' . $token;
          ?>
          <li class="produit scroll-mt-20" id="produit-<?= $produit_id ?>">
            <div class="produit-image">
              <?php if ($produit_image !== ''): ?>
                <img src="<?= e($produit_image) ?>" alt="" width="400" height="400"<?= $i < 4 ? '' : ' loading="lazy"' ?> decoding="async">
              <?php else: ?>
                <div class="flex h-full items-center justify-center text-text-3"><?= ico('image', 'ico-40', 'Pas de photo') ?></div>
              <?php endif; ?>
            </div>
            <div class="produit-corps">
              <h3 class="produit-nom"><?= e($produit_nom) ?></h3>
              <p class="produit-prix"><?= montant($produit_prix_brut) ?></p>
              <p class="produit-commission"><span>Commission</span><?= montant($commission_montant_brut) ?></p>
            </div>
            <div class="produit-actions">
              <button type="button" class="btn btn-sm btn-primaire min-w-0 sm:flex-1" data-produit-id="<?= $produit_id ?>" data-copier="<?= e($lien_affiliation) ?>"><?= ico('copy', 'ico-16') ?><span data-libelle>Copier le lien</span></button>
              <button type="button" class="btn btn-sm btn-secondaire sm:w-9 sm:px-0" data-partager="<?= e($lien_affiliation) ?>" data-texte="<?= e($produit_nom . ' : ' . formaterMontant($produit_prix_brut)) ?>" title="Partager"><?= ico('share-2', 'ico-16') ?><span class="sm:sr-only">Partager<span class="sr-only"> <?= e($produit_nom) ?></span></span></button>
            </div>
            <details class="border-t border-line px-3 text-xs">
              <summary class="flex h-9 cursor-pointer list-none items-center text-text-2 hover:text-text">Voir le lien</summary>
              <label class="sr-only" for="lien-input-<?= $produit_id ?>">Lien d'affiliation pour <?= e($produit_nom) ?></label>
              <input type="text" readonly id="lien-input-<?= $produit_id ?>" value="<?= e($lien_affiliation) ?>" class="mb-3 w-full rounded border border-line bg-surface-2 px-2 py-2 font-mono text-xs text-text-2">
              <form method="POST" action="/services/boutique.php" class="mb-3">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="produit_id" value="<?= $produit_id ?>">
                <input type="hidden" name="action" value="signaler">
                <button type="submit" class="lien text-text-3" onclick="return confirm('Signaler ce produit à l\'équipe MonRevenu ?');"><?= ico('circle-alert', 'ico-16') ?>Signaler ce produit</button>
              </form>
            </details>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

<?php
$scripts_page = ['/assets/js/catalogue.js'];
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php';
