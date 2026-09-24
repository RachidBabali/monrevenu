<?php
/**
 * admin/audit.php : section Audit de l'administration (rôle admin seulement).
 * Onglets : Vue d'ensemble, Journal, Argent, Sécurité, Santé, Base de données, Stockage, Dépendances.
 * La consultation et l'export sont eux-mêmes journalisés. Aucune action ne modifie le journal.
 */
require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/../includs/journal_erreurs.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/../includs/audit.php';
require_once __DIR__ . '/../includs/sante.php';
require_once __DIR__ . '/../includs/rapprochement.php';
require_once __DIR__ . '/../includs/affiliation_helpers.php';
require_once __DIR__ . '/../includs/ui.php';

$admin = requireRole($pdo, 'admin');
$message = '';
$error = '';

$onglets = [
    'vue'          => ['Vue d\'ensemble', 'layout-dashboard'],
    'journal'      => ['Journal', 'file-text'],
    'argent'       => ['Argent', 'banknote'],
    'securite'     => ['Sécurité', 'shield'],
    'sante'        => ['Santé', 'circle-check'],
    'base'         => ['Base de données', 'package'],
    'stockage'     => ['Stockage', 'image'],
    'dependances'  => ['Dépendances', 'settings'],
    'erreurs'      => ['Erreurs', 'circle-alert'],
];
$onglet = isset($onglets[$_GET['onglet'] ?? '']) ? $_GET['onglet'] : 'vue';

// ---------------------------------------------------------------- Filtres du journal
$filtres = [
    'du'       => trim((string) ($_GET['du'] ?? '')),
    'au'       => trim((string) ($_GET['au'] ?? '')),
    'acteur'   => (int) ($_GET['acteur'] ?? 0),
    'role'     => trim((string) ($_GET['role'] ?? '')),
    'categorie' => trim((string) ($_GET['categorie'] ?? '')),
    'action'   => trim((string) ($_GET['action'] ?? '')),
    'entite'   => trim((string) ($_GET['entite'] ?? '')),
    'entite_id' => trim((string) ($_GET['entite_id'] ?? '')),
    'resultat' => trim((string) ($_GET['resultat'] ?? '')),
    'ip'       => trim((string) ($_GET['ip'] ?? '')),
];

/** Construit la clause WHERE du journal a partir des filtres. */
function conditionsJournal(array $f): array
{
    $ou = [];
    $p = [];
    if ($f['du'] !== '')        { $ou[] = 'occurred_at >= ?'; $p[] = $f['du'] . ' 00:00:00'; }
    if ($f['au'] !== '')        { $ou[] = 'occurred_at <= ?'; $p[] = $f['au'] . ' 23:59:59'; }
    if ($f['acteur'] > 0)       { $ou[] = 'actor_id = ?'; $p[] = $f['acteur']; }
    if ($f['role'] !== '')      { $ou[] = 'actor_role = ?'; $p[] = $f['role']; }
    if ($f['categorie'] !== '') { $ou[] = 'category = ?'; $p[] = $f['categorie']; }
    if ($f['action'] !== '')    { $ou[] = 'action LIKE ?'; $p[] = '%' . $f['action'] . '%'; }
    if ($f['entite'] !== '')    { $ou[] = 'entity_type = ?'; $p[] = $f['entite']; }
    if ($f['entite_id'] !== '') { $ou[] = 'entity_id = ?'; $p[] = $f['entite_id']; }
    if ($f['resultat'] !== '')  { $ou[] = 'result = ?'; $p[] = $f['resultat']; }
    if ($f['ip'] !== '')        { $ou[] = '(ip LIKE ? OR ip_prefixe LIKE ?)'; $p[] = $f['ip'] . '%'; $p[] = $f['ip'] . '%'; }
    return [$ou ? implode(' AND ', $ou) : '1 = 1', $p];
}

// ---------------------------------------------------------------- Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'admin_audit');
        http_response_code(403);
        die('Action non autorisée (CSRF).');
    }
    $action = (string) ($_POST['action_audit'] ?? '');

    if ($action === 'verifier_chaine') {
        $debut = microtime(true);
        $r = auditVerifierChaine($pdo);
        $duree = (int) round((microtime(true) - $debut) * 1000);
        auditInfo($pdo, ['category' => 'admin', 'action' => 'audit_verification_chaine', 'result' => $r['ok'] ? 'ok' : 'echec',
            'meta' => ['lignes' => $r['lignes'], 'rupture' => $r['rupture'], 'duree_ms' => $duree]]);
        $_SESSION['flash_message'] = $r['ok']
            ? 'Chaîne vérifiée : ' . number_format($r['lignes'], 0, ',', ' ') . ' lignes conformes (' . $duree . ' ms).'
            : '';
        $_SESSION['flash_error'] = $r['ok'] ? ''
            : 'Rupture détectée à la ligne ' . $r['rupture'] . ' : ' . $r['raison'] . '. Ne supprimez rien, prévenez le responsable technique.';
        header('Location: /admin/audit.php?onglet=' . urlencode($_POST['onglet'] ?? 'vue'));
        exit();
    }

    if ($action === 'exporter') {
        [$where, $params] = conditionsJournal($filtres + ['du' => $_POST['du'] ?? '', 'au' => $_POST['au'] ?? '']);
        $limite = 10000;
        auditInfo($pdo, ['category' => 'admin', 'action' => 'audit_export', 'meta' => ['filtres' => array_filter($filtres), 'limite' => $limite]]);
        $st = $pdo->prepare(
            "SELECT id, occurred_at, request_id, actor_id, actor_role, category, action, entity_type, entity_id,
                    result, route, ip_prefixe, before_json, after_json, meta_json
             FROM audit_log WHERE $where ORDER BY id DESC LIMIT $limite"
        );
        $st->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="journal-audit-' . date('Y-m-d-His') . '.csv"');
        $sortie = fopen('php://output', 'w');
        fwrite($sortie, "\xEF\xBB\xBF"); // BOM : Excel lit correctement les accents
        fputcsv($sortie, ['id', 'date', 'requete', 'acteur', 'role', 'categorie', 'action', 'entite', 'entite_id',
            'resultat', 'page', 'ip_tronquee', 'avant', 'apres', 'details'], ';', '"', '');
        while ($ligne = $st->fetch(PDO::FETCH_NUM)) fputcsv($sortie, $ligne, ';', '"', '');
        fclose($sortie);
        exit();
    }

    header('Location: /admin/audit.php');
    exit();
}

$message = $_SESSION['flash_message'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// Consultation journalisee (une ligne par onglet ouvert, sans detail des filtres vides)
auditInfo($pdo, ['category' => 'admin', 'action' => 'audit_consultation', 'meta' => ['onglet' => $onglet] + array_filter($filtres)]);

$compteurs_admin = [
    'commercants' => (int) $pdo->query("SELECT COUNT(*) FROM commercants_profils WHERE statut = 'en_attente'")->fetchColumn(),
    'produits' => (int) $pdo->query("SELECT COUNT(*) FROM vendeur_produits WHERE moderation = 'en_attente'")->fetchColumn(),
];
$titre_page = 'Audit';
$page_admin = 'audit';
include __DIR__ . '/sections/coquille_debut.php';
?>
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="page-titre">Audit</h2>
        <p class="meta mt-1">Tout ce qui se passe dans l'application, en lecture seule. Le journal ne peut pas être modifié depuis cette page.</p>
      </div>
      <form method="POST" action="/admin/audit.php" class="shrink-0">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="onglet" value="<?= e($onglet) ?>">
        <button type="submit" name="action_audit" value="verifier_chaine" class="btn btn-sm btn-secondaire"><?= ico('shield', 'ico-16') ?>Vérifier l'intégrité du journal</button>
      </form>
    </div>

    <nav class="onglets" aria-label="Sections de l'audit">
      <?php foreach ($onglets as $cle => [$libelle, $icone]): ?>
        <a class="onglet" href="?onglet=<?= e($cle) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
      <?php endforeach; ?>
    </nav>

<?php include __DIR__ . '/sections/audit_' . $onglet . '.php'; ?>
<?php include __DIR__ . '/sections/coquille_fin.php'; ?>
