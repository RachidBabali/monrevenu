<?php
/** Onglet Journal : filtres, pagination par curseur sur l'identifiant, detail d'une ligne, export CSV. */
[$where, $params] = conditionsJournal($filtres);
$avant = (int) ($_GET['avant'] ?? 0); // curseur : identifiants strictement inferieurs
$parPage = 50;
$sql = "SELECT * FROM audit_log WHERE $where" . ($avant > 0 ? ' AND id < ' . $avant : '') . " ORDER BY id DESC LIMIT " . ($parPage + 1);
$st = $pdo->prepare($sql);
$st->execute($params);
$lignes = $st->fetchAll(PDO::FETCH_ASSOC);
$suite = count($lignes) > $parPage;
if ($suite) array_pop($lignes);

$categories = $pdo->query("SELECT DISTINCT category FROM audit_log ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$entites = $pdo->query("SELECT DISTINCT entity_type FROM audit_log WHERE entity_type IS NOT NULL ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
$roles = $pdo->query("SELECT DISTINCT actor_role FROM audit_log WHERE actor_role IS NOT NULL ORDER BY actor_role")->fetchAll(PDO::FETCH_COLUMN);

/** Rend un contenu JSON du journal en liste lisible. */
function lignesJson(?string $json): string
{
    if ($json === null || $json === '') return '';
    $donnees = json_decode($json, true);
    if (!is_array($donnees)) return e(mb_substr($json, 0, 300));
    $sortie = [];
    foreach ($donnees as $cle => $valeur) {
        if (is_array($valeur)) $valeur = json_encode($valeur, JSON_UNESCAPED_UNICODE);
        $sortie[] = '<span class="text-text-3">' . e((string) $cle) . '</span> ' . e(mb_substr((string) $valeur, 0, 160));
    }
    return implode('<br>', $sortie);
}
?>
    <form method="GET" action="/admin/audit.php" class="carte flex flex-col gap-3 p-4">
      <input type="hidden" name="onglet" value="journal">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="champ"><label class="champ-label" for="f-du">Du</label>
          <input class="champ-saisie" type="date" id="f-du" name="du" value="<?= e($filtres['du']) ?>"></div>
        <div class="champ"><label class="champ-label" for="f-au">Au</label>
          <input class="champ-saisie" type="date" id="f-au" name="au" value="<?= e($filtres['au']) ?>"></div>
        <div class="champ"><label class="champ-label" for="f-acteur">Acteur (identifiant)</label>
          <input class="champ-saisie chiffres" type="number" id="f-acteur" name="acteur" min="0" value="<?= $filtres['acteur'] ?: '' ?>"></div>
        <div class="champ"><label class="champ-label" for="f-role">Rôle</label>
          <select class="champ-saisie" id="f-role" name="role"><option value="">Tous</option>
            <?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"<?= $filtres['role'] === $r ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select></div>
        <div class="champ"><label class="champ-label" for="f-categorie">Catégorie</label>
          <select class="champ-saisie" id="f-categorie" name="categorie"><option value="">Toutes</option>
            <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"<?= $filtres['categorie'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
          </select></div>
        <div class="champ"><label class="champ-label" for="f-action">Action contient</label>
          <input class="champ-saisie" type="text" id="f-action" name="action" maxlength="60" value="<?= e($filtres['action']) ?>"></div>
        <div class="champ"><label class="champ-label" for="f-entite">Entité</label>
          <select class="champ-saisie" id="f-entite" name="entite"><option value="">Toutes</option>
            <?php foreach ($entites as $en): ?><option value="<?= e($en) ?>"<?= $filtres['entite'] === $en ? ' selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?>
          </select></div>
        <div class="champ"><label class="champ-label" for="f-entite-id">Identifiant de l'entité</label>
          <input class="champ-saisie" type="text" id="f-entite-id" name="entite_id" maxlength="64" value="<?= e($filtres['entite_id']) ?>"></div>
        <div class="champ"><label class="champ-label" for="f-resultat">Résultat</label>
          <select class="champ-saisie" id="f-resultat" name="resultat"><option value="">Tous</option>
            <?php foreach (['ok' => 'Réussite', 'refus' => 'Refus', 'echec' => 'Échec'] as $v => $l): ?>
              <option value="<?= e($v) ?>"<?= $filtres['resultat'] === $v ? ' selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="champ"><label class="champ-label" for="f-ip">Adresse IP commence par</label>
          <input class="champ-saisie chiffres" type="text" id="f-ip" name="ip" maxlength="45" value="<?= e($filtres['ip']) ?>"></div>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn btn-sm btn-primaire"><?= ico('search', 'ico-16') ?>Filtrer</button>
        <a class="btn btn-sm btn-discret" href="/admin/audit.php?onglet=journal">Réinitialiser</a>
      </div>
    </form>

    <form method="POST" action="/admin/audit.php" class="flex flex-wrap items-center gap-2">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <?php foreach ($filtres as $cle => $valeur): if ($valeur !== '' && $valeur !== 0): ?>
        <input type="hidden" name="<?= e($cle) ?>" value="<?= e((string) $valeur) ?>">
      <?php endif; endforeach; ?>
      <button type="submit" name="action_audit" value="exporter" class="btn btn-sm btn-secondaire"><?= ico('download', 'ico-16') ?>Exporter en CSV (10 000 lignes au plus)</button>
      <span class="meta">L'export est journalisé.</span>
    </form>

    <div class="carte overflow-hidden">
      <?php if (!$lignes): ?>
        <div class="vide">
          <?= ico('file-text', 'ico-40') ?>
          <p class="vide-titre">Aucune ligne pour ces filtres</p>
          <p class="vide-texte">Élargissez la période ou retirez un filtre.</p>
        </div>
      <?php else: ?>
        <table class="tableau tableau-empile">
          <thead><tr>
            <th scope="col">Quand</th><th scope="col">Acteur</th><th scope="col">Action</th>
            <th scope="col">Entité</th><th scope="col">Résultat</th><th scope="col">Détail</th>
          </tr></thead>
          <tbody>
          <?php foreach ($lignes as $l): ?>
            <tr>
              <td data-label="Quand" class="chiffres whitespace-nowrap text-text-2"><?= e(substr($l['occurred_at'], 0, 19)) ?></td>
              <td data-label="Acteur"><?= $l['actor_id'] ? '<span class="chiffres">#' . (int) $l['actor_id'] . '</span> ' : '<span class="text-text-3">anonyme</span> ' ?><span class="text-text-3"><?= e($l['actor_role'] ?? '') ?></span></td>
              <td data-label="Action"><span class="font-medium"><?= e($l['action']) ?></span><br><span class="meta"><?= e($l['category']) ?></span></td>
              <td data-label="Entité" class="text-text-2"><?= $l['entity_type'] ? e($l['entity_type']) . ' <span class="chiffres">' . e((string) $l['entity_id']) . '</span>' : '' ?></td>
              <td data-label="Résultat"><span class="pastille pastille-<?= $l['result'] === 'ok' ? 'succes' : ($l['result'] === 'refus' ? 'attente' : 'danger') ?>"><?= e($l['result']) ?></span></td>
              <td data-label="Détail">
                <details class="accordeon">
                  <summary class="cursor-pointer text-sm text-primary-ink">Voir<?= ico('chevron-down', 'ico-16') ?></summary>
                  <div class="flex flex-col gap-2 pb-3 text-sm">
                    <p class="meta">Ligne <?= (int) $l['id'] ?>, requête <?= e($l['request_id']) ?>, page <?= e($l['route'] ?? '') ?>, IP <?= e($l['ip_prefixe'] ?? 'inconnue') ?></p>
                    <?php if ($l['before_json']): ?><div><p class="meta">Avant</p><?= lignesJson($l['before_json']) ?></div><?php endif; ?>
                    <?php if ($l['after_json']): ?><div><p class="meta">Après</p><?= lignesJson($l['after_json']) ?></div><?php endif; ?>
                    <?php if ($l['meta_json']): ?><div><p class="meta">Détails</p><?= lignesJson($l['meta_json']) ?></div><?php endif; ?>
                    <p class="meta break-all">Empreinte <?= e(substr($l['row_hash'], 0, 16)) ?> (16 premiers caractères)</p>
                  </div>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <?php if ($suite && $lignes): ?>
      <?php $params_suite = array_filter($filtres, fn($v) => $v !== '' && $v !== 0); $params_suite['onglet'] = 'journal'; $params_suite['avant'] = (int) end($lignes)['id']; ?>
      <a class="btn btn-secondaire self-start" href="?<?= e(http_build_query($params_suite)) ?>"><?= ico('arrow-right', 'ico-16') ?>Lignes plus anciennes</a>
    <?php endif; ?>
