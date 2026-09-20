<?php
/** Onglet Argent : recherche des mouvements, chronologie d'un utilisateur, totaux, rapprochement. */
$recherche = trim((string) ($_GET['q'] ?? ''));
$utilisateur = (int) ($_GET['utilisateur'] ?? 0);
$type = trim((string) ($_GET['type'] ?? ''));
$du = trim((string) ($_GET['du'] ?? ''));
$au = trim((string) ($_GET['au'] ?? ''));

$ou = ['1 = 1'];
$p = [];
if ($utilisateur > 0) { $ou[] = 't.user_id = ?'; $p[] = $utilisateur; }
if ($type !== '')     { $ou[] = 't.type = ?'; $p[] = $type; }
if ($du !== '')       { $ou[] = 't.created_at >= ?'; $p[] = $du . ' 00:00:00'; }
if ($au !== '')       { $ou[] = 't.created_at <= ?'; $p[] = $au . ' 23:59:59'; }
if ($recherche !== '') { $ou[] = '(t.reference LIKE ? OR u.fullname LIKE ?)'; $p[] = '%' . $recherche . '%'; $p[] = '%' . $recherche . '%'; }
$avant = (int) ($_GET['avant'] ?? 0);
if ($avant > 0) { $ou[] = 't.id < ?'; $p[] = $avant; }

$st = $pdo->prepare(
    "SELECT t.*, u.fullname FROM transactions_monrevenu t JOIN users_monrevenu u ON u.id = t.user_id
     WHERE " . implode(' AND ', $ou) . " ORDER BY t.id DESC LIMIT 51"
);
$st->execute($p);
$mouvements = $st->fetchAll(PDO::FETCH_ASSOC);
$suite = count($mouvements) > 50;
if ($suite) array_pop($mouvements);

$totaux = totauxArgent($pdo, 30);
$parType = [];
foreach ($totaux as $t) {
    $parType[$t['type']]['n'] = ($parType[$t['type']]['n'] ?? 0) + (int) $t['n'];
    $parType[$t['type']]['total'] = ($parType[$t['type']]['total'] ?? 0) + (float) $t['total'];
}
$rapprochement = rapprochementSoldes($pdo);
$anomalies = count($rapprochement['ecarts']) + count($rapprochement['negatifs']) + count($rapprochement['doublons'])
    + count($rapprochement['ventes_sans_commission']) + count($rapprochement['commissions_sans_vente'])
    + count($rapprochement['retraits_anciens']) + count($rapprochement['comptes_bloques_avec_liens']);
?>
    <section class="flex flex-col gap-3" aria-labelledby="t-rapprochement">
      <h2 id="t-rapprochement" class="section-titre">Rapprochement des soldes</h2>
      <p class="meta"><?= $rapprochement['total_comptes'] ?> compte(s) avec des mouvements. Cette page signale, elle ne corrige jamais.</p>
      <?php if ($anomalies === 0): ?>
        <div class="carte"><div class="vide">
          <?= ico('circle-check', 'ico-40') ?>
          <p class="vide-titre">Aucun écart</p>
          <p class="vide-texte">Chaque solde correspond à la somme de ses mouvements, aucune référence en double, aucune commission manquante.</p>
        </div></div>
      <?php else: ?>
        <?php
        $sections = [
            ['ecarts', 'Écarts de solde', ['user_id' => 'Compte', 'nom' => 'Nom', 'solde_reel' => 'Solde réel', 'solde_attendu' => 'Solde attendu', 'ecart' => 'Écart', 'raison' => 'Raison']],
            ['negatifs', 'Soldes négatifs', ['user_id' => 'Compte', 'nom' => 'Nom', 'solde' => 'Solde']],
            ['doublons', 'Références en double', ['reference' => 'Référence', 'n' => 'Occurrences']],
            ['ventes_sans_commission', 'Ventes validées sans commission créditée', ['id' => 'Vente', 'vendeur_id' => 'Affilié', 'commission_earn' => 'Commission', 'created_at' => 'Date']],
            ['commissions_sans_vente', 'Commissions sans vente validée', ['id' => 'Transaction', 'user_id' => 'Compte', 'amount' => 'Montant', 'reference' => 'Référence']],
            ['retraits_anciens', 'Retraits en attente depuis plus de 7 jours', ['id' => 'Retrait', 'user_id' => 'Compte', 'amount' => 'Montant', 'created_at' => 'Demandé le']],
            ['comptes_bloques_avec_liens', 'Comptes suspendus avec des ventes en cours', ['id' => 'Compte', 'fullname' => 'Nom', 'status' => 'Statut', 'ventes_en_cours' => 'Ventes en cours']],
        ];
        foreach ($sections as [$cle, $titre, $colonnes]):
            if (!$rapprochement[$cle]) continue; ?>
          <div class="carte overflow-hidden">
            <p class="carte-entete carte-titre"><?= e($titre) ?> <span class="pastille pastille-attente ml-2"><?= count($rapprochement[$cle]) ?></span></p>
            <table class="tableau tableau-empile">
              <thead><tr><?php foreach ($colonnes as $libelle): ?><th scope="col"><?= e($libelle) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
              <?php foreach ($rapprochement[$cle] as $ligne): ?>
                <tr><?php foreach ($colonnes as $champ => $libelle): ?>
                  <td data-label="<?= e($libelle) ?>"><?= e((string) ($ligne[$champ] ?? '')) ?></td>
                <?php endforeach; ?></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="flex flex-col gap-3" aria-labelledby="t-totaux">
      <h2 id="t-totaux" class="section-titre">Totaux par type (30 jours)</h2>
      <dl class="indicateurs">
        <?php foreach ($parType as $t => $v): ?>
          <div class="indicateur">
            <dt class="indicateur-libelle"><?= e(ucfirst($t)) ?></dt>
            <dd class="indicateur-valeur"><?= formaterMontant($v['total']) ?></dd>
            <dd class="indicateur-note"><?= (int) $v['n'] ?> mouvement(s)</dd>
          </div>
        <?php endforeach; ?>
        <?php if (!$parType): ?><div class="indicateur"><dt class="indicateur-libelle">Mouvements</dt><dd class="indicateur-valeur">0</dd><dd class="indicateur-note">Aucun sur 30 jours</dd></div><?php endif; ?>
      </dl>
    </section>

    <section class="flex flex-col gap-3" aria-labelledby="t-mouvements">
      <h2 id="t-mouvements" class="section-titre">Mouvements</h2>
      <form method="GET" action="/admin/audit.php" class="carte flex flex-col gap-3 p-4">
        <input type="hidden" name="onglet" value="argent">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div class="champ"><label class="champ-label" for="a-q">Référence ou nom</label>
            <input class="champ-saisie" type="search" id="a-q" name="q" value="<?= e($recherche) ?>"></div>
          <div class="champ"><label class="champ-label" for="a-u">Compte (identifiant)</label>
            <input class="champ-saisie chiffres" type="number" id="a-u" name="utilisateur" min="0" value="<?= $utilisateur ?: '' ?>"></div>
          <div class="champ"><label class="champ-label" for="a-type">Type</label>
            <select class="champ-saisie" id="a-type" name="type"><option value="">Tous</option>
              <?php foreach (['depot', 'retrait', 'commission', 'achat_service', 'jeu_gain', 'jeu_perte'] as $t): ?>
                <option value="<?= e($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= e(ucfirst($t)) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="champ"><label class="champ-label" for="a-du">Du</label>
            <input class="champ-saisie" type="date" id="a-du" name="du" value="<?= e($du) ?>"></div>
          <div class="champ"><label class="champ-label" for="a-au">Au</label>
            <input class="champ-saisie" type="date" id="a-au" name="au" value="<?= e($au) ?>"></div>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-sm btn-primaire"><?= ico('search', 'ico-16') ?>Filtrer</button>
          <a class="btn btn-sm btn-discret" href="/admin/audit.php?onglet=argent">Réinitialiser</a>
        </div>
      </form>

      <div class="carte overflow-hidden">
        <?php if (!$mouvements): ?>
          <div class="vide"><?= ico('banknote', 'ico-40') ?><p class="vide-titre">Aucun mouvement</p><p class="vide-texte">Modifiez les filtres pour élargir la recherche.</p></div>
        <?php else: ?>
          <table class="tableau tableau-empile">
            <thead><tr><th scope="col">Date</th><th scope="col">Compte</th><th scope="col">Type</th><th scope="col">Référence</th>
              <th scope="col" class="col-montant">Montant</th><th scope="col" class="col-montant">Solde après</th><th scope="col">Statut</th></tr></thead>
            <tbody>
            <?php foreach ($mouvements as $m): ?>
              <tr>
                <td data-label="Date" class="chiffres whitespace-nowrap text-text-2"><?= e(dateFr($m['created_at'], 'court')) ?></td>
                <td data-label="Compte"><a class="lien" href="?onglet=argent&utilisateur=<?= (int) $m['user_id'] ?>"><?= e($m['fullname']) ?></a>
                  <span class="meta chiffres">#<?= (int) $m['user_id'] ?></span></td>
                <td data-label="Type"><?= e(ucfirst($m['type'])) ?><?= $m['actor_id'] ? '<br><span class="meta">par #' . (int) $m['actor_id'] . '</span>' : '' ?></td>
                <td data-label="Référence" class="chiffres text-text-2"><?= e((string) $m['reference']) ?>
                  <?= $m['audit_id'] ? '<br><a class="lien meta" href="?onglet=journal&entite=transaction&entite_id=' . (int) $m['id'] . '">journal</a>' : '' ?></td>
                <td data-label="Montant" class="col-montant"><?= montant($m['amount']) ?></td>
                <td data-label="Solde après" class="col-montant chiffres"><?= $m['balance_after'] !== null ? e(formaterMontant($m['balance_after'])) : '<span class="text-text-3">inconnu</span>' ?></td>
                <td data-label="Statut"><?= badgeStatut($m['status'], 'transaction') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <?php if ($suite && $mouvements): ?>
        <?php $q = array_filter(['onglet' => 'argent', 'q' => $recherche, 'utilisateur' => $utilisateur ?: '', 'type' => $type, 'du' => $du, 'au' => $au, 'avant' => (int) end($mouvements)['id']]); ?>
        <a class="btn btn-secondaire self-start" href="?<?= e(http_build_query($q)) ?>"><?= ico('arrow-right', 'ico-16') ?>Mouvements plus anciens</a>
      <?php endif; ?>
    </section>
