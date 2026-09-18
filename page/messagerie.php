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
    // Récupérer les vrais messages de l'utilisateur depuis la base de données
    $query = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC");
    $query->execute([$user_id]);
    $conversations = $query->fetchAll();
} catch (PDOException $e) {
    $conversations = [];
}

// Prépare les données complètes des messages pour le JS (affichage instantané, sans recharger la page)
$messages_json = [];
foreach ($conversations as $conv) {
    $messages_json[(int) $conv['id']] = [
        'expediteur' => $conv['expediteur'] ?? 'Support',
        'message'    => $conv['message'] ?? $conv['dernier_message'] ?? '',
        'date'       => isset($conv['created_at']) ? date('d/m/Y à H:i', strtotime($conv['created_at'])) : '',
    ];
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
$par_jour = [];
$nb_non_lus = 0;
foreach ($conversations as $conv) {
    $par_jour[substr((string) ($conv['created_at'] ?? ''), 0, 10)][] = $conv;
    if (strtolower((string) ($conv['statut'] ?? '')) === 'non_lu') {
        $nb_non_lus++;
    }
}
$titre_page   = 'Messages';
$scripts_page = ['/assets/js/messagerie.js'];
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm text-text-2" id="resume-messages"><?= $nb_non_lus > 0 ? '<span class="chiffres">' . $nb_non_lus . '</span> message' . ($nb_non_lus > 1 ? 's' : '') . ' non lu' . ($nb_non_lus > 1 ? 's' : '') : 'Tous vos messages sont lus.' ?></p>
      <?php if ($nb_non_lus > 0): ?>
        <button type="button" class="btn btn-sm btn-secondaire" id="tout-marquer-lu"><?= ico('check', 'ico-16') ?>Tout marquer comme lu</button>
      <?php endif; ?>
    </div>

    <?php if (empty($conversations)): ?>
      <div class="carte">
        <div class="vide">
          <?= ico('inbox', 'ico-40') ?>
          <p class="vide-titre">Aucun message</p>
          <p class="vide-texte">Les avis de commande, de commission et de retrait envoyés par MonRevenu apparaîtront ici.</p>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($par_jour as $jour => $messages): ?>
        <section class="carte overflow-hidden" aria-label="<?= e(dateFr($jour, 'jour')) ?>">
          <h2 class="border-b border-line bg-surface-2 px-4 py-2 text-xs font-medium text-text-3"><?= e(dateFr($jour, 'jour')) ?></h2>
          <?php foreach ($messages as $conv):
            $msgId    = (int) $conv['id'];
            $isUnread = strtolower((string) ($conv['statut'] ?? '')) === 'non_lu';
            $texte    = nettoyerPictogrammes($conv['message'] ?? '');
          ?>
            <article class="ligne-tx items-start<?= $isUnread ? ' bg-primary-soft' : '' ?>" id="carte-message-<?= $msgId ?>" data-message-id="<?= $msgId ?>"<?= $isUnread ? ' data-non-lu' : '' ?>>
              <span class="ligne-tx-icone"><?= ico(typeNotification($texte)) ?></span>
              <div class="ligne-tx-corps">
                <p class="flex items-center gap-2 text-xs text-text-3">
                  <span class="font-medium text-text-2"><?= e(nettoyerPictogrammes($conv['expediteur'] ?? 'MonRevenu')) ?></span>
                  <span class="chiffres"><?= e(dateFr($conv['created_at'] ?? null, 'heure_seule')) ?></span>
                  <?php if ($isUnread): ?><span class="pastille pastille-info" id="pastille-<?= $msgId ?>">Non lu</span><?php endif; ?>
                </p>
                <p class="mt-1 text-sm text-text"><?= nl2br(e($texte)) ?></p>
                <?php if ($isUnread): ?>
                  <button type="button" class="lien cible text-sm" data-marquer-lu="<?= $msgId ?>">Marquer comme lu</button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
