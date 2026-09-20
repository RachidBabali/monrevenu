<?php
/**
 * commercant/commandes.php : commandes passees sur les produits du commercant.
 * Le commercant fait avancer une commande jusqu'a "colis recu" ou l'annule avec un motif.
 * Seul un administrateur valide une vente (ce qui credite la commission de l'affilie).
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'commercant_commandes');
        $_SESSION['flash_error'] = 'Votre session a expiré. Rechargez la page puis recommencez.';
        header('Location: /commercant/commandes.php'); exit();
    }
    $commande_id = (int) ($_POST['commande_id'] ?? 0);
    $nouveau = (string) ($_POST['statut'] ?? '');
    $motif = mb_substr(trim((string) ($_POST['motif'] ?? '')), 0, 255);
    $notification = null;
    try {
        $pdo->beginTransaction();
        // La propriete est verifiee par la jointure : le produit doit appartenir au commercant connecte
        $st = $pdo->prepare(
            "SELECT v.* FROM vendeur_ventes v JOIN vendeur_produits p ON p.id = v.produit_id
             WHERE v.id = ? AND p.vendeur_id = ? FOR UPDATE"
        );
        $st->execute([$commande_id, $id]);
        $commande = $st->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            $pdo->rollBack();
            auditInfo($pdo, ['category' => 'commande', 'action' => 'commande_acces_refuse', 'result' => 'refus',
                'entity_type' => 'commande', 'entity_id' => $commande_id]);
            $_SESSION['flash_error'] = "Cette commande est introuvable.";
            header('Location: /commercant/commandes.php'); exit();
        }
        $autorises = COMMERCANT_TRANSITIONS[$commande['statut']] ?? [];
        if (!in_array($nouveau, $autorises, true)) {
            throw new RuntimeException('transition_interdite');
        }
        if ($nouveau === 'annulee' && $motif === '') {
            throw new RuntimeException('motif_manquant');
        }

        $pdo->prepare("UPDATE vendeur_ventes SET statut = ?, motif_annulation = ? WHERE id = ?")
            ->execute([$nouveau, $nouveau === 'annulee' ? $motif : $commande['motif_annulation'], $commande_id]);
        auditCritique($pdo, ['category' => 'commande', 'action' => 'commande_statut', 'entity_type' => 'commande', 'entity_id' => $commande_id,
            'before' => ['statut' => $commande['statut']], 'after' => ['statut' => $nouveau],
            'meta' => ['par' => 'commercant', 'produit_id' => (int) $commande['produit_id']] + ($nouveau === 'annulee' ? ['motif' => $motif] : [])]);

        $textes = [
            'contacte'   => "Le client de votre vente #" . $commande_id . " a été contacté par le vendeur.",
            'colis_recu' => "Le client de votre vente #" . $commande_id . " a reçu son colis. MonRevenu valide la vente sous peu.",
            'annulee'    => "Votre vente #" . $commande_id . " a été annulée par le vendeur. Motif : " . $motif,
        ];
        $titres = ['contacte' => 'Client contacté', 'colis_recu' => 'Colis reçu', 'annulee' => 'Vente annulée'];
        $affilie = (int) $commande['vendeur_id'];
        $notification = [$affilie, $textes[$nouveau], $titres[$nouveau]];

        $pdo->commit();
        if ($notification) {
            try {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/notifications.php';
                envoyerNotification($pdo, $notification[0], $notification[1], $notification[2], '/page/historique.php');
            } catch (Throwable $t) {
                error_log('[commercant/commandes] notification : ' . get_class($t));
            }
        }
        $_SESSION['flash_success'] = $nouveau === 'annulee' ? 'Commande annulée. L\'affilié a été prévenu.' : 'Commande mise à jour.';
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = match ($t->getMessage()) {
            'transition_interdite' => "Cette commande ne peut pas passer à cet état. Seul MonRevenu valide une vente.",
            'motif_manquant' => "Indiquez le motif de l'annulation : il est envoyé à l'affilié.",
            default => messageIncident(incidentEnregistrer($pdo, $t, 'commercant/commandes'),
                "La commande n'a pas pu être mise à jour. Réessayez dans un instant."),
        };
    }
    header('Location: /commercant/commandes.php'); exit();
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$filtres = [
    'a_traiter' => ["v.statut IN ('en_attente', 'contacte', 'colis_recu')", 'À traiter'],
    'validees'  => ["v.statut = 'validee'", 'Validées'],
    'annulees'  => ["v.statut = 'annulee'", 'Annulées'],
    'toutes'    => ['1 = 1', 'Toutes'],
];
$filtre = isset($filtres[$_GET['etat'] ?? '']) ? $_GET['etat'] : 'a_traiter';

$commandes = [];
try {
    $st = $pdo->prepare(
        "SELECT v.*, p.nom_produit FROM vendeur_ventes v JOIN vendeur_produits p ON p.id = v.produit_id
         WHERE p.vendeur_id = ? AND " . $filtres[$filtre][0] . " ORDER BY v.created_at DESC, v.id DESC LIMIT 100"
    );
    $st->execute([$id]);
    $commandes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[commercant/commandes] ' . $e->getMessage());
    $message_error = "Les commandes n'ont pas pu être chargées. Actualisez la page.";
}

$titre_page = 'Commandes';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="page-titre">Commandes</h2>
        <p class="meta mt-1">Les coordonnées du client servent à confirmer et livrer la commande.</p>
      </div>
      <nav class="segments" aria-label="Filtrer les commandes">
        <?php foreach ($filtres as $cle => [$sql, $libelle]): ?>
          <a class="segment" href="?etat=<?= e($cle) ?>"<?= $filtre === $cle ? ' aria-current="true"' : '' ?>><?= e($libelle) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <?php if (!$commandes): ?>
      <div class="carte">
        <div class="vide">
          <?= ico('inbox', 'ico-40') ?>
          <p class="vide-titre">Aucune commande dans cette vue</p>
          <p class="vide-texte">Les commandes passées sur vos produits depuis les liens des affiliés apparaissent ici.</p>
        </div>
      </div>
    <?php else: ?>
      <ul class="flex flex-col gap-3">
        <?php foreach ($commandes as $c): ?>
          <?php $suite = COMMERCANT_TRANSITIONS[$c['statut']] ?? []; ?>
          <li class="carte flex flex-col gap-3 p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-medium text-text"><?= e($c['nom_produit']) ?><?= (int) $c['quantite'] > 1 ? ' <span class="font-normal text-text-3">x' . (int) $c['quantite'] . '</span>' : '' ?></p>
                <p class="meta mt-0.5">Commande n° <?= (int) $c['id'] ?>, <?= e(dateFr($c['created_at'], 'long')) ?></p>
              </div>
              <div class="flex flex-col items-end gap-1">
                <?= badgeStatut($c['statut'], 'commande') ?>
                <span class="montant"><?= formaterMontant((float) $c['prix_unitaire'] * (int) $c['quantite']) ?></span>
              </div>
            </div>

            <dl class="recap">
              <div class="recap-ligne"><dt>Client</dt><dd><?= e($c['nom_client']) ?></dd></div>
              <div class="recap-ligne"><dt>Téléphone</dt><dd><a class="lien chiffres" href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) $c['telephone_client'])) ?>" target="_blank" rel="noopener"><?= e($c['telephone_client']) ?></a></dd></div>
              <?php if (!empty($c['adresse_client'])): ?>
                <div class="recap-ligne"><dt>Adresse</dt><dd class="text-right"><?= e($c['adresse_client']) ?></dd></div>
              <?php endif; ?>
              <div class="recap-ligne"><dt>Commission due à MonRevenu</dt><dd><?= formaterMontant($c['commission_earn']) ?></dd></div>
              <?php if ($c['statut'] === 'annulee' && !empty($c['motif_annulation'])): ?>
                <div class="recap-ligne"><dt>Motif d'annulation</dt><dd class="text-right"><?= e($c['motif_annulation']) ?></dd></div>
              <?php endif; ?>
            </dl>

            <?php if ($suite): ?>
              <div class="flex flex-wrap items-center gap-2 border-t border-line pt-3">
                <?php foreach ($suite as $etat): ?>
                  <?php if ($etat === 'annulee'): ?>
                    <button type="button" class="btn btn-sm btn-discret text-danger" data-ouvrir="annuler-<?= (int) $c['id'] ?>" aria-haspopup="dialog"><?= ico('circle-x', 'ico-16') ?>Annuler</button>
                  <?php else: ?>
                    <form method="POST" action="/commercant/commandes.php">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="commande_id" value="<?= (int) $c['id'] ?>">
                      <input type="hidden" name="statut" value="<?= e($etat) ?>">
                      <button type="submit" class="btn btn-sm btn-primaire">
                        <?= ico($etat === 'contacte' ? 'phone' : 'truck', 'ico-16') ?><?= $etat === 'contacte' ? 'Client contacté' : 'Colis reçu par le client' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($c['statut'] === 'colis_recu'): ?>
                  <p class="meta">MonRevenu valide la vente et paie l'affilié.</p>
                <?php endif; ?>
              </div>

              <?php if (in_array('annulee', $suite, true)): ?>
                <dialog class="feuille" id="annuler-<?= (int) $c['id'] ?>" aria-labelledby="titre-annuler-<?= (int) $c['id'] ?>">
                  <div class="poignee"></div>
                  <div class="feuille-entete">
                    <h2 class="feuille-titre" id="titre-annuler-<?= (int) $c['id'] ?>">Annuler la commande n° <?= (int) $c['id'] ?></h2>
                    <button type="button" class="btn btn-icone btn-discret" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
                  </div>
                  <form method="POST" action="/commercant/commandes.php">
                    <div class="feuille-corps flex flex-col gap-4">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="commande_id" value="<?= (int) $c['id'] ?>">
                      <input type="hidden" name="statut" value="annulee">
                      <div class="champ">
                        <label class="champ-label" for="motif-<?= (int) $c['id'] ?>">Motif de l'annulation</label>
                        <input class="champ-saisie" type="text" id="motif-<?= (int) $c['id'] ?>" name="motif" maxlength="255" required
                               placeholder="Produit indisponible, client injoignable...">
                        <p class="champ-aide">Ce motif est envoyé à l'affilié qui a réalisé la vente.</p>
                      </div>
                    </div>
                    <div class="feuille-pied">
                      <button type="button" class="btn btn-secondaire" data-fermer>Revenir</button>
                      <button type="submit" class="btn btn-danger">Annuler la commande</button>
                    </div>
                  </form>
                </dialog>
              <?php endif; ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
