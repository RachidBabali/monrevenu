<?php
/**
 * admin/commande_calcul.php : acces support. Detail exact du calcul fige d'une commande (bareme, taux,
 * repartition, prix net) pour repondre a un affilie qui conteste un gain. Reserve a l'administrateur ;
 * la consultation est journalisee. Ce detail n'existe nulle part cote affilie.
 */
require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/../includs/audit.php';
require_once __DIR__ . '/../includs/commercant.php';
require_once __DIR__ . '/../includs/ui.php';

$admin = requireRole($pdo, 'admin');
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT v.*, p.nom_produit, u.fullname AS affilie FROM vendeur_ventes v
    LEFT JOIN vendeur_produits p ON p.id = v.produit_id LEFT JOIN users_monrevenu u ON u.id = v.vendeur_id WHERE v.id = ?");
$st->execute([$id]);
$v = $st->fetch(PDO::FETCH_ASSOC);
$snap = $v && $v['calcul_snapshot'] ? json_decode($v['calcul_snapshot'], true) : null;
if ($v) {
    auditInfo($pdo, ['category' => 'admin', 'action' => 'calcul_commande_consulte', 'entity_type' => 'commande', 'entity_id' => $id]);
}

$compteurs_admin = [
    'commercants' => (int) $pdo->query("SELECT COUNT(*) FROM commercants_profils WHERE statut = 'en_attente'")->fetchColumn(),
    'produits' => (int) $pdo->query("SELECT COUNT(*) FROM vendeur_produits WHERE moderation = 'en_attente'")->fetchColumn(),
];
$titre_page = 'Calcul de commande';
$page_admin = 'commissions';
include __DIR__ . '/sections/coquille_debut.php';
$dev = $v ? ($v['devise'] === 'KMF' ? 'KMF' : 'FCFA') : '';
?>
    <div><h2 class="page-titre">Calcul de la commande n° <?= (int) $id ?></h2>
      <p class="meta mt-1"><a href="/admin/commissions.php">← Commissions</a></p></div>
<?php if (!$v): ?>
    <p class="carte p-4">Commande introuvable.</p>
<?php elseif (!$snap): ?>
    <div class="carte p-4">
      <p>Cette commande date d'avant le calcul par tranches : aucun détail figé.</p>
      <p class="meta mt-1">Produit <?= e($v['nom_produit']) ?> · affilié <?= e($v['affilie']) ?> · gain enregistré <?= e(formaterMontant((float) $v['commission_earn'])) ?></p>
    </div>
<?php else: $r = $snap['resultat']; $c = $snap['config']; ?>
    <div class="carte flex flex-col gap-3 p-4">
      <p class="meta">Produit <?= e($v['nom_produit']) ?> · affilié <?= e($v['affilie']) ?> · quantité <?= (int) $v['quantite'] ?> · calcul figé le <?= e($snap['calcule_le']) ?></p>
      <dl class="recap bg-surface">
        <div class="recap-ligne"><dt>Prix net commerçant (unitaire)</dt><dd class="montant"><?= e(number_format($r['prix_net'], 0, ',', ' ')) ?> <?= e($dev) ?></dd></div>
        <div class="recap-ligne"><dt>Supplément brut</dt><dd class="montant"><?= e(number_format($r['supplement_brut'], 2, ',', ' ')) ?></dd></div>
        <div class="recap-ligne"><dt>Supplément retenu (plancher <?= e((string) $c['min']) ?>, plafond <?= e((string) $c['max']) ?>, arrondi <?= (int) $c['arrondi'] ?>)</dt><dd class="montant"><?= e(number_format($r['supplement'], 0, ',', ' ')) ?></dd></div>
        <div class="recap-ligne"><dt>Prix affiché au client</dt><dd class="montant"><?= e(number_format($r['prix_final'], 0, ',', ' ')) ?></dd></div>
        <div class="recap-ligne"><dt>Répartition (affilié / plateforme)</dt><dd><?= e((string) $c['part_affilie']) ?> % / <?= e((string) $c['part_plateforme']) ?> %</dd></div>
        <div class="recap-ligne"><dt>Gain affilié (unitaire)</dt><dd class="montant"><?= e(number_format($r['gain_affilie'], 0, ',', ' ')) ?></dd></div>
        <div class="recap-ligne"><dt>Part plateforme (unitaire)</dt><dd class="montant"><?= e(number_format($r['part_plateforme'], 0, ',', ' ')) ?></dd></div>
      </dl>
      <table class="tableau"><caption class="sr-only">Détail par tranche</caption>
        <thead><tr><th scope="col">Tranche</th><th scope="col">Taux</th><th scope="col" class="col-montant">Assiette</th><th scope="col" class="col-montant">Montant</th></tr></thead>
        <tbody><?php foreach ($r['detail_tranches'] as $t): ?>
          <tr><td><?= e(number_format($t['min'], 0, ',', ' ')) ?> → <?= $t['max'] === null ? '∞' : e(number_format($t['max'], 0, ',', ' ')) ?></td><td><?= e((string) $t['taux']) ?> %</td>
              <td class="col-montant"><?= e(number_format($t['assiette'], 0, ',', ' ')) ?></td><td class="col-montant"><?= e(number_format($t['montant'], 2, ',', ' ')) ?></td></tr>
        <?php endforeach; ?></tbody></table>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/sections/coquille_fin.php'; ?>
