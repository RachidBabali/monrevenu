<?php
/**
 * admin/reglages.php : reglage "Publication" (bloc H). Trois modes, plage de prix par
 * marche, seuil de signalements et liste de mots interdits. Toute modification est
 * journalisee (includs/reglages_publication.php > sauvegarderReglagesPublication).
 */
require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/../includs/audit.php';
require_once __DIR__ . '/../includs/incident.php';
require_once __DIR__ . '/../includs/commercant.php';
require_once __DIR__ . '/../includs/reglages_publication.php';
require_once __DIR__ . '/../includs/ui.php';

$admin = requireRole($pdo, 'admin');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'admin_reglages');
        http_response_code(403);
        die('Action non autorisée (CSRF).');
    }
    try {
        sauvegarderReglagesPublication($pdo, $_POST, (int) $admin['id']);
        $message = 'Réglages de publication enregistrés.';
    } catch (Throwable $t) {
        $error = messageIncident(incidentEnregistrer($pdo, $t, 'admin/reglages'), "L'enregistrement a échoué. Réessayez dans un instant.");
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_error'] = $error;
    header('Location: /admin/reglages.php');
    exit();
}

$message = $_SESSION['flash_message'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$reglages = reglagesPublication($pdo);

$compteurs_admin = [
    'commercants' => (int) $pdo->query("SELECT COUNT(*) FROM commercants_profils WHERE statut = 'en_attente'")->fetchColumn(),
    'produits' => (int) $pdo->query("SELECT COUNT(*) FROM vendeur_produits WHERE moderation = 'en_attente'")->fetchColumn(),
];
$titre_page = 'Réglages';
$page_admin = 'reglages';
include __DIR__ . '/sections/coquille_debut.php';

$modes = [
    'manuelle' => ['Manuelle', 'Comportement actuel : chaque boutique et chaque produit sont validés à la main par un administrateur. Le réglage « publication directe » par commerçant reste actif.'],
    'automatique_controles' => ['Automatique avec contrôles', 'Une boutique devient valide dès la vérification de son compte. Un produit est publié automatiquement s\'il passe les contrôles ci-dessous, sinon il entre dans la file « Produits à valider » avec le motif.'],
    'automatique_confiance' => ['Automatique de confiance', 'Comme ci-dessus, mais les produits sont publiés immédiatement sans passer par les contrôles de contenu (prix, mots interdits, coordonnées, doublons).'],
];
?>
    <div>
      <h2 class="page-titre">Publication des commerçants</h2>
      <p class="meta mt-1">S'applique aux nouvelles boutiques et aux nouveaux produits ; les décisions déjà prises ne changent pas rétroactivement.</p>
    </div>

    <form method="POST" action="/admin/reglages.php" class="carte flex flex-col gap-6 p-4">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <fieldset class="flex flex-col gap-2">
        <legend class="champ-label mb-1">Mode de publication</legend>
        <?php foreach ($modes as $cle => [$libelle, $description]): ?>
          <label class="choix-carte" for="mode-<?= e($cle) ?>">
            <input class="case" type="radio" id="mode-<?= e($cle) ?>" name="mode" value="<?= e($cle) ?>"<?= $reglages['mode'] === $cle ? ' checked' : '' ?>>
            <span><span class="block font-medium text-text"><?= e($libelle) ?></span><span class="block text-sm text-text-2"><?= e($description) ?></span></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <div class="champ">
        <label class="champ-label" for="premiers">Premiers produits à valider à la main</label>
        <input class="champ-saisie chiffres" type="number" id="premiers" name="premiers_produits_a_valider" min="0" max="20" value="<?= (int) $reglages['premiers_produits_a_valider'] ?>">
        <p class="champ-aide">Même en mode automatique, les N premiers produits d'une nouvelle boutique passent par une validation manuelle (0 = aucun ; recommandé 1 à 3 au lancement).</p>
      </div>

      <fieldset class="flex flex-col gap-3">
        <legend class="champ-label mb-1">Plage de prix acceptée en publication automatique</legend>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="champ">
            <label class="champ-label" for="prix-min-xof">Minimum (FCFA)</label>
            <input class="champ-saisie chiffres" type="number" step="1" id="prix-min-xof" name="prix_min_xof" min="0" value="<?= (int) $reglages['prix_min_xof'] ?>">
          </div>
          <div class="champ">
            <label class="champ-label" for="prix-max-xof">Maximum (FCFA)</label>
            <input class="champ-saisie chiffres" type="number" step="1" id="prix-max-xof" name="prix_max_xof" min="0" value="<?= (int) $reglages['prix_max_xof'] ?>">
          </div>
          <div class="champ">
            <label class="champ-label" for="prix-min-kmf">Minimum (KMF)</label>
            <input class="champ-saisie chiffres" type="number" step="1" id="prix-min-kmf" name="prix_min_kmf" min="0" value="<?= (int) $reglages['prix_min_kmf'] ?>">
          </div>
          <div class="champ">
            <label class="champ-label" for="prix-max-kmf">Maximum (KMF)</label>
            <input class="champ-saisie chiffres" type="number" step="1" id="prix-max-kmf" name="prix_max_kmf" min="0" value="<?= (int) $reglages['prix_max_kmf'] ?>">
          </div>
        </div>
        <p class="champ-aide">Un produit hors de cette plage n'est jamais publié automatiquement (il entre dans la file à valider) ; cela ne bloque pas sa création.</p>
      </fieldset>

      <div class="champ">
        <label class="champ-label" for="seuil">Seuil de signalements avant suspension automatique</label>
        <input class="champ-saisie chiffres" type="number" id="seuil" name="seuil_signalements" min="1" max="50" value="<?= (int) $reglages['seuil_signalements'] ?>">
        <p class="champ-aide">Un produit publié atteignant ce nombre de signalements par des affiliés différents est retiré du catalogue en attendant une décision.</p>
      </div>

      <div class="champ">
        <label class="champ-label" for="mots">Mots et expressions interdits</label>
        <textarea class="champ-saisie" id="mots" name="mots_interdits" rows="8"><?= e($reglages['mots_interdits']) ?></textarea>
        <p class="champ-aide">Un mot ou une expression par ligne, en minuscules. Si le nom ou la description d'un produit contient l'un de ces mots, il entre dans la file à valider au lieu d'être publié automatiquement.</p>
      </div>

      <button type="submit" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Enregistrer</span></button>
    </form>
<?php include __DIR__ . '/sections/coquille_fin.php'; ?>
