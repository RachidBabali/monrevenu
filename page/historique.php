<?php
session_start();
// Connexion à la base de données
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';

// Vérification de la session
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /index.php'); exit();
}

$user_id       = $_SESSION['user_id'];
$user_fullname = $_SESSION['user_fullname'] ?? 'Utilisateur';

try {
    // Récupérer la liste des transactions (table réelle : transactions_monrevenu)
    $query = $pdo->prepare("SELECT * FROM transactions_monrevenu WHERE user_id = ? ORDER BY created_at DESC");
    $query->execute([$user_id]);
    $transactions = $query->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
}

/* Filtres d'affichage appliques a la liste deja chargee. */
$filtres = ['tout' => 'Tout', 'commission' => 'Commissions', 'retrait' => 'Retraits', 'autre' => 'Autres'];
$filtre = $_GET['type'] ?? 'tout';
if (!isset($filtres[$filtre])) {
    $filtre = 'tout';
}
$recherche = trim((string) ($_GET['q'] ?? ''));
$credits = ['depot', 'commission', 'jeu_gain'];
$libelles_type = [
    'depot' => 'Crédit', 'retrait' => 'Retrait', 'commission' => 'Commission',
    'achat_service' => 'Achat', 'jeu_gain' => 'Gain', 'jeu_perte' => 'Débit',
];
$icones_type = ['commission' => 'hand-coins', 'retrait' => 'banknote', 'depot' => 'arrow-down-left', 'jeu_gain' => 'arrow-down-left'];

$liste = array_values(array_filter($transactions, static function ($tx) use ($filtre, $recherche) {
    $type = strtolower((string) $tx['type']);
    if ($filtre === 'commission' && $type !== 'commission') return false;
    if ($filtre === 'retrait' && $type !== 'retrait') return false;
    if ($filtre === 'autre' && in_array($type, ['commission', 'retrait'], true)) return false;
    if ($recherche !== '') {
        $botte = mb_strtolower(($tx['description'] ?? '') . ' ' . ($tx['reference'] ?? ''));
        if (!str_contains($botte, mb_strtolower($recherche))) return false;
    }
    return true;
}));
$par_jour = [];
foreach ($liste as $tx) {
    $par_jour[substr((string) $tx['created_at'], 0, 10)][] = $tx;
}

$titre_page = 'Historique';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
$lien = static fn(array $params) => '?' . http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== 'tout'));
?>

    <div class="flex flex-col gap-3">
      <nav class="onglets" aria-label="Type d'opération">
        <?php foreach ($filtres as $cle => $libelle): ?>
          <a class="onglet" href="<?= e($lien(['type' => $cle, 'q' => $recherche]) ?: '?') ?>"<?= $filtre === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
        <?php endforeach; ?>
      </nav>
      <form method="GET" action="" role="search" class="flex gap-2">
        <?php if ($filtre !== 'tout'): ?><input type="hidden" name="type" value="<?= e($filtre) ?>"><?php endif; ?>
        <div class="relative flex-1">
          <label class="sr-only" for="recherche-historique">Rechercher une opération</label>
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-3"><?= ico('search') ?></span>
          <input class="champ-saisie pl-10" type="search" id="recherche-historique" name="q" value="<?= e($recherche) ?>" placeholder="Référence ou libellé" autocomplete="off" enterkeyhint="search">
        </div>
        <button class="btn btn-secondaire" type="submit">Rechercher</button>
      </form>
    </div>

    <?php if (!$par_jour): ?>
      <div class="carte">
        <div class="vide">
          <?= ico('history', 'ico-40') ?>
          <?php if ($recherche !== '' || $filtre !== 'tout'): ?>
            <p class="vide-titre">Aucune opération ne correspond</p>
            <p class="vide-texte">Changez de filtre ou effacez la recherche.</p>
            <a class="btn btn-sm btn-secondaire mt-2" href="/page/historique.php">Tout afficher</a>
          <?php else: ?>
            <p class="vide-titre">Aucune opération pour l'instant</p>
            <p class="vide-texte">Vos commissions et vos retraits apparaîtront ici.</p>
            <a class="btn btn-sm btn-primaire mt-2" href="/services/boutique.php">Choisir un produit</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <p class="text-sm text-text-2"><span class="chiffres"><?= count($liste) ?></span> opération<?= count($liste) > 1 ? 's' : '' ?></p>
      <div class="flex flex-col gap-4">
        <?php foreach ($par_jour as $jour => $operations): ?>
          <section class="carte overflow-hidden" aria-label="<?= e(dateFr($jour, 'jour')) ?>">
            <h2 class="border-b border-line bg-surface-2 px-4 py-2 text-xs font-medium text-text-3"><?= e(dateFr($jour, 'jour')) ?></h2>
            <?php foreach ($operations as $tx):
              $type    = strtolower((string) $tx['type']);
              $credit  = in_array($type, $credits, true);
              $echoue  = ($tx['status'] ?? '') === 'echoue';
              $classe  = $echoue ? 'text-text-3 line-through' : ($credit ? 'montant-entrant' : '');
              $libelle = nettoyerPictogrammes($tx['description'] ?? '') ?: ($libelles_type[$type] ?? ucfirst($type));
            ?>
              <div class="ligne-tx">
                <span class="ligne-tx-icone"><?= ico($icones_type[$type] ?? ($credit ? 'arrow-down-left' : 'arrow-up-right')) ?></span>
                <div class="ligne-tx-corps">
                  <p class="ligne-tx-titre"><?= e($libelle) ?></p>
                  <p class="ligne-tx-meta">
                    <span><?= e($libelles_type[$type] ?? ucfirst($type)) ?></span>
                    <span class="chiffres"><?= e(dateFr($tx['created_at'], 'heure_seule')) ?></span>
                    <?php if (!empty($tx['reference'])): ?><span class="font-mono"><?= e($tx['reference']) ?></span><?php endif; ?>
                  </p>
                </div>
                <div class="ligne-tx-montant">
                  <?= montant($credit ? (float) $tx['amount'] : -abs((float) $tx['amount']), $credit, $classe) ?>
                  <div class="mt-1"><?= badgeStatut($tx['status'] ?? '', 'transaction') ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
