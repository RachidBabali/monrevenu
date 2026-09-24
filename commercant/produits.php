<?php
require_once __DIR__ . '/../includs/session.php';
/**
 * commercant/produits.php : liste des produits du commercant et actions rapides.
 * Chaque action verifie que le produit appartient bien au commercant connecte, dans la transaction.
 */
if (session_status() === PHP_SESSION_NONE) demarrerSession();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/audit.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/incident.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/commercant.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/image_produit.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/reglages_publication.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';

$profil = exigerCommercant($pdo);
$id = (int) $profil['user_id'];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'commercant_produits');
        $_SESSION['flash_error'] = 'Votre session a expiré. Rechargez la page puis recommencez.';
        header('Location: /commercant/produits.php'); exit();
    }

    $produit_id = (int) ($_POST['produit_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $imageASupprimer = null;
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM vendeur_produits WHERE id = ? AND vendeur_id = ? FOR UPDATE");
        $st->execute([$produit_id, $id]);
        $produit = $st->fetch(PDO::FETCH_ASSOC);
        if (!$produit) {
            $pdo->rollBack();
            auditInfo($pdo, ['category' => 'produit', 'action' => 'produit_action_refusee', 'result' => 'refus',
                'entity_type' => 'produit', 'entity_id' => $produit_id, 'meta' => ['raison' => 'produit d\'un autre compte ou inexistant']]);
            $_SESSION['flash_error'] = "Ce produit est introuvable.";
            header('Location: /commercant/produits.php'); exit();
        }

        $avant = ['statut' => $produit['statut'], 'moderation' => $produit['moderation']];
        $apres = null;
        $succes = '';
        $motifModeration = null;
        $publicationType = $produit['publication_type'] ?? 'manuel';
        $publieAutoLe = $produit['publie_automatiquement_le'] ?? null;

        if ($action === 'soumettre' && in_array($produit['moderation'], ['brouillon', 'refuse'], true)) {
            if (!commercantPeutPublier($profil)) {
                throw new RuntimeException('boutique_non_validee');
            }
            $decision = evaluerPublicationProduit($pdo, $profil,
                ['nom' => $produit['nom_produit'], 'description' => (string) $produit['description'], 'prix' => (float) $produit['prix_vente'], 'image' => (string) $produit['image']],
                $profil['marche'], $produit_id);
            $apres = ['statut' => 'actif', 'moderation' => $decision['moderation']];
            $motifModeration = $decision['motif'];
            $publicationType = $decision['moderation'] === 'approuve' ? $decision['publication_type'] : 'manuel';
            $publieAutoLe = ($decision['moderation'] === 'approuve' && $decision['publication_type'] === 'automatique') ? date('Y-m-d H:i:s') : null;
            $succes = $decision['moderation'] === 'approuve'
                ? 'Produit publié dans le catalogue.'
                : 'Produit envoyé pour validation. Vous recevrez un message dès qu\'il sera traité.' . ($motifModeration ? ' Motif : ' . $motifModeration : '');
        } elseif ($action === 'brouillon' && $produit['moderation'] !== 'brouillon') {
            $apres = ['statut' => $produit['statut'], 'moderation' => 'brouillon'];
            $succes = 'Produit remis en brouillon : il n\'est plus visible dans le catalogue.';
        } elseif ($action === 'suspendre' && $produit['statut'] === 'actif') {
            $apres = ['statut' => 'suspendu', 'moderation' => $produit['moderation']];
            $succes = 'Produit suspendu : il n\'apparaît plus dans le catalogue.';
        } elseif ($action === 'reactiver' && $produit['statut'] !== 'actif') {
            $apres = ['statut' => 'actif', 'moderation' => $produit['moderation']];
            $succes = 'Produit remis en vente.';
        } elseif ($action === 'terminer' && $produit['statut'] !== 'termine') {
            $apres = ['statut' => 'termine', 'moderation' => $produit['moderation']];
            $succes = 'Produit marqué comme terminé.';
        } elseif ($action === 'supprimer') {
            $stc = $pdo->prepare("SELECT COUNT(*) FROM vendeur_ventes WHERE produit_id = ?");
            $stc->execute([$produit_id]);
            if ((int) $stc->fetchColumn() > 0) {
                throw new RuntimeException('commandes_existantes');
            }
            $pdo->prepare("DELETE FROM vendeur_produits WHERE id = ? AND vendeur_id = ?")->execute([$produit_id, $id]);
            auditCritique($pdo, ['category' => 'produit', 'action' => 'produit_suppression', 'entity_type' => 'produit', 'entity_id' => $produit_id,
                'before' => ['nom_produit' => $produit['nom_produit'], 'prix_vente' => $produit['prix_vente'], 'statut' => $produit['statut'], 'moderation' => $produit['moderation']]]);
            $imageASupprimer = $produit['image'];
            $succes = 'Produit supprimé.';
        } else {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Cette action n'est pas possible sur ce produit.";
            header('Location: /commercant/produits.php'); exit();
        }

        if ($apres !== null) {
            $pdo->prepare(
                "UPDATE vendeur_produits SET statut = ?, moderation = ?, moderation_note = ?, publication_type = ?, publie_automatiquement_le = ?
                 WHERE id = ? AND vendeur_id = ?"
            )->execute([$apres['statut'], $apres['moderation'], $motifModeration, $publicationType, $publieAutoLe, $produit_id, $id]);
            [$b, $a] = auditDiff($avant, $apres);
            auditCritique($pdo, ['category' => 'produit', 'action' => 'produit_' . $action, 'entity_type' => 'produit', 'entity_id' => $produit_id,
                'before' => $b, 'after' => $a, 'meta' => $motifModeration ? ['motif' => $motifModeration] : []]);
        }

        $pdo->commit();
        if ($imageASupprimer) {
            $r = supprimerImageProduit($imageASupprimer, $id);
            auditInfo($pdo, ['category' => $r['ok'] ? 'produit' : 'systeme', 'action' => $r['ok'] ? 'image_suppression' : 'image_suppression_echec',
                'result' => $r['ok'] ? 'ok' : 'echec', 'entity_type' => 'produit', 'entity_id' => $produit_id, 'meta' => ['cle' => $r['cle'] ?? null]]);
        }
        $_SESSION['flash_success'] = $succes;
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = match ($t->getMessage()) {
            'commandes_existantes' => "Ce produit a déjà des commandes : suspendez-le ou marquez-le terminé plutôt que de le supprimer.",
            'boutique_non_validee' => "Votre boutique doit être validée avant de publier un produit.",
            default => messageIncident(incidentEnregistrer($pdo, $t, 'commercant/produits'),
                "L'action n'a pas pu être enregistrée. Réessayez dans un instant."),
        };
    }
    header('Location: /commercant/produits.php'); exit();
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$filtre = $_GET['etat'] ?? 'tous';
$conditions = [
    'tous'       => '',
    'publies'    => " AND moderation = 'approuve' AND statut = 'actif'",
    'attente'    => " AND moderation = 'en_attente'",
    'brouillons' => " AND moderation = 'brouillon'",
    'refuses'    => " AND moderation = 'refuse'",
];
if (!isset($conditions[$filtre])) $filtre = 'tous';

$produits = [];
$total = 0;
try {
    $st = $pdo->prepare("SELECT * FROM vendeur_produits WHERE vendeur_id = ?" . $conditions[$filtre] . " ORDER BY id DESC LIMIT 100");
    $st->execute([$id]);
    $produits = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT COUNT(*) FROM vendeur_produits WHERE vendeur_id = ?");
    $st->execute([$id]);
    $total = (int) $st->fetchColumn();
} catch (PDOException $e) {
    error_log('[commercant/produits] ' . $e->getMessage());
    $message_error = "La liste n'a pas pu être chargée. Actualisez la page.";
}

$titre_page = 'Mes produits';
$actions_entete = commercantPeutPublier($profil)
    ? '<a class="btn btn-sm btn-primaire" href="/commercant/produit.php">' . ico('plus', 'ico-16') . 'Ajouter</a>' : '';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';

$etats = ['tous' => 'Tous', 'publies' => 'Publiés', 'attente' => 'En attente', 'brouillons' => 'Brouillons', 'refuses' => 'Refusés'];
?>
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="page-titre">Mes produits</h2>
        <p class="meta mt-1"><?= $total ?> produit<?= $total > 1 ? 's' : '' ?> sur <?= commercantMaxProduits() ?> autorisés</p>
      </div>
      <nav class="segments" aria-label="Filtrer les produits">
        <?php foreach ($etats as $cle => $libelle): ?>
          <a class="segment" href="?etat=<?= e($cle) ?>"<?= $filtre === $cle ? ' aria-current="true"' : '' ?>><?= e($libelle) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <?php if (!commercantPeutPublier($profil)): ?>
      <p class="alerte alerte-info"><?= ico('info') ?><span>Vous pouvez préparer vos produits en brouillon. Ils pourront être envoyés pour validation une fois votre boutique validée.</span></p>
    <?php endif; ?>

    <?php if (!$produits): ?>
      <div class="carte">
        <div class="vide">
          <?= ico('package', 'ico-40') ?>
          <p class="vide-titre"><?= $filtre === 'tous' ? 'Aucun produit pour l\'instant' : 'Aucun produit dans cette vue' ?></p>
          <p class="vide-texte">Ajoutez un produit avec sa photo, son prix et sa description. Les affiliés le partagent ensuite avec leur lien.</p>
          <a class="btn btn-sm btn-primaire mt-2" href="/commercant/produit.php">Ajouter un produit</a>
        </div>
      </div>
    <?php else: ?>
      <ul class="flex flex-col gap-3">
        <?php foreach ($produits as $p): ?>
          <?php
            $img = (string) $p['image'];
            $src = $img === '' ? '' : (preg_match('#^(https?:)?//#', $img) || str_starts_with($img, 'data:') ? $img : '/admin/' . ltrim($img, '/'));
            $commission = calculerCommission((float) $p['prix_vente']);
          ?>
          <li class="carte flex flex-col gap-3 p-3 sm:flex-row sm:items-start">
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded bg-surface-2">
              <?php if ($src !== ''): ?>
                <img src="<?= e($src) ?>" alt="" width="80" height="80" class="h-full w-full object-contain" loading="lazy" decoding="async">
              <?php else: ?>
                <span class="flex h-full w-full items-center justify-center text-text-3"><?= ico('image') ?></span>
              <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <?= badgeStatut($p['moderation'], 'moderation') ?>
                <?php if ($p['statut'] !== 'actif'): ?><?= badgeStatut($p['statut'], 'produit') ?><?php endif; ?>
              </div>
              <p class="mt-1 font-medium text-text"><?= e($p['nom_produit']) ?></p>
              <p class="meta mt-0.5"><?= formaterMontant($p['prix_vente']) ?>, commission affilié <?= formaterMontant($commission) ?><?= (int) $p['stock'] > 0 ? ', stock ' . (int) $p['stock'] : '' ?></p>
              <?php if ($p['moderation'] === 'refuse' && $p['moderation_note']): ?>
                <p class="mt-1 text-sm text-danger">Motif du refus : <?= e($p['moderation_note']) ?></p>
              <?php elseif ($p['moderation'] === 'en_attente' && $p['moderation_note']): ?>
                <p class="mt-1 text-sm text-text-2">En attente de validation. Motif : <?= e($p['moderation_note']) ?></p>
              <?php endif; ?>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
              <a class="btn btn-sm btn-secondaire" href="/commercant/produit.php?id=<?= (int) $p['id'] ?>"><?= ico('pencil', 'ico-16') ?>Modifier</a>
              <?php
                $boutons = [];
                if (in_array($p['moderation'], ['brouillon', 'refuse'], true) && commercantPeutPublier($profil)) {
                    $boutons[] = ['soumettre', (int) $profil['confiance'] === 1 ? 'Publier' : 'Envoyer pour validation', 'btn-primaire', 'upload'];
                }
                if ($p['moderation'] === 'approuve' && $p['statut'] === 'actif') $boutons[] = ['suspendre', 'Suspendre', 'btn-discret', 'eye-off'];
                if ($p['statut'] === 'suspendu') $boutons[] = ['reactiver', 'Remettre en vente', 'btn-secondaire', 'eye'];
                if ($p['moderation'] !== 'brouillon' && $p['statut'] !== 'termine') $boutons[] = ['brouillon', 'Remettre en brouillon', 'btn-discret', 'file-text'];
                if ($p['statut'] !== 'termine') $boutons[] = ['terminer', 'Terminer', 'btn-discret', 'check'];
              ?>
              <?php foreach ($boutons as [$act, $libelle, $classe, $icone]): ?>
                <form method="POST" action="/commercant/produits.php" class="contents">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="produit_id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="action" value="<?= e($act) ?>">
                  <button type="submit" class="btn btn-sm <?= e($classe) ?>"><?= ico($icone, 'ico-16') ?><?= e($libelle) ?></button>
                </form>
              <?php endforeach; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
