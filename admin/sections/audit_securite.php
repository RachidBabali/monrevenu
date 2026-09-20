<?php
/** Onglet Sécurité : échecs de connexion, verrouillages, connexions administrateur, changements d'IP, refus. */
$jours = (int) ($_GET['jours'] ?? 7);
if (!in_array($jours, [1, 7, 30, 90], true)) $jours = 7;

$compter = static function (string $sql) use ($pdo, $jours): int {
    $st = $pdo->prepare($sql);
    $st->execute([$jours]);
    return (int) $st->fetchColumn();
};
$echecs = $compter("SELECT COUNT(*) FROM audit_log WHERE action = 'connexion_echec' AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
$verrous = $compter("SELECT COUNT(*) FROM audit_log WHERE action IN ('verrouillage_compte','connexion_refusee_verrou_ip','connexion_refusee_verrou_compte') AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
$refus = $compter("SELECT COUNT(*) FROM audit_log WHERE action IN ('acces_refuse','csrf_echec') AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
$adminConnexions = $compter("SELECT COUNT(*) FROM audit_log WHERE action = 'connexion' AND actor_role = 'admin' AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");

$tableaux = [
    ['Connexions administrateur', "SELECT occurred_at, actor_id, ip_prefixe, meta_json FROM audit_log
        WHERE action = 'connexion' AND actor_role = 'admin' AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY id DESC LIMIT 25"],
    ['Échecs de connexion et verrouillages', "SELECT occurred_at, action, actor_id, ip_prefixe, meta_json FROM audit_log
        WHERE action IN ('connexion_echec','verrouillage_compte','connexion_refusee_verrou_ip','connexion_refusee_verrou_compte','connexion_refusee_inactif')
          AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY id DESC LIMIT 25"],
    ['Accès refusés et jetons CSRF invalides', "SELECT occurred_at, action, actor_id, actor_role, ip_prefixe, meta_json FROM audit_log
        WHERE action IN ('acces_refuse','csrf_echec','produit_action_refusee','commande_acces_refuse','produit_acces_refuse')
          AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY id DESC LIMIT 25"],
    ['Changements de rôle, blocages et suppressions', "SELECT occurred_at, action, actor_id, entity_id, meta_json FROM audit_log
        WHERE category = 'admin' AND action IN ('compte_blocage','compte_deblocage','compte_suppression_admin','commercant_valider','commercant_suspendre','commercant_refuser','commercant_confiance')
          AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY id DESC LIMIT 25"],
    ['Changements d\'adresse IP en cours de session', "SELECT occurred_at, actor_id, ip_prefixe, meta_json FROM audit_log
        WHERE action = 'session_ip_changee' AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY id DESC LIMIT 25"],
];
?>
    <nav class="segments self-start" aria-label="Période">
      <?php foreach ([1 => '24 h', 7 => '7 j', 30 => '30 j', 90 => '90 j'] as $v => $l): ?>
        <a class="segment" href="?onglet=securite&jours=<?= $v ?>"<?= $jours === $v ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
      <?php endforeach; ?>
    </nav>

    <dl class="indicateurs">
      <div class="indicateur"><dt class="indicateur-libelle">Échecs de connexion</dt><dd class="indicateur-valeur"><?= $echecs ?></dd><dd class="indicateur-note">sur la période</dd></div>
      <div class="indicateur"><dt class="indicateur-libelle">Verrouillages</dt><dd class="indicateur-valeur"><?= $verrous ?></dd><dd class="indicateur-note">compte ou adresse IP</dd></div>
      <div class="indicateur"><dt class="indicateur-libelle">Refus d'accès</dt><dd class="indicateur-valeur"><?= $refus ?></dd><dd class="indicateur-note">pages interdites et jetons CSRF</dd></div>
      <div class="indicateur"><dt class="indicateur-libelle">Connexions administrateur</dt><dd class="indicateur-valeur"><?= $adminConnexions ?></dd><dd class="indicateur-note">sur la période</dd></div>
    </dl>

    <?php foreach ($tableaux as [$titre, $sql]): ?>
      <?php $st = $pdo->prepare($sql); $st->execute([$jours]); $lignes = $st->fetchAll(PDO::FETCH_ASSOC); ?>
      <section class="flex flex-col gap-3">
        <h2 class="section-titre"><?= e($titre) ?></h2>
        <div class="carte overflow-hidden">
          <?php if (!$lignes): ?>
            <p class="p-4 text-sm text-text-3">Rien sur la période.</p>
          <?php else: ?>
            <table class="tableau tableau-empile">
              <thead><tr><th scope="col">Quand</th><th scope="col">Événement</th><th scope="col">Acteur</th><th scope="col">IP</th><th scope="col">Détails</th></tr></thead>
              <tbody>
              <?php foreach ($lignes as $l): ?>
                <tr>
                  <td data-label="Quand" class="chiffres whitespace-nowrap text-text-2"><?= e(substr($l['occurred_at'], 0, 19)) ?></td>
                  <td data-label="Événement"><?= e($l['action'] ?? 'connexion') ?></td>
                  <td data-label="Acteur" class="chiffres"><?= $l['actor_id'] ? '#' . (int) $l['actor_id'] : 'anonyme' ?><?= isset($l['entity_id']) && $l['entity_id'] !== null ? ' <span class="meta">cible #' . e((string) $l['entity_id']) . '</span>' : '' ?></td>
                  <td data-label="IP" class="chiffres text-text-2"><?= e($l['ip_prefixe'] ?? '') ?></td>
                  <td data-label="Détails" class="text-text-2"><?= e(mb_substr((string) ($l['meta_json'] ?? ''), 0, 120)) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
