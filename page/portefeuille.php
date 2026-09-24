<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
exigerConnexion();

$user_id       = $_SESSION['user_id'];
$user_fullname = $_SESSION['user_fullname'] ?? 'Utilisateur';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/config_marche.php';

// Meme lecture que dashboard.php (variables requises par sections/wallet.php)
$stmt = $pdo->prepare("SELECT balance, role, phone, phone_verified, pays_code FROM users_monrevenu WHERE id = ?");
$stmt->execute([$user_id]);
$sender         = $stmt->fetch();
$balance        = $sender['balance'] ?? 0;
$marche_membre  = marcheDeCompte($sender ?: null);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$titre_page   = 'Portefeuille';
$scripts_page = ['/assets/js/portefeuille.js'];

// Le traitement du retrait (POST) est dans sections/wallet.php : il doit s'executer avant tout affichage.
ob_start();
include $_SERVER['DOCUMENT_ROOT'] . '/sections/wallet.php';
$bloc_portefeuille = ob_get_clean();

/* Liste des retraits : lecture seule. */
$retraits = [];
try {
    $st = $pdo->prepare("SELECT id, amount, status, method, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $st->execute([$user_id]);
    $retraits = $st->fetchAll();
} catch (PDOException $e) {
    $message_error = $message_error ?: "La liste des retraits n'a pas pu être chargée.";
}

include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
echo $bloc_portefeuille;
?>

    <section class="flex flex-col gap-3" aria-labelledby="t-retraits">
      <div class="flex items-end justify-between gap-3">
        <h2 id="t-retraits" class="section-titre">Mes retraits</h2>
        <a class="lien cible text-sm" href="/page/historique.php">Tout l'historique</a>
      </div>
      <div class="carte overflow-hidden">
        <?php if (!$retraits): ?>
          <div class="vide">
            <?= ico('banknote', 'ico-40') ?>
            <p class="vide-titre">Aucun retrait</p>
            <p class="vide-texte">Vos demandes de retrait et leur statut apparaîtront ici.</p>
          </div>
        <?php else: ?>
          <table class="tableau tableau-empile">
            <thead><tr><th scope="col">Date</th><th scope="col">Moyen</th><th scope="col">Référence</th><th scope="col">Statut</th><th scope="col" class="col-montant">Montant</th></tr></thead>
            <tbody>
            <?php foreach ($retraits as $r): ?>
              <tr>
                <td data-label="" class="chiffres font-medium"><?= e(dateFr($r['created_at'], 'heure')) ?></td>
                <td data-label="Moyen"><?= e($r['method'] ?: 'Non précisé') ?></td>
                <td data-label="Référence" class="font-mono text-text-2">RETRAIT-<?= (int) $r['id'] ?></td>
                <td data-label="Statut"><?= badgeStatut($r['status'], 'retrait') ?></td>
                <td data-label="Montant" class="col-montant"><?= montant($r['amount'], false, '', $marche_membre) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

    <section class="carte p-4" aria-labelledby="t-regles">
      <h2 id="t-regles" class="carte-titre">Comment se passe un retrait</h2>
      <ol class="mt-3 flex list-decimal flex-col gap-2 pl-5 text-sm text-text-2">
        <li>Le montant demandé est déduit de votre solde au moment de la demande.</li>
        <li>L'équipe MonRevenu valide la demande et envoie le paiement sur le numéro indiqué.</li>
        <li>Si la demande est refusée, le montant est recrédité sur votre solde et vous recevez une notification.</li>
      </ol>
    </section>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
