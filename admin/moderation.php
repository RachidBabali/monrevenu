<?php
/**
 * admin/moderation.php : file des produits de commercants a valider, et gestion des produits deja publies
 * (suspension, remise en vente). L'apercu reprend la carte produit du catalogue.
 */
require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/../includs/audit.php';
require_once __DIR__ . '/../includs/incident.php';
require_once __DIR__ . '/../includs/commercant.php';
require_once __DIR__ . '/../includs/affiliation_helpers.php';
require_once __DIR__ . '/../includs/ui.php';

$admin = requireRole($pdo, 'admin');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'admin_moderation');
        http_response_code(403);
        die('Action non autorisée (CSRF).');
    }
    $produit_id = (int) ($_POST['produit_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $motif = mb_substr(trim((string) ($_POST['motif'] ?? '')), 0, 255);
    $notification = null;

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM vendeur_produits WHERE id = ? FOR UPDATE");
        $st->execute([$produit_id]);
        $produit = $st->fetch(PDO::FETCH_ASSOC);
        if (!$produit) throw new RuntimeException('introuvable');

        if ($action === 'approuver') {
            $pdo->prepare("UPDATE vendeur_produits SET moderation = 'approuve', moderation_note = NULL, statut = 'actif' WHERE id = ?")->execute([$produit_id]);
            auditCritique($pdo, ['category' => 'produit', 'action' => 'produit_approbation', 'entity_type' => 'produit', 'entity_id' => $produit_id,
                'before' => ['moderation' => $produit['moderation'], 'statut' => $produit['statut']], 'after' => ['moderation' => 'approuve', 'statut' => 'actif']]);
            $notification = [(int) $produit['vendeur_id'], "Votre produit « " . $produit['nom_produit'] . " » est publié dans le catalogue.", 'Produit publié'];
            $message = 'Produit approuvé et publié.';
        } elseif ($action === 'refuser') {
            if ($motif === '') throw new RuntimeException('motif_manquant');
            $pdo->prepare("UPDATE vendeur_produits SET moderation = 'refuse', moderation_note = ?, statut = 'suspendu' WHERE id = ?")->execute([$motif, $produit_id]);
            auditCritique($pdo, ['category' => 'produit', 'action' => 'produit_refus', 'entity_type' => 'produit', 'entity_id' => $produit_id,
                'before' => ['moderation' => $produit['moderation']], 'after' => ['moderation' => 'refuse'], 'meta' => ['motif' => $motif]]);
            $notification = [(int) $produit['vendeur_id'], "Votre produit « " . $produit['nom_produit'] . " » n'a pas été publié. Motif : " . $motif, 'Produit refusé'];
            $message = 'Produit refusé, le commerçant est prévenu.';
        } elseif ($action === 'suspendre') {
            if ($motif === '') throw new RuntimeException('motif_manquant');
            $pdo->prepare("UPDATE vendeur_produits SET statut = 'suspendu', moderation_note = ? WHERE id = ?")->execute([$motif, $produit_id]);
            auditCritique($pdo, ['category' => 'produit', 'action' => 'produit_suspension_admin', 'entity_type' => 'produit', 'entity_id' => $produit_id,
                'before' => ['statut' => $produit['statut']], 'after' => ['statut' => 'suspendu'], 'meta' => ['motif' => $motif]]);
            $notification = [(int) $produit['vendeur_id'], "Votre produit « " . $produit['nom_produit'] . " » a été retiré du catalogue. Motif : " . $motif, 'Produit retiré'];
            $message = 'Produit suspendu.';
        } elseif ($action === 'reactiver') {
            $pdo->prepare("UPDATE vendeur_produits SET statut = 'actif', moderation_note = NULL WHERE id = ?")->execute([$produit_id]);
            auditCritique($pdo, ['category' => 'produit', 'action' => 'produit_reactivation_admin', 'entity_type' => 'produit', 'entity_id' => $produit_id,
                'before' => ['statut' => $produit['statut']], 'after' => ['statut' => 'actif']]);
            $message = 'Produit remis en vente.';
        } else {
            throw new RuntimeException('action_inconnue');
        }

        $pdo->commit();
        if ($notification) {
            try {
                require_once __DIR__ . '/../includs/notifications.php';
                envoyerNotification($pdo, $notification[0], $notification[1], $notification[2], '/commercant/produits.php');
            } catch (Throwable $t) {
                error_log('[admin/moderation] notification : ' . get_class($t));
            }
        }
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = match ($t->getMessage()) {
            'introuvable' => "Ce produit est introuvable.",
            'motif_manquant' => "Indiquez le motif : il est envoyé au commerçant.",
            default => messageIncident(
                incidentEnregistrer($pdo, $t, 'admin/moderation'),
                "L'action n'a pas pu être enregistrée. Réessayez dans un instant."
            ),
        };
        $message = '';
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_error'] = $error;
    header('Location: /admin/moderation.php');
    exit();
}

$message = $_SESSION['flash_message'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$vue = in_array($_GET['vue'] ?? '', ['attente', 'publies', 'refuses'], true) ? $_GET['vue'] : 'attente';
$conditions = [
    'attente' => "p.moderation = 'en_attente'",
    'publies' => "p.moderation = 'approuve'",
    'refuses' => "p.moderation = 'refuse'",
];
$produits = [];
try {
    $st = $pdo->prepare(
        "SELECT p.*, cp.nom_boutique, cp.statut AS statut_boutique, u.fullname
         FROM vendeur_produits p
         JOIN commercants_profils cp ON cp.user_id = p.vendeur_id
         JOIN users_monrevenu u ON u.id = p.vendeur_id
         WHERE " . $conditions[$vue] . " ORDER BY p.updated_at DESC, p.id DESC LIMIT 100"
    );
    $st->execute();
    $produits = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $error ?: messageIncident(incidentEnregistrer($pdo, $e, 'admin/moderation/liste'), "La liste n'a pas pu être chargée.");
}

$compteurs_admin = [
    'commercants' => (int) $pdo->query("SELECT COUNT(*) FROM commercants_profils WHERE statut = 'en_attente'")->fetchColumn(),
    'produits' => (int) $pdo->query("SELECT COUNT(*) FROM vendeur_produits WHERE moderation = 'en_attente'")->fetchColumn(),
];
$titre_page = 'Produits à valider';
$page_admin = 'moderation';
include __DIR__ . '/sections/coquille_debut.php';

$vues = ['attente' => 'À valider', 'publies' => 'Publiés', 'refuses' => 'Refusés'];
?>
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="page-titre">Produits des commerçants</h2>
        <p class="meta mt-1"><?= $compteurs_admin['produits'] ?> produit<?= $compteurs_admin['produits'] > 1 ? 's' : '' ?> en attente de validation</p>
      </div>
      <nav class="segments" aria-label="Filtrer les produits">
        <?php foreach ($vues as $cle => $libelle): ?>
          <a class="segment" href="?vue=<?= e($cle) ?>"<?= $vue === $cle ? ' aria-current="true"' : '' ?>><?= e($libelle) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <?php if (!$produits): ?>
      <div class="carte">
        <div class="vide">
          <?= ico('badge-check', 'ico-40') ?>
          <p class="vide-titre">Rien à traiter dans cette vue</p>
          <p class="vide-texte">Les produits envoyés par les commerçants apparaissent ici avant publication.</p>
        </div>
      </div>
    <?php else: ?>
      <ul class="grid gap-4 lg:grid-cols-2">
        <?php foreach ($produits as $p): ?>
          <?php
            $img = (string) $p['image'];
            $src = $img === '' ? '' : (preg_match('#^(https?:)?//#', $img) || str_starts_with($img, 'data:') ? $img : '/admin/' . ltrim($img, '/'));
          ?>
          <li class="carte flex flex-col gap-3 p-4">
            <div class="flex gap-3">
              <div class="h-24 w-24 shrink-0 overflow-hidden rounded bg-surface-2">
                <?php if ($src !== ''): ?>
                  <img src="<?= e($src) ?>" alt="Photo de <?= e($p['nom_produit']) ?>" width="96" height="96" class="h-full w-full object-contain" loading="lazy" decoding="async">
                <?php else: ?>
                  <span class="flex h-full w-full items-center justify-center text-text-3"><?= ico('image') ?></span>
                <?php endif; ?>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-text"><?= e($p['nom_produit']) ?></p>
                <p class="montant mt-0.5"><?= formaterMontant($p['prix_vente']) ?></p>
                <p class="meta">Commission affilié <?= formaterMontant(calculerCommission((float) $p['prix_vente'])) ?><?= (int) $p['stock'] > 0 ? ', stock ' . (int) $p['stock'] : '' ?></p>
                <p class="meta mt-1"><?= e($p['nom_boutique']) ?> (<?= e($p['fullname']) ?>) <?= badgeStatut($p['statut_boutique'], 'boutique') ?></p>
              </div>
            </div>
            <?php if ($p['description']): ?>
              <p class="text-sm text-text-2"><?= nl2br(e(mb_substr($p['description'], 0, 600))) ?></p>
            <?php endif; ?>
            <?php if ($p['moderation_note']): ?>
              <p class="text-sm text-danger">Motif enregistré : <?= e($p['moderation_note']) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2 border-t border-line pt-3">
              <?php if ($p['moderation'] !== 'approuve'): ?>
                <form method="POST" action="/admin/moderation.php">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="produit_id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="action" value="approuver">
                  <button type="submit" class="btn btn-sm btn-primaire"<?= $p['statut_boutique'] !== 'valide' ? ' disabled title="La boutique doit être validée"' : '' ?>><?= ico('check', 'ico-16') ?>Approuver</button>
                </form>
              <?php endif; ?>
              <?php if ($p['moderation'] === 'en_attente'): ?>
                <button type="button" class="btn btn-sm btn-discret text-danger" data-ouvrir="refus-<?= (int) $p['id'] ?>" aria-haspopup="dialog"><?= ico('circle-x', 'ico-16') ?>Refuser</button>
              <?php endif; ?>
              <?php if ($p['moderation'] === 'approuve' && $p['statut'] === 'actif'): ?>
                <button type="button" class="btn btn-sm btn-discret text-danger" data-ouvrir="refus-<?= (int) $p['id'] ?>" aria-haspopup="dialog"><?= ico('eye-off', 'ico-16') ?>Retirer du catalogue</button>
              <?php elseif ($p['statut'] !== 'actif' && $p['moderation'] === 'approuve'): ?>
                <form method="POST" action="/admin/moderation.php">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="produit_id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="action" value="reactiver">
                  <button type="submit" class="btn btn-sm btn-secondaire"><?= ico('eye', 'ico-16') ?>Remettre en vente</button>
                </form>
              <?php endif; ?>
            </div>

            <dialog class="feuille" id="refus-<?= (int) $p['id'] ?>" aria-labelledby="t-refus-<?= (int) $p['id'] ?>">
              <div class="poignee"></div>
              <div class="feuille-entete">
                <h2 class="feuille-titre" id="t-refus-<?= (int) $p['id'] ?>"><?= $p['moderation'] === 'approuve' ? 'Retirer' : 'Refuser' ?> <?= e($p['nom_produit']) ?></h2>
                <button type="button" class="btn btn-icone btn-discret" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
              </div>
              <form method="POST" action="/admin/moderation.php">
                <div class="feuille-corps flex flex-col gap-4">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="produit_id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="action" value="<?= $p['moderation'] === 'approuve' ? 'suspendre' : 'refuser' ?>">
                  <div class="champ">
                    <label class="champ-label" for="motif-p-<?= (int) $p['id'] ?>">Motif</label>
                    <input class="champ-saisie" type="text" id="motif-p-<?= (int) $p['id'] ?>" name="motif" maxlength="255" required
                           placeholder="Photo illisible, produit interdit, prix incohérent...">
                    <p class="champ-aide">Le commerçant reçoit ce motif et peut corriger son produit.</p>
                  </div>
                </div>
                <div class="feuille-pied">
                  <button type="button" class="btn btn-secondaire" data-fermer>Revenir</button>
                  <button type="submit" class="btn btn-danger">Confirmer</button>
                </div>
              </form>
            </dialog>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
<?php include __DIR__ . '/sections/coquille_fin.php'; ?>
