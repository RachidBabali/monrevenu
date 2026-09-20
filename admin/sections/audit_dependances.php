<?php
/** Onglet Dépendances : paquets de composer.lock, dernier composer audit, composants copiés dans le dépôt. */
$dep = santeDependances();
$audit = $dep['audit'];
$niveau = $audit === null ? 'inconnu' : (($audit['vulnerabilites'] > 0) ? 'critique' : (($audit['age_jours'] > 30) ? 'attention' : 'ok'));
$controles = [
    santeControle('composer_audit', 'Dernier contrôle des vulnérabilités',
        $audit === null ? 'jamais exécuté' : $audit['date'] . ' (' . $audit['age_jours'] . ' jour(s))', $niveau,
        $audit === null ? 'Lancer tools/composer-audit.sh en local, puis déposer storage/audit/composer-audit.json.'
            : ($audit['vulnerabilites'] > 0 ? $audit['vulnerabilites'] . ' vulnérabilité(s) signalée(s)' : 'Aucune vulnérabilité signalée')),
    santeControle('composer_paquets', 'Paquets installés', count($dep['paquets']), 'ok', 'Lus dans composer.lock'),
];
?>
    <?php tableauControles($controles, 'Dépendances'); ?>

    <section class="flex flex-col gap-3">
      <h2 class="section-titre">Paquets (composer.lock)</h2>
      <div class="carte overflow-hidden">
        <table class="tableau tableau-empile">
          <thead><tr><th scope="col">Paquet</th><th scope="col">Version</th><th scope="col">Licence</th></tr></thead>
          <tbody>
          <?php foreach ($dep['paquets'] as $p): ?>
            <tr>
              <td data-label="Paquet" class="chiffres"><?= e($p['nom']) ?></td>
              <td data-label="Version" class="chiffres text-text-2"><?= e($p['version']) ?></td>
              <td data-label="Licence" class="text-text-2"><?= e($p['licence']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$dep['paquets']): ?><tr><td colspan="3" class="text-text-3">composer.lock introuvable.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="flex flex-col gap-3">
      <h2 class="section-titre">Composants copiés dans le dépôt</h2>
      <div class="carte overflow-hidden">
        <table class="tableau tableau-empile">
          <thead><tr><th scope="col">Composant</th><th scope="col">Version</th><th scope="col">Licence</th></tr></thead>
          <tbody>
          <?php foreach ($dep['copies'] as [$nom, $version, $licence]): ?>
            <tr>
              <td data-label="Composant"><?= e($nom) ?></td>
              <td data-label="Version" class="text-text-2"><?= e($version) ?></td>
              <td data-label="Licence" class="text-text-2"><?= e($licence) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
