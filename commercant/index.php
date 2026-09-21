<?php
/**
 * commercant/index.php : tableau de bord du commercant.
 * Produits publies, en attente ou refuses, commandes a traiter, commissions dues a la plateforme.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/audit.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/commercant.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';

$profil = exigerCommercant($pdo);
$id = (int) $profil['user_id'];
$marche_commercant = $profil['marche'];
$regle_commission = marche($marche_commercant)['commission'];

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$compteurs = ['publies' => 0, 'attente' => 0, 'refuses' => 0, 'brouillons' => 0];
$a_traiter = 0;
$dernieres = [];
$dette = ['commissions' => '0', 'reglements' => '0', 'solde' => '0.00'];
try {
    $st = $pdo->prepare(
        "SELECT
            COUNT(CASE WHEN moderation = 'approuve' AND statut = 'actif' THEN 1 END) AS publies,
            COUNT(CASE WHEN moderation = 'en_attente' THEN 1 END) AS attente,
            COUNT(CASE WHEN moderation = 'refuse' THEN 1 END) AS refuses,
            COUNT(CASE WHEN moderation = 'brouillon' THEN 1 END) AS brouillons
         FROM vendeur_produits WHERE vendeur_id = ?"
    );
    $st->execute([$id]);
    $compteurs = array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: $compteurs);

    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM vendeur_ventes v JOIN vendeur_produits p ON p.id = v.produit_id
         WHERE p.vendeur_id = ? AND v.statut IN ('en_attente', 'contacte', 'colis_recu')"
    );
    $st->execute([$id]);
    $a_traiter = (int) $st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT v.id, v.quantite, v.prix_unitaire, v.statut, v.created_at, p.nom_produit
         FROM vendeur_ventes v JOIN vendeur_produits p ON p.id = v.produit_id
         WHERE p.vendeur_id = ? ORDER BY v.created_at DESC, v.id DESC LIMIT 5"
    );
    $st->execute([$id]);
    $dernieres = $st->fetchAll(PDO::FETCH_ASSOC);

    $dette = detteCommercant($pdo, $id);
} catch (PDOException $e) {
    error_log('[commercant/index] ' . $e->getMessage());
    $message_error = "Certaines informations n'ont pas pu être chargées. Actualisez la page.";
}

$titre_page = 'Tableau de bord';
$actions_entete = commercantPeutPublier($profil)
    ? '<a class="btn btn-sm btn-primaire" href="/commercant/produit.php">' . ico('plus', 'ico-16') . '<span class="hidden sm:inline">Ajouter un produit</span><span class="sm:hidden">Ajouter</span></a>'
    : '';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>
    <div>
      <h2 class="page-titre"><?= e($profil['nom_boutique']) ?></h2>
      <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-text-2">
        <?= badgeStatut($profil['statut'], 'boutique') ?>
        <?php if ($profil['ville']): ?><span><?= e($profil['ville']) ?></span><?php endif; ?>
      </p>
    </div>

    <?php if ($profil['statut'] === 'en_attente'): ?>
      <p class="alerte alerte-info"><?= ico('info') ?><span>Votre boutique est en cours de vérification par l'équipe MonRevenu. Vous pouvez préparer vos produits en brouillon dès maintenant ; vous pourrez les envoyer pour publication une fois la boutique validée.</span></p>
    <?php elseif ($profil['statut'] === 'refuse' || $profil['statut'] === 'suspendu'): ?>
      <p class="alerte alerte-danger" role="alert"><?= ico('circle-alert') ?><span>
        <?= $profil['statut'] === 'refuse' ? 'Votre boutique n\'a pas été validée.' : 'Votre boutique est suspendue : vos produits ne sont plus visibles dans le catalogue.' ?>
        <?php if ($profil['motif']): ?>Motif : <?= e($profil['motif']) ?>.<?php endif; ?>
        Pour en parler, écrivez à contact@monrevenu.xyz.
      </span></p>
    <?php endif; ?>

    <section class="flex flex-col gap-3" aria-labelledby="t-apercu">
      <h2 id="t-apercu" class="section-titre">Aperçu</h2>
      <dl class="indicateurs">
        <div class="indicateur">
          <dt class="indicateur-libelle">Produits publiés</dt>
          <dd class="indicateur-valeur"><?= $compteurs['publies'] ?></dd>
          <dd class="indicateur-note"><?= $compteurs['brouillons'] ?> brouillon<?= $compteurs['brouillons'] > 1 ? 's' : '' ?></dd>
        </div>
        <div class="indicateur">
          <dt class="indicateur-libelle">À valider ou refusés</dt>
          <dd class="indicateur-valeur"><?= $compteurs['attente'] + $compteurs['refuses'] ?></dd>
          <dd class="indicateur-note"><?= $compteurs['attente'] ?> en attente, <?= $compteurs['refuses'] ?> refusé<?= $compteurs['refuses'] > 1 ? 's' : '' ?></dd>
        </div>
        <div class="indicateur">
          <dt class="indicateur-libelle">Commandes à traiter</dt>
          <dd class="indicateur-valeur"><?= $a_traiter ?></dd>
          <dd class="indicateur-note">Nouvelles, clients contactés ou colis reçus</dd>
        </div>
        <div class="indicateur">
          <dt class="indicateur-libelle">Commissions dues</dt>
          <dd class="indicateur-valeur"><?= formaterMontant($dette['solde']) ?></dd>
          <dd class="indicateur-note">Ventes validées moins vos règlements</dd>
        </div>
      </dl>
    </section>

    <section class="flex min-w-0 flex-col gap-3" aria-labelledby="t-commandes">
      <div class="flex items-end justify-between gap-3">
        <h2 id="t-commandes" class="section-titre">Dernières commandes</h2>
        <a class="lien cible text-sm" href="/commercant/commandes.php">Toutes les commandes</a>
      </div>
      <div class="carte overflow-hidden">
        <?php if (!$dernieres): ?>
          <div class="vide">
            <?= ico('shopping-cart', 'ico-40') ?>
            <p class="vide-titre">Aucune commande pour l'instant</p>
            <p class="vide-texte">Quand un client commande l'un de vos produits depuis le lien d'un affilié, la commande apparaît ici avec ses coordonnées.</p>
          </div>
        <?php else: ?>
          <table class="tableau tableau-empile">
            <thead><tr><th scope="col">Produit</th><th scope="col">Date</th><th scope="col">Statut</th><th scope="col" class="col-montant">Montant</th></tr></thead>
            <tbody>
            <?php foreach ($dernieres as $c): ?>
              <tr>
                <td data-label="" class="font-medium"><span><?= e($c['nom_produit']) ?><?= (int) $c['quantite'] > 1 ? ' <span class="font-normal text-text-3">x' . (int) $c['quantite'] . '</span>' : '' ?></span></td>
                <td data-label="Date" class="chiffres whitespace-nowrap text-text-2"><?= e(dateFr($c['created_at'], 'court')) ?></td>
                <td data-label="Statut"><?= badgeStatut($c['statut'], 'commande') ?></td>
                <td data-label="Montant" class="col-montant"><?= montant((float) $c['prix_unitaire'] * (int) $c['quantite']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/sections/activer_notifications.php'; ?>

    <section class="carte flex flex-col gap-2 p-4" aria-labelledby="t-fonctionnement">
      <h2 id="t-fonctionnement" class="section-titre">Fonctionnement</h2>
      <ul class="flex list-disc flex-col gap-1 pl-5 text-sm text-text-2">
        <li>Chaque produit est vérifié par MonRevenu avant d'apparaître dans le catalogue des affiliés.</li>
        <li>Vous traitez vos commandes jusqu'à la réception du colis ; MonRevenu valide ensuite la vente et paie l'affilié.</li>
        <li>La commission de l'affilié (<?= formaterMontant($regle_commission['basse'], false, true, $marche_commercant) ?> par article jusqu'à <?= formaterMontant($regle_commission['seuil'], false, true, $marche_commercant) ?>, <?= formaterMontant($regle_commission['haute'], false, true, $marche_commercant) ?> au-delà) est due à MonRevenu pour chaque vente validée.</li>
      </ul>
    </section>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
