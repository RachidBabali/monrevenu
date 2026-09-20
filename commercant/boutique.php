<?php
/**
 * commercant/boutique.php : profil de la boutique et suivi de la dette de commission.
 * Le statut de la boutique et la liste des reglements sont en lecture seule : seul un administrateur les change.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/audit.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/incident.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/commercant.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';

$profil = exigerCommercant($pdo);
$id = (int) $profil['user_id'];
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'commercant_boutique');
        $erreur = 'Votre session a expiré. Rechargez la page puis recommencez.';
    } else {
        $nom = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nom_boutique'] ?? '')));
        $ville = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['ville'] ?? '')));
        $description = trim((string) ($_POST['description'] ?? ''));
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 120) {
            $erreur = 'Indiquez le nom de votre boutique (2 à 120 caractères).';
        } elseif (mb_strlen($ville) > 100 || mb_strlen($description) > 2000) {
            $erreur = 'Ville (100 caractères) ou description (2000 caractères) trop longue.';
        } else {
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare("SELECT nom_boutique, ville, description FROM commercants_profils WHERE user_id = ? FOR UPDATE");
                $st->execute([$id]);
                $avant = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $pdo->prepare("UPDATE commercants_profils SET nom_boutique = ?, ville = ?, description = ? WHERE user_id = ?")
                    ->execute([$nom, $ville !== '' ? $ville : null, $description !== '' ? $description : null, $id]);
                [$b, $a] = auditDiff($avant, ['nom_boutique' => $nom, 'ville' => $ville !== '' ? $ville : null, 'description' => $description !== '' ? $description : null]);
                if (isset($a['description'])) { $b['description'] = '[modifiee]'; $a['description'] = '[modifiee]'; }
                if ($a) {
                    auditCritique($pdo, ['category' => 'compte', 'action' => 'boutique_modification', 'entity_type' => 'commercant',
                        'entity_id' => $id, 'before' => $b, 'after' => $a]);
                }
                $pdo->commit();
                $_SESSION['flash_success'] = 'Informations de la boutique enregistrées.';
                header('Location: /commercant/boutique.php'); exit();
            } catch (Throwable $t) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erreur = messageIncident(incidentEnregistrer($pdo, $t, 'commercant/boutique'),
                    "Les informations n'ont pas pu être enregistrées. Réessayez dans un instant.");
            }
        }
        $profil['nom_boutique'] = $nom;
        $profil['ville'] = $ville;
        $profil['description'] = $description;
    }
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $erreur ?: ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$dette = detteCommercant($pdo, $id);
$reglements = [];
$ventesValidees = 0;
try {
    $st = $pdo->prepare("SELECT montant, reference, note, created_at FROM commercant_reglements WHERE commercant_id = ? ORDER BY id DESC LIMIT 20");
    $st->execute([$id]);
    $reglements = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM vendeur_ventes v JOIN vendeur_produits p ON p.id = v.produit_id
         WHERE p.vendeur_id = ? AND v.statut = 'validee' AND v.commission_creditee = 1"
    );
    $st->execute([$id]);
    $ventesValidees = (int) $st->fetchColumn();
} catch (PDOException $e) {
    error_log('[commercant/boutique] ' . $e->getMessage());
}

$titre_page = 'Ma boutique';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>
    <div>
      <h2 class="page-titre">Ma boutique</h2>
      <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-text-2">
        <?= badgeStatut($profil['statut'], 'boutique') ?>
        <?php if ($profil['motif']): ?><span>Motif : <?= e($profil['motif']) ?></span><?php endif; ?>
      </p>
    </div>

    <form method="POST" action="/commercant/boutique.php" class="carte flex flex-col gap-4 p-4" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="champ">
        <label class="champ-label" for="nom_boutique">Nom de la boutique</label>
        <input class="champ-saisie" type="text" id="nom_boutique" name="nom_boutique" maxlength="120" required value="<?= e($profil['nom_boutique']) ?>">
      </div>
      <div class="champ">
        <label class="champ-label" for="ville">Ville <span class="font-normal text-text-3">(facultatif)</span></label>
        <input class="champ-saisie" type="text" id="ville" name="ville" maxlength="100" value="<?= e($profil['ville'] ?? '') ?>">
      </div>
      <div class="champ">
        <label class="champ-label" for="description">Description <span class="font-normal text-text-3">(facultatif)</span></label>
        <textarea class="champ-saisie" id="description" name="description" rows="4" maxlength="2000"><?= e($profil['description'] ?? '') ?></textarea>
        <p class="champ-aide">Ce que vous vendez, vos zones de livraison, vos délais habituels.</p>
      </div>
      <div class="flex justify-end border-t border-line pt-4">
        <button type="submit" class="btn btn-primaire"><?= ico('check') ?>Enregistrer</button>
      </div>
    </form>

    <section class="flex flex-col gap-3" aria-labelledby="t-dette">
      <h2 id="t-dette" class="section-titre">Commissions dues à MonRevenu</h2>
      <dl class="indicateurs">
        <div class="indicateur">
          <dt class="indicateur-libelle">Commissions des ventes validées</dt>
          <dd class="indicateur-valeur"><?= formaterMontant($dette['commissions']) ?></dd>
          <dd class="indicateur-note"><?= $ventesValidees ?> vente<?= $ventesValidees > 1 ? 's' : '' ?> validée<?= $ventesValidees > 1 ? 's' : '' ?></dd>
        </div>
        <div class="indicateur">
          <dt class="indicateur-libelle">Déjà réglé</dt>
          <dd class="indicateur-valeur"><?= formaterMontant($dette['reglements']) ?></dd>
          <dd class="indicateur-note"><?= count($reglements) ?> règlement<?= count($reglements) > 1 ? 's' : '' ?> enregistré<?= count($reglements) > 1 ? 's' : '' ?></dd>
        </div>
        <div class="indicateur">
          <dt class="indicateur-libelle">Reste à régler</dt>
          <dd class="indicateur-valeur"><?= formaterMontant($dette['solde']) ?></dd>
          <dd class="indicateur-note">MonRevenu ne prélève rien : le règlement se fait avec l'équipe.</dd>
        </div>
      </dl>
      <div class="carte overflow-hidden">
        <?php if (!$reglements): ?>
          <div class="vide">
            <?= ico('receipt', 'ico-40') ?>
            <p class="vide-titre">Aucun règlement enregistré</p>
            <p class="vide-texte">Les règlements que vous effectuez sont saisis par MonRevenu et apparaissent ici.</p>
          </div>
        <?php else: ?>
          <table class="tableau tableau-empile">
            <thead><tr><th scope="col">Date</th><th scope="col">Référence</th><th scope="col">Note</th><th scope="col" class="col-montant">Montant</th></tr></thead>
            <tbody>
            <?php foreach ($reglements as $r): ?>
              <tr>
                <td data-label="Date" class="chiffres whitespace-nowrap text-text-2"><?= e(dateFr($r['created_at'], 'court')) ?></td>
                <td data-label="Référence" class="chiffres"><?= e($r['reference']) ?></td>
                <td data-label="Note" class="text-text-2"><?= e($r['note'] ?? '') ?></td>
                <td data-label="Montant" class="col-montant"><?= montant($r['montant']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
