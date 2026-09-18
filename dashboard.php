<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
exigerConnexion();

$user_id       = $_SESSION['user_id'];
$user_fullname = $_SESSION['user_fullname'] ?? 'Utilisateur';
$user_initials = strtoupper(substr($user_fullname, 0, 2));
$prenom        = explode(' ', $user_fullname)[0];

// Solde + phone + rôle (requis par wallet.php) + phone_verified (bannière WhatsApp)
$stmt = $pdo->prepare("SELECT balance, role, phone, phone_verified FROM users_monrevenu WHERE id = ?");
$stmt->execute([$user_id]);
$sender         = $stmt->fetch();
$balance        = $sender['balance'] ?? 0;
$role           = $sender['role'] ?? 'client';
$phone_verifie  = (int) ($sender['phone_verified'] ?? 0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/* Indicateurs du tableau de bord : lectures seules (SELECT prepares), aucune ecriture. */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';
$periodes_valides = [7, 30, 90, 0];
$periode = (int) ($_GET['periode'] ?? 30);
if (!in_array($periode, $periodes_valides, true)) {
    $periode = 30;
}
$filtre_date = $periode > 0 ? ' AND v.created_at >= (NOW() - INTERVAL ' . $periode . ' DAY)' : '';
$indic = ['creditees' => 0.0, 'nb_creditees' => 0, 'attente' => 0.0, 'nb_attente' => 0, 'ventes' => 0, 'annulees' => 0];
$dernier_retrait = null;
$dernieres_commissions = [];
$produits_a_promouvoir = [];
try {
    $st = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN v.statut = 'validee' THEN v.commission_earn END), 0) AS creditees,
            COUNT(CASE WHEN v.statut = 'validee' THEN 1 END) AS nb_creditees,
            COUNT(CASE WHEN v.statut <> 'annulee' THEN 1 END) AS ventes,
            COUNT(CASE WHEN v.statut = 'annulee' THEN 1 END) AS annulees
         FROM vendeur_ventes v WHERE v.vendeur_id = ?" . $filtre_date
    );
    $st->execute([$user_id]);
    $indic = array_merge($indic, $st->fetch() ?: []);

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(commission_earn), 0) AS attente, COUNT(*) AS nb_attente
         FROM vendeur_ventes WHERE vendeur_id = ? AND statut IN ('en_attente', 'contacte', 'colis_recu')"
    );
    $st->execute([$user_id]);
    $indic = array_merge($indic, $st->fetch() ?: []);

    $st = $pdo->prepare("SELECT amount, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $st->execute([$user_id]);
    $dernier_retrait = $st->fetch() ?: null;

    $st = $pdo->prepare(
        "SELECT v.id, v.quantite, v.commission_earn, v.statut, v.created_at, p.nom_produit
         FROM vendeur_ventes v JOIN vendeur_produits p ON p.id = v.produit_id
         WHERE v.vendeur_id = ? ORDER BY v.created_at DESC LIMIT 5"
    );
    $st->execute([$user_id]);
    $dernieres_commissions = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT id, nom_produit, image, prix_vente FROM vendeur_produits
         WHERE statut = 'actif' ORDER BY prix_vente DESC, id DESC LIMIT 4"
    );
    $st->execute();
    $produits_a_promouvoir = $st->fetchAll();
} catch (PDOException $e) {
    $message_error = $message_error ?: "Certaines informations n'ont pas pu être chargées. Actualisez la page.";
}
$libelle_periode = [7 => '7 derniers jours', 30 => '30 derniers jours', 90 => '90 derniers jours', 0 => 'depuis le début'][$periode];

$titre_page = 'Accueil';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>

    <?php if ($phone_verifie !== 1): ?>
      <?php include $_SERVER['DOCUMENT_ROOT'] . '/sections/banniere_verification_whatsapp.php'; ?>
    <?php endif; ?>

    <div>
      <h2 class="page-titre">Bonjour, <?= e($prenom) ?></h2>
      <p class="mt-1 text-sm text-text-2"><?= e(ucfirst(dateFr('now', 'jour_semaine'))) ?></p>
    </div>

    <section class="carte flex flex-col gap-4 p-4 sm:flex-row sm:items-end sm:justify-between" aria-labelledby="t-solde">
      <div>
        <h3 id="t-solde" class="text-sm font-normal text-text-2">Solde disponible</h3>
        <p class="montant mt-1 text-4xl"><?= formaterMontant($balance) ?></p>
        <p class="meta mt-1">Retrait possible à partir de <?= formaterMontant(1000) ?></p>
      </div>
      <div class="grid grid-cols-2 gap-2 sm:flex">
        <a class="btn btn-primaire" href="/page/portefeuille.php#retrait"><?= ico('banknote') ?>Retirer</a>
        <a class="btn btn-secondaire" href="/page/historique.php"><?= ico('history') ?>Historique</a>
      </div>
    </section>

    <section class="flex flex-col gap-3" aria-labelledby="t-activite">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="t-activite" class="section-titre">Activité</h2>
        <nav class="segments" aria-label="Période des indicateurs">
          <?php foreach ([7 => '7 j', 30 => '30 j', 90 => '90 j', 0 => 'Tout'] as $val => $lib): ?>
            <a class="segment" href="?periode=<?= $val ?>"<?= $periode === $val ? ' aria-current="true"' : '' ?>><?= e($lib) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <dl class="indicateurs">
        <div class="indicateur">
          <dt class="indicateur-libelle">Commissions créditées</dt>
          <dd class="indicateur-valeur"><?= formaterMontant($indic['creditees']) ?></dd>
          <dd class="indicateur-note"><?= (int) $indic['nb_creditees'] === 0 ? 'Aucune vente validée' : (int) $indic['nb_creditees'] . ' vente' . ($indic['nb_creditees'] > 1 ? 's validées' : ' validée') ?>, <?= e($libelle_periode) ?></dd>
        </div>
        <div class="indicateur">
          <dt class="indicateur-libelle">Commissions en attente</dt>
          <dd class="indicateur-valeur"><?= formaterMontant($indic['attente']) ?></dd>
          <dd class="indicateur-note"><?= (int) $indic['nb_attente'] === 0 ? 'Aucune commande' : (int) $indic['nb_attente'] . ' commande' . ($indic['nb_attente'] > 1 ? 's' : '') ?> à confirmer</dd>
        </div>
        <div class="indicateur">
          <dt class="indicateur-libelle">Ventes</dt>
          <dd class="indicateur-valeur"><?= (int) $indic['ventes'] ?></dd>
          <dd class="indicateur-note"><?= e($libelle_periode) ?><?= $indic['annulees'] > 0 ? ', ' . (int) $indic['annulees'] . ' annulée' . ($indic['annulees'] > 1 ? 's' : '') . ' en plus' : '' ?></dd>
        </div>
        <div class="indicateur">
          <dt class="indicateur-libelle">Dernier retrait</dt>
          <?php if ($dernier_retrait): ?>
            <dd class="indicateur-valeur"><?= formaterMontant($dernier_retrait['amount']) ?></dd>
            <dd class="indicateur-note"><?= e(['valide' => 'Payé', 'rejete' => 'Refusé', 'en_attente' => 'En attente'][$dernier_retrait['status']] ?? $dernier_retrait['status']) ?>, demandé le <?= e(dateFr($dernier_retrait['created_at'], 'court')) ?></dd>
          <?php else: ?>
            <dd class="indicateur-valeur text-text-3">Aucun</dd>
            <dd class="indicateur-note">Pas encore de demande</dd>
          <?php endif; ?>
        </div>
      </dl>
    </section>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
      <section class="flex min-w-0 flex-col gap-3" aria-labelledby="t-commissions">
        <div class="flex items-end justify-between gap-3">
          <h2 id="t-commissions" class="section-titre">Dernières commissions</h2>
          <a class="lien text-sm" href="/page/historique.php?type=commission">Tout voir</a>
        </div>
        <div class="carte overflow-hidden">
          <?php if (!$dernieres_commissions): ?>
            <div class="vide">
              <?= ico('hand-coins', 'ico-40') ?>
              <p class="vide-titre">Aucune commande pour l'instant</p>
              <p class="vide-texte">Chaque commande passée avec votre lien apparaît ici, avec son statut.</p>
              <a class="btn btn-sm btn-primaire mt-2" href="/services/boutique.php">Choisir un produit</a>
            </div>
          <?php else: ?>
            <table class="tableau tableau-empile">
              <thead><tr><th scope="col">Produit</th><th scope="col">Date</th><th scope="col">Statut</th><th scope="col" class="col-montant">Commission</th></tr></thead>
              <tbody>
              <?php foreach ($dernieres_commissions as $c): ?>
                <tr>
                  <td data-label="" class="font-medium"><span><?= e($c['nom_produit']) ?><?= (int) $c['quantite'] > 1 ? ' <span class="font-normal text-text-3">x' . (int) $c['quantite'] . '</span>' : '' ?></span></td>
                  <td data-label="Date" class="chiffres whitespace-nowrap text-text-2"><?= e(dateFr($c['created_at'], 'court')) ?></td>
                  <td data-label="Statut"><?= badgeStatut($c['statut'], 'commission') ?></td>
                  <td data-label="Commission" class="col-montant"><?= montant($c['commission_earn'], $c['statut'] === 'validee', $c['statut'] === 'validee' ? 'montant-entrant' : ($c['statut'] === 'annulee' ? 'text-text-3 line-through' : '')) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </section>

      <aside class="flex min-w-0 flex-col gap-6">
        <section class="flex flex-col gap-3" aria-labelledby="t-promouvoir">
          <div class="flex items-end justify-between gap-3">
            <h2 id="t-promouvoir" class="section-titre">Produits à promouvoir</h2>
            <a class="lien text-sm" href="/services/boutique.php?tri=commission">Catalogue</a>
          </div>
          <div class="carte">
            <?php if (!$produits_a_promouvoir): ?>
              <p class="px-4 py-6 text-sm text-text-2">Aucun produit actif dans le catalogue.</p>
            <?php endif; ?>
            <?php foreach ($produits_a_promouvoir as $p): ?>
              <a class="ligne-tx hover:bg-surface-2" href="/services/boutique.php?q=<?= rawurlencode($p['nom_produit']) ?>#produit-<?= (int) $p['id'] ?>">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded bg-surface-2">
                  <?php if (!empty($p['image'])): ?>
                    <img src="<?= e(preg_match('#^https?://#', $p['image']) ? $p['image'] : '/admin/' . $p['image']) ?>" alt="" width="44" height="44" loading="lazy" class="h-full w-full object-contain">
                  <?php else: ?>
                    <?= ico('image', 'text-text-3') ?>
                  <?php endif; ?>
                </span>
                <span class="ligne-tx-corps">
                  <span class="ligne-tx-titre block line-clamp-2"><?= e($p['nom_produit']) ?></span>
                  <span class="ligne-tx-meta"><?= montant($p['prix_vente'], false, 'font-normal') ?></span>
                </span>
                <span class="ligne-tx-montant"><span class="meta block">Commission</span><?= montant(calculerCommission((float) $p['prix_vente']), false, 'montant-entrant') ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="t-raccourcis">
          <h2 id="t-raccourcis" class="section-titre">Raccourcis</h2>
          <nav class="carte liste-nav" aria-labelledby="t-raccourcis">
            <a class="liste-nav-lien" href="/services/boutique.php"><?= ico('store') ?>Catalogue et liens d'affiliation<?= ico('chevron-right', 'ico-16') ?></a>
            <a class="liste-nav-lien" href="/page/portefeuille.php"><?= ico('wallet') ?>Portefeuille et retraits<?= ico('chevron-right', 'ico-16') ?></a>
            <a class="liste-nav-lien" href="/services/mon-stock.php"><?= ico('package') ?>Mon stock<?= ico('chevron-right', 'ico-16') ?></a>
          </nav>
        </section>
      </aside>
    </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
