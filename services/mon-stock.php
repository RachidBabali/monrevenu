<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
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
        $_SESSION['flash_error'] = "Votre session a expiré. Rechargez la page puis recommencez.";
        header('Location: mon-stock.php'); exit();
    }

    $produit_id = (int) ($_POST['produit_id'] ?? 0);
    $quantite   = (int) ($_POST['quantite'] ?? 0);

    if ($produit_id <= 0 || $quantite <= 0) {
        $_SESSION['flash_error'] = "Choisissez un produit et une quantité d'au moins 1.";
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

        require_once __DIR__ . '/../includs/audit.php';
        auditCritique($pdo, ['category' => 'commande', 'action' => 'vente_stock_declaration', 'entity_type' => 'vente_stock', 'entity_id' => $vente_id,
            'before' => ['quantite' => $stock_avant], 'after' => ['quantite' => $stock_apres],
            'meta' => ['produit_id' => $produit_id, 'vendu' => $quantite, 'commission' => $commission_montant, 'reference' => $reference]]);

        $pdo->commit();

        $_SESSION['flash_success'] = "Vente enregistrée : {$quantite} x " . $ligne['nom_produit'] . ". Commission de " . formaterMontant($commission_montant) . " en attente d'envoi par l'administration.";

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erreur enregistrement vente stock : ' . $e->getMessage());
        $_SESSION['flash_error'] = "La vente n'a pas pu être enregistrée. Réessayez dans un instant.";
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
$image_stock = static fn(?string $img): string => $img ? (preg_match('#^https?://#', $img) ? $img : '/admin/' . $img) : '';
$titre_page = 'Mon stock';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>

    <?php if (!empty($produits_stock_faible)): ?>
      <div class="alerte alerte-attention" role="status">
        <?= ico('circle-alert') ?>
        <div>
          <p class="font-medium">Stock bas</p>
          <ul class="mt-1">
            <?php foreach ($produits_stock_faible as $p): ?>
              <li><?= e($p['nom_produit']) ?> : <?= (int) $p['quantite_disponible'] ?> unité<?= $p['quantite_disponible'] > 1 ? 's' : '' ?> restante<?= $p['quantite_disponible'] > 1 ? 's' : '' ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <dl class="indicateurs">
      <div class="indicateur"><dt class="indicateur-libelle">Commissions</dt><dd class="indicateur-valeur"><?= formaterMontant($commissions_gagnees) ?></dd><dd class="indicateur-note">dont <?= e(formaterMontant($commissions_en_attente)) ?> à envoyer</dd></div>
      <div class="indicateur"><dt class="indicateur-libelle">Unités en stock</dt><dd class="indicateur-valeur"><?= (int) $stock_total_unites ?></dd><dd class="indicateur-note"><?= $nb_produits_stock ?> produit<?= $nb_produits_stock > 1 ? 's' : '' ?></dd></div>
      <div class="indicateur"><dt class="indicateur-libelle">Ventes déclarées</dt><dd class="indicateur-valeur"><?= (int) $nb_ventes_total ?></dd><dd class="indicateur-note">50 dernières</dd></div>
      <div class="indicateur"><dt class="indicateur-libelle">Montant encaissé</dt><dd class="indicateur-valeur"><?= formaterMontant($chiffre_affaires) ?></dd><dd class="indicateur-note">sur ces ventes</dd></div>
    </dl>

    <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
      <section class="carte self-start" aria-labelledby="t-vente">
        <h2 id="t-vente" class="carte-entete carte-titre">Déclarer une vente</h2>
        <?php if (empty($mon_stock)): ?>
          <p class="p-4 text-sm text-text-2">Aucun stock ne vous est attribué. L'équipe MonRevenu vous prévient quand du stock est ajouté.</p>
        <?php else: ?>
          <form action="" method="POST" class="flex flex-col gap-4 p-4">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <div class="champ">
              <label class="champ-label" for="produit_id">Produit</label>
              <select class="champ-saisie" id="produit_id" name="produit_id" required>
                <option value="">Choisir un produit</option>
                <?php foreach ($mon_stock as $p): ?>
                  <option value="<?= (int) $p['produit_id'] ?>"<?= $p['quantite_disponible'] <= 0 ? ' disabled' : '' ?>><?= e($p['nom_produit']) ?> (<?= (int) $p['quantite_disponible'] ?> disponible<?= $p['quantite_disponible'] > 1 ? 's' : '' ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="champ">
              <label class="champ-label" for="quantite">Quantité vendue</label>
              <input class="champ-saisie chiffres" type="number" id="quantite" name="quantite" min="1" step="1" inputmode="numeric" required value="1">
            </div>
            <button type="submit" name="action_enregistrer_vente" class="btn btn-primaire btn-bloc"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Enregistrer la vente</span></button>
            <p class="text-xs text-text-3">La commission est envoyée sur votre solde après contrôle par l'administration.</p>
          </form>
        <?php endif; ?>
      </section>

      <section class="flex min-w-0 flex-col gap-3" aria-labelledby="t-stock">
        <h2 id="t-stock" class="section-titre">Mon stock</h2>
        <div class="carte overflow-hidden">
          <?php if (empty($mon_stock)): ?>
            <div class="vide"><?= ico('package', 'ico-40') ?><p class="vide-titre">Aucun produit en stock</p><p class="vide-texte">Le stock confié par MonRevenu apparaîtra ici.</p></div>
          <?php else: ?>
            <table class="tableau tableau-empile">
              <thead><tr><th scope="col">Produit</th><th scope="col" class="col-montant">Prix</th><th scope="col" class="col-montant">Commission</th><th scope="col" class="col-montant">Vendu</th><th scope="col" class="col-montant">Disponible</th></tr></thead>
              <tbody>
              <?php foreach ($mon_stock as $p): $img = $image_stock($p['image']); ?>
                <tr>
                  <td data-label="">
                    <span class="flex items-center gap-3">
                      <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded bg-surface-2">
                        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" width="40" height="40" loading="lazy" class="h-full w-full object-contain"><?php else: ?><?= ico('image', 'text-text-3') ?><?php endif; ?>
                      </span>
                      <span class="font-medium"><?= e($p['nom_produit']) ?></span>
                    </span>
                  </td>
                  <td data-label="Prix" class="col-montant"><?= montant($p['prix_vente'], false, 'font-normal') ?></td>
                  <td data-label="Commission" class="col-montant"><?= montant($p['commission_fixe']) ?></td>
                  <td data-label="Vendu" class="col-montant chiffres"><?= (int) $p['quantite_vendue'] ?></td>
                  <td data-label="Disponible" class="col-montant chiffres font-semibold<?= $p['quantite_disponible'] <= SEUIL_STOCK_FAIBLE ? ' text-warning' : '' ?>"><?= (int) $p['quantite_disponible'] ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <h2 class="section-titre mt-3">Ventes déclarées</h2>
        <div class="carte overflow-hidden">
          <?php if (empty($mes_ventes)): ?>
            <div class="vide"><?= ico('receipt', 'ico-40') ?><p class="vide-titre">Aucune vente déclarée</p><p class="vide-texte">Chaque vente enregistrée apparaît ici avec l'état de sa commission.</p></div>
          <?php else: ?>
            <?php foreach ($mes_ventes as $v): ?>
              <div class="ligne-tx">
                <span class="ligne-tx-icone"><?= ico('receipt') ?></span>
                <div class="ligne-tx-corps">
                  <p class="ligne-tx-titre"><?= e($v['nom_produit']) ?></p>
                  <p class="ligne-tx-meta"><span class="chiffres"><?= (int) $v['quantite'] ?> x <?= e(formaterMontant($v['prix_unitaire'])) ?></span><span class="chiffres"><?= e(dateFr($v['created_at'], 'heure')) ?></span><?php if (!empty($v['reference'])): ?><span class="font-mono"><?= e($v['reference']) ?></span><?php endif; ?></p>
                </div>
                <div class="ligne-tx-montant"><?= montant($v['commission_montant'], true, 'montant-entrant') ?><div class="mt-1"><?= badgeStatut((string) (int) $v['commission_envoyee'], 'stock') ?></div></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
