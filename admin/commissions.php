<?php
/**
 * admin/commissions.php : bareme du supplement par tranches, repartition affilie / plateforme, plancher,
 * plafond et arrondi, par marche. Historique de chaque modification (qui, quand, avant / apres).
 * Reserve a l'administrateur : ces donnees ne sortent jamais vers l'espace affilie.
 */
require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/../includs/audit.php';
require_once __DIR__ . '/../includs/incident.php';
require_once __DIR__ . '/../includs/commercant.php';
require_once __DIR__ . '/../includs/commission_admin.php';
require_once __DIR__ . '/../includs/ui.php';

$admin = requireRole($pdo, 'admin');
$marche = marcheValide($_GET['marche'] ?? $_POST['marche'] ?? '') ?? 'SN';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'admin_commissions');
        http_response_code(403);
        die('Action non autorisée (CSRF).');
    }
    try {
        $n = commissionSauvegarder($pdo, $marche, commissionValiderSaisie($_POST), (int) $admin['id']);
        $_SESSION['flash_message'] = "Barème enregistré. Prix affiché recalculé pour $n produit(s) ; les commandes déjà passées ne changent pas.";
    } catch (InvalidArgumentException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (Throwable $t) {
        $_SESSION['flash_error'] = messageIncident(incidentEnregistrer($pdo, $t, 'admin/commissions'), "L'enregistrement a échoué. Réessayez dans un instant.");
    }
    header('Location: /admin/commissions.php?marche=' . urlencode($marche));
    exit();
}

$message = $_SESSION['flash_message'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$cfg = commissionConfig($pdo, $marche);
$devise = deviseLibelle($marche);
$st = $pdo->prepare("SELECT h.*, u.fullname FROM commission_history h LEFT JOIN users_monrevenu u ON u.id = h.admin_id WHERE h.marche = ? ORDER BY h.id DESC LIMIT 30");
$st->execute([$marche]);
$historique = $st->fetchAll(PDO::FETCH_ASSOC);

$compteurs_admin = [
    'commercants' => (int) $pdo->query("SELECT COUNT(*) FROM commercants_profils WHERE statut = 'en_attente'")->fetchColumn(),
    'produits' => (int) $pdo->query("SELECT COUNT(*) FROM vendeur_produits WHERE moderation = 'en_attente'")->fetchColumn(),
];
$titre_page = 'Commissions';
$page_admin = 'commissions';
include __DIR__ . '/sections/coquille_debut.php';

$lignes = $cfg['brackets'];
$lignes[] = ['min' => '', 'max' => '', 'taux' => ''];
$lignes[] = ['min' => '', 'max' => '', 'taux' => ''];
?>
    <div>
      <h2 class="page-titre">Commissions</h2>
      <p class="meta mt-1">Le supplément s'ajoute au prix net du commerçant. Chaque tranche est taxée à son propre taux. Un changement recalcule le prix affiché des produits ; les commandes déjà passées gardent leur calcul d'origine.</p>
      <nav class="mt-3 flex gap-2" aria-label="Marché">
        <?php foreach (marches() as $code => $m): ?>
          <a class="btn btn-sm <?= $code === $marche ? 'btn-primaire' : 'btn-secondaire' ?>" href="/admin/commissions.php?marche=<?= e($code) ?>"><?= e($m['nom']) ?> (<?= e($m['devise_libelle']) ?>)</a>
        <?php endforeach; ?>
      </nav>
    </div>

    <form method="POST" action="/admin/commissions.php" class="carte flex flex-col gap-6 p-4">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="marche" value="<?= e($marche) ?>">

      <fieldset class="flex flex-col gap-2">
        <legend class="champ-label mb-1">Barème par tranches (<?= e($devise) ?>)</legend>
        <div class="grid grid-cols-[1fr_1fr_5rem_auto] gap-2 text-sm text-text-3"><span>De</span><span>À (vide = sans limite)</span><span>Taux %</span><span>Suppr.</span></div>
        <?php foreach ($lignes as $i => $l): ?>
          <div class="grid grid-cols-[1fr_1fr_5rem_auto] items-center gap-2">
            <input class="champ-saisie chiffres" type="text" inputmode="decimal" name="tranche_min[<?= $i ?>]" value="<?= e((string) $l['min']) ?>" aria-label="Borne minimale tranche <?= $i + 1 ?>">
            <input class="champ-saisie chiffres" type="text" inputmode="decimal" name="tranche_max[<?= $i ?>]" value="<?= e((string) ($l['max'] ?? '')) ?>" aria-label="Borne maximale tranche <?= $i + 1 ?>">
            <input class="champ-saisie chiffres" type="text" inputmode="decimal" name="tranche_taux[<?= $i ?>]" value="<?= e((string) $l['taux']) ?>" aria-label="Taux tranche <?= $i + 1 ?>">
            <input class="case" type="checkbox" name="tranche_suppr[<?= $i ?>]" value="1" aria-label="Supprimer la tranche <?= $i + 1 ?>">
          </div>
        <?php endforeach; ?>
        <p class="champ-aide">Les deux lignes vides servent à ajouter des tranches. La première commence à 0, chaque tranche commence où la précédente finit, la dernière n'a pas de limite.</p>
      </fieldset>

      <fieldset class="grid gap-3 sm:grid-cols-2">
        <legend class="champ-label mb-1">Répartition du supplément</legend>
        <div class="champ"><label class="champ-label" for="pa">Affilié (%)</label><input class="champ-saisie chiffres" type="text" inputmode="decimal" id="pa" name="part_affilie" value="<?= e((string) $cfg['part_affilie']) ?>"></div>
        <div class="champ"><label class="champ-label" for="pp">Plateforme (%)</label><input class="champ-saisie chiffres" type="text" inputmode="decimal" id="pp" name="part_plateforme" value="<?= e((string) $cfg['part_plateforme']) ?>"></div>
        <p class="champ-aide sm:col-span-2">Le total doit faire 100 %. La part plateforme couvre les frais mobile money et les coûts fixes.</p>
      </fieldset>

      <fieldset class="grid gap-3 sm:grid-cols-3">
        <legend class="champ-label mb-1">Règles du supplément</legend>
        <div class="champ"><label class="champ-label" for="smin">Plancher (<?= e($devise) ?>)</label><input class="champ-saisie chiffres" type="text" inputmode="decimal" id="smin" name="supplement_min" value="<?= e((string) $cfg['min']) ?>"></div>
        <div class="champ"><label class="champ-label" for="smax">Plafond (<?= e($devise) ?>)</label><input class="champ-saisie chiffres" type="text" inputmode="decimal" id="smax" name="supplement_max" value="<?= e((string) $cfg['max']) ?>"></div>
        <div class="champ"><label class="champ-label" for="arr">Arrondi au multiple supérieur de</label>
          <select class="champ-saisie" id="arr" name="arrondi"><?php foreach ([1, 50, 100] as $a): ?><option value="<?= $a ?>"<?= $cfg['arrondi'] === $a ? ' selected' : '' ?>><?= $a ?></option><?php endforeach; ?></select></div>
      </fieldset>

      <button type="submit" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Enregistrer</span></button>
    </form>

    <form method="GET" action="/admin/commande_calcul.php" class="carte flex flex-wrap items-end gap-3 p-4">
      <div class="champ"><label class="champ-label" for="cmd">Détail du calcul d'une commande (support)</label>
        <input class="champ-saisie chiffres" type="number" min="1" id="cmd" name="id" required placeholder="N° de commande"></div>
      <button type="submit" class="btn btn-secondaire">Consulter</button>
    </form>

    <div class="carte overflow-hidden">
      <h3 class="carte-entete carte-titre">Historique des modifications</h3>
      <table class="tableau">
        <thead><tr><th scope="col">Date</th><th scope="col">Administrateur</th><th scope="col">Objet</th><th scope="col">Avant</th><th scope="col">Après</th></tr></thead>
        <tbody>
        <?php foreach ($historique as $h): ?>
          <tr><td><?= e($h['created_at']) ?></td><td><?= e($h['fullname'] ?? ('#' . $h['admin_id'])) ?></td><td><?= e($h['cible']) ?></td>
              <td class="text-xs break-all"><?= e((string) $h['ancienne_valeur']) ?></td><td class="text-xs break-all"><?= e((string) $h['nouvelle_valeur']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$historique): ?><tr><td colspan="5" class="meta">Aucune modification depuis la mise en place.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
<?php include __DIR__ . '/sections/coquille_fin.php'; ?>
