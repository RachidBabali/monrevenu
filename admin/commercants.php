<?php
/**
 * admin/commercants.php : liste des commercants, validation du compte, suspension, refus,
 * publication directe sans validation (confiance) et enregistrement des reglements.
 */
require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/../includs/audit.php';
require_once __DIR__ . '/../includs/incident.php';
require_once __DIR__ . '/../includs/commercant.php';
require_once __DIR__ . '/../includs/ui.php';

$admin = requireRole($pdo, 'admin');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'admin_commercants');
        http_response_code(403);
        die('Action non autorisée (CSRF).');
    }
    $cible = (int) ($_POST['commercant_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $motif = mb_substr(trim((string) ($_POST['motif'] ?? '')), 0, 255);
    $notification = null;

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            "SELECT cp.*, u.fullname FROM commercants_profils cp JOIN users_monrevenu u ON u.id = cp.user_id
             WHERE cp.user_id = ? FOR UPDATE"
        );
        $st->execute([$cible]);
        $profil = $st->fetch(PDO::FETCH_ASSOC);
        if (!$profil) throw new RuntimeException('introuvable');

        if (in_array($action, ['valider', 'refuser', 'suspendre', 'reactiver'], true)) {
            if (in_array($action, ['refuser', 'suspendre'], true) && $motif === '') throw new RuntimeException('motif_manquant');
            $statut = ['valider' => 'valide', 'refuser' => 'refuse', 'suspendre' => 'suspendu', 'reactiver' => 'valide'][$action];
            $pdo->prepare("UPDATE commercants_profils SET statut = ?, motif = ?, valide_par = ?, valide_le = NOW() WHERE user_id = ?")
                ->execute([$statut, in_array($action, ['refuser', 'suspendre'], true) ? $motif : null, (int) $admin['id'], $cible]);
            auditCritique($pdo, ['category' => 'admin', 'action' => 'commercant_' . $action, 'entity_type' => 'commercant', 'entity_id' => $cible,
                'before' => ['statut' => $profil['statut']], 'after' => ['statut' => $statut],
                'meta' => $motif !== '' ? ['motif' => $motif] : []]);
            $textes = [
                'valide'    => "Votre boutique est validée. Vous pouvez publier vos produits depuis votre espace.",
                'refuse'    => "Votre demande de boutique n'a pas été retenue. Motif : " . $motif,
                'suspendu'  => "Votre boutique est suspendue et vos produits ne sont plus visibles. Motif : " . $motif,
            ];
            $notification = [$cible, $textes[$statut], $statut === 'valide' ? 'Boutique validée' : ($statut === 'refuse' ? 'Boutique refusée' : 'Boutique suspendue')];
            $message = 'Compte commerçant mis à jour.';
        } elseif ($action === 'confiance') {
            $nouvelle = (int) $profil['confiance'] === 1 ? 0 : 1;
            $pdo->prepare("UPDATE commercants_profils SET confiance = ? WHERE user_id = ?")->execute([$nouvelle, $cible]);
            auditCritique($pdo, ['category' => 'admin', 'action' => 'commercant_confiance', 'entity_type' => 'commercant', 'entity_id' => $cible,
                'before' => ['confiance' => (int) $profil['confiance']], 'after' => ['confiance' => $nouvelle]]);
            $message = $nouvelle ? 'Ce commerçant publie désormais sans validation préalable.' : 'Les produits de ce commerçant repassent par la validation.';
        } elseif ($action === 'reglement') {
            $montant = round((float) str_replace([' ', ','], ['', '.'], (string) ($_POST['montant'] ?? '')), 2);
            $reference = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100);
            if ($montant <= 0) throw new RuntimeException('montant_invalide');
            if ($reference === '') throw new RuntimeException('reference_manquante');
            try {
                $pdo->prepare("INSERT INTO commercant_reglements (commercant_id, montant, reference, note, created_by) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$cible, number_format($montant, 2, '.', ''), $reference, $motif !== '' ? $motif : null, (int) $admin['id']]);
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('reference_utilisee');
                throw $e;
            }
            auditCritique($pdo, ['category' => 'argent', 'action' => 'commercant_reglement', 'entity_type' => 'commercant', 'entity_id' => $cible,
                'after' => ['montant' => number_format($montant, 2, '.', ''), 'reference' => $reference],
                'meta' => ['note' => $motif !== '' ? $motif : null]]);
            $message = 'Règlement enregistré.';
        } else {
            throw new RuntimeException('action_inconnue');
        }

        $pdo->commit();
        if ($notification) {
            try {
                require_once __DIR__ . '/../includs/notifications.php';
                envoyerNotification($pdo, $notification[0], $notification[1], $notification[2], '/commercant/index.php');
            } catch (Throwable $t) {
                error_log('[admin/commercants] notification : ' . get_class($t));
            }
        }
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = match ($t->getMessage()) {
            'introuvable' => "Ce commerçant est introuvable.",
            'motif_manquant' => "Indiquez le motif : il est envoyé au commerçant.",
            'montant_invalide' => "Indiquez un montant supérieur à zéro.",
            'reference_manquante' => "Indiquez la référence du règlement (numéro de transfert, reçu).",
            'reference_utilisee' => "Cette référence de règlement existe déjà.",
            default => messageIncident(
                incidentEnregistrer($pdo, $t, 'admin/commercants'),
                "L'action n'a pas pu être enregistrée. Réessayez dans un instant."
            ),
        };
        $message = '';
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_error'] = $error;
    header('Location: /admin/commercants.php');
    exit();
}

$message = $_SESSION['flash_message'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$commercants = [];
try {
    $commercants = $pdo->query(
        "SELECT cp.*, u.fullname, u.email, u.phone, u.is_active, u.status AS statut_compte, u.created_at AS inscrit_le,
                (SELECT COUNT(*) FROM vendeur_produits p WHERE p.vendeur_id = cp.user_id) AS nb_produits,
                (SELECT COUNT(*) FROM vendeur_produits p WHERE p.vendeur_id = cp.user_id AND p.moderation = 'en_attente') AS nb_a_valider,
                (SELECT COUNT(*) FROM vendeur_ventes v JOIN vendeur_produits p ON p.id = v.produit_id WHERE p.vendeur_id = cp.user_id) AS nb_commandes
         FROM commercants_profils cp JOIN users_monrevenu u ON u.id = cp.user_id
         ORDER BY FIELD(cp.statut, 'en_attente', 'valide', 'suspendu', 'refuse'), cp.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $error ?: messageIncident(incidentEnregistrer($pdo, $e, 'admin/commercants/liste'), "La liste n'a pas pu être chargée.");
}
foreach ($commercants as &$c) { $c['dette'] = detteCommercant($pdo, (int) $c['user_id']); }
unset($c);

$compteurs_admin = [
    'commercants' => count(array_filter($commercants, fn($c) => $c['statut'] === 'en_attente')),
    'produits' => (int) $pdo->query("SELECT COUNT(*) FROM vendeur_produits WHERE moderation = 'en_attente'")->fetchColumn(),
];
$titre_page = 'Commerçants';
$page_admin = 'commercants';
include __DIR__ . '/sections/coquille_debut.php';
?>
    <div>
      <h2 class="page-titre">Commerçants</h2>
      <p class="meta mt-1"><?= count($commercants) ?> compte<?= count($commercants) > 1 ? 's' : '' ?>, dont <?= $compteurs_admin['commercants'] ?> en attente de validation</p>
    </div>

    <?php if (!$commercants): ?>
      <div class="carte">
        <div class="vide">
          <?= ico('store', 'ico-40') ?>
          <p class="vide-titre">Aucun commerçant inscrit</p>
          <p class="vide-texte">Les comptes créés avec l'option « Vendre mes produits » apparaissent ici pour validation.</p>
        </div>
      </div>
    <?php else: ?>
      <ul class="flex flex-col gap-3">
        <?php foreach ($commercants as $c): ?>
          <li class="carte flex flex-col gap-3 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-medium text-text"><?= e($c['nom_boutique']) ?></p>
                <p class="meta mt-0.5"><?= e($c['fullname']) ?><?= $c['ville'] ? ', ' . e($c['ville']) : '' ?>, inscrit le <?= e(dateFr($c['inscrit_le'], 'court')) ?></p>
                <p class="meta chiffres"><?= e($c['email']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?></p>
              </div>
              <div class="flex flex-col items-end gap-1">
                <?= badgeStatut($c['statut'], 'boutique') ?>
                <?php if ((int) $c['confiance'] === 1): ?><span class="pastille pastille-info">Publication directe</span><?php endif; ?>
              </div>
            </div>

            <?php if ($c['description']): ?><p class="text-sm text-text-2"><?= e($c['description']) ?></p><?php endif; ?>

            <dl class="recap">
              <div class="recap-ligne"><dt>Produits</dt><dd><?= (int) $c['nb_produits'] ?><?= (int) $c['nb_a_valider'] > 0 ? ', ' . (int) $c['nb_a_valider'] . ' à valider' : '' ?></dd></div>
              <div class="recap-ligne"><dt>Commandes</dt><dd><?= (int) $c['nb_commandes'] ?></dd></div>
              <div class="recap-ligne recap-total"><dt>Dette de commission</dt><dd><?= formaterMontant($c['dette']['solde']) ?></dd></div>
            </dl>
            <?php if ($c['motif']): ?><p class="text-sm text-text-2">Motif enregistré : <?= e($c['motif']) ?></p><?php endif; ?>

            <div class="flex flex-wrap gap-2 border-t border-line pt-3">
              <?php if ($c['statut'] !== 'valide'): ?>
                <form method="POST" action="/admin/commercants.php">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="commercant_id" value="<?= (int) $c['user_id'] ?>">
                  <input type="hidden" name="action" value="<?= $c['statut'] === 'suspendu' ? 'reactiver' : 'valider' ?>">
                  <button type="submit" class="btn btn-sm btn-primaire"><?= ico('badge-check', 'ico-16') ?><?= $c['statut'] === 'suspendu' ? 'Réactiver' : 'Valider la boutique' ?></button>
                </form>
              <?php endif; ?>
              <?php if ($c['statut'] === 'valide' || $c['statut'] === 'en_attente'): ?>
                <button type="button" class="btn btn-sm btn-discret text-danger" data-ouvrir="motif-<?= (int) $c['user_id'] ?>" aria-haspopup="dialog">
                  <?= ico('circle-x', 'ico-16') ?><?= $c['statut'] === 'valide' ? 'Suspendre' : 'Refuser' ?>
                </button>
              <?php endif; ?>
              <form method="POST" action="/admin/commercants.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="commercant_id" value="<?= (int) $c['user_id'] ?>">
                <input type="hidden" name="action" value="confiance">
                <button type="submit" class="btn btn-sm btn-secondaire"><?= ico('shield', 'ico-16') ?><?= (int) $c['confiance'] === 1 ? 'Repasser par la validation' : 'Autoriser la publication directe' ?></button>
              </form>
              <button type="button" class="btn btn-sm btn-secondaire" data-ouvrir="reglement-<?= (int) $c['user_id'] ?>" aria-haspopup="dialog"><?= ico('receipt', 'ico-16') ?>Enregistrer un règlement</button>
            </div>

            <dialog class="feuille" id="motif-<?= (int) $c['user_id'] ?>" aria-labelledby="t-motif-<?= (int) $c['user_id'] ?>">
              <div class="poignee"></div>
              <div class="feuille-entete">
                <h2 class="feuille-titre" id="t-motif-<?= (int) $c['user_id'] ?>"><?= $c['statut'] === 'valide' ? 'Suspendre' : 'Refuser' ?> <?= e($c['nom_boutique']) ?></h2>
                <button type="button" class="btn btn-icone btn-discret" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
              </div>
              <form method="POST" action="/admin/commercants.php">
                <div class="feuille-corps flex flex-col gap-4">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="commercant_id" value="<?= (int) $c['user_id'] ?>">
                  <input type="hidden" name="action" value="<?= $c['statut'] === 'valide' ? 'suspendre' : 'refuser' ?>">
                  <div class="champ">
                    <label class="champ-label" for="motif-champ-<?= (int) $c['user_id'] ?>">Motif</label>
                    <input class="champ-saisie" type="text" id="motif-champ-<?= (int) $c['user_id'] ?>" name="motif" maxlength="255" required>
                    <p class="champ-aide">Le motif est envoyé au commerçant et conservé dans le journal.</p>
                  </div>
                </div>
                <div class="feuille-pied">
                  <button type="button" class="btn btn-secondaire" data-fermer>Revenir</button>
                  <button type="submit" class="btn btn-danger">Confirmer</button>
                </div>
              </form>
            </dialog>

            <dialog class="feuille" id="reglement-<?= (int) $c['user_id'] ?>" aria-labelledby="t-reglement-<?= (int) $c['user_id'] ?>">
              <div class="poignee"></div>
              <div class="feuille-entete">
                <h2 class="feuille-titre" id="t-reglement-<?= (int) $c['user_id'] ?>">Règlement de <?= e($c['nom_boutique']) ?></h2>
                <button type="button" class="btn btn-icone btn-discret" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
              </div>
              <form method="POST" action="/admin/commercants.php">
                <div class="feuille-corps flex flex-col gap-4">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="commercant_id" value="<?= (int) $c['user_id'] ?>">
                  <input type="hidden" name="action" value="reglement">
                  <p class="text-sm text-text-2">Reste à régler : <?= formaterMontant($c['dette']['solde']) ?>.</p>
                  <div class="champ">
                    <label class="champ-label" for="montant-<?= (int) $c['user_id'] ?>">Montant reçu</label>
                    <input class="champ-saisie chiffres" type="number" id="montant-<?= (int) $c['user_id'] ?>" name="montant" min="1" step="1" inputmode="numeric" required>
                  </div>
                  <div class="champ">
                    <label class="champ-label" for="reference-<?= (int) $c['user_id'] ?>">Référence</label>
                    <input class="champ-saisie" type="text" id="reference-<?= (int) $c['user_id'] ?>" name="reference" maxlength="100" required>
                    <p class="champ-aide">Numéro de transfert ou de reçu. Une référence ne peut servir qu'une fois.</p>
                  </div>
                  <div class="champ">
                    <label class="champ-label" for="note-<?= (int) $c['user_id'] ?>">Note <span class="font-normal text-text-3">(facultatif)</span></label>
                    <input class="champ-saisie" type="text" id="note-<?= (int) $c['user_id'] ?>" name="motif" maxlength="255">
                  </div>
                </div>
                <div class="feuille-pied">
                  <button type="button" class="btn btn-secondaire" data-fermer>Revenir</button>
                  <button type="submit" class="btn btn-primaire">Enregistrer</button>
                </div>
              </form>
            </dialog>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
<?php include __DIR__ . '/sections/coquille_fin.php'; ?>
