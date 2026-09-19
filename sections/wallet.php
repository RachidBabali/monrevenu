<?php
// sections/wallet.php
// Variables requises : $balance, $user_fullname, $pdo, $user_id, $sender
require_once __DIR__ . '/../includs/ui.php';
// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$retrait_error      = '';
$retrait_success    = '';

if (!defined('MONTANT_MIN_RETRAIT')) {
    define('MONTANT_MIN_RETRAIT', 1000);
}

// Traitement demande de retrait
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retrait') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $retrait_error = 'Votre session a expiré. Rechargez la page puis recommencez.';
        require_once __DIR__ . '/../includs/audit.php';
        auditInfo($pdo, ['category' => 'systeme', 'action' => 'csrf_echec', 'result' => 'refus', 'meta' => ['page' => 'portefeuille']]);
    } else {
        $montant_r = round(floatval($_POST['montant_retrait'] ?? 0), 2);
        $methode_r = trim($_POST['methode_retrait'] ?? '');
        $numero_r  = trim($_POST['numero_reception'] ?? '');

        if (!$montant_r || !$methode_r || !$numero_r) {
            $retrait_error = 'Indiquez le montant, le moyen de retrait et le numéro de réception.';
        } elseif ($montant_r < MONTANT_MIN_RETRAIT) {
            $retrait_error = 'Le minimum de retrait est de ' . formaterMontant(MONTANT_MIN_RETRAIT) . '. Saisissez un montant plus élevé.';
        } elseif ($montant_r > $balance) {
            $retrait_error = 'Solde insuffisant : vous disposez de ' . formaterMontant($balance) . '.';
        } else {
            require_once __DIR__ . '/../includs/argent.php';
            $pdo->beginTransaction();
            try {
                // Le retrait est cree d'abord pour connaitre son numero, puis le solde est debite sous verrou
                // par mouvementSolde (refus si le solde deviendrait negatif, journal dans la meme transaction).
                $note = $methode_r . ' : ' . $numero_r;
                $stmtRetrait = $pdo->prepare(
                    "INSERT INTO withdrawals (user_id, amount, status, method, note, created_at)
                     VALUES (?, ?, 'en_attente', ?, ?, NOW())"
                );
                $stmtRetrait->execute([$user_id, $montant_r, $methode_r, $note]);
                $withdrawal_id = (int) $pdo->lastInsertId();

                mouvementSolde($pdo, (int) $user_id, -$montant_r, 'retrait', 'RETRAIT-' . $withdrawal_id, 'en_attente',
                    'Demande de retrait via ' . $methode_r, 'retrait_demande', ['retrait_id' => $withdrawal_id, 'methode' => $methode_r]);

                $pdo->commit();

                // Notification apres le commit : aucun appel reseau pendant que la tete du journal est verrouillee
                try {
                    require_once __DIR__ . '/../includs/notifications.php';
                    envoyerNotification(
                        $pdo, $user_id,
                        "Votre demande de retrait de " . formaterMontant($montant_r) . " est enregistrée. Elle est en attente de validation.",
                        'Demande de retrait', '/page/historique.php'
                    );
                } catch (Throwable $e) {
                    error_log('[wallet] notification de retrait : ' . get_class($e));
                }
                $balance -= $montant_r;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $retrait_success = 'Demande de retrait de ' . formaterMontant($montant_r) . ' enregistrée. Vous recevrez une notification quand le paiement sera effectué.';
            } catch (SoldeInsuffisant $e) {
                $pdo->rollBack();
                $retrait_error = 'Solde insuffisant : vous disposez de ' . formaterMontant($balance) . '.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[wallet] retrait : ' . get_class($e) . ' ' . $e->getMessage());
                $retrait_error = "La demande n'a pas pu être enregistrée. Réessayez dans un instant.";
            }
        }
    }
}

$retrait_possible = $balance >= MONTANT_MIN_RETRAIT;
$retrait_manque   = max(0, MONTANT_MIN_RETRAIT - $balance);
?>
<?php if ($retrait_success): ?>
  <p class="alerte alerte-succes" role="status"><?= ico('circle-check') ?><span><?= e($retrait_success) ?></span></p>
<?php endif; ?>

<section id="section-wallet" class="carte flex flex-col gap-4 p-4 sm:flex-row sm:items-end sm:justify-between" aria-labelledby="t-solde-wallet">
  <div>
    <h2 id="t-solde-wallet" class="text-sm font-normal text-text-2">Solde disponible</h2>
    <p class="montant mt-1 text-4xl"><?= formaterMontant($balance) ?></p>
    <p class="meta mt-1">Minimum de retrait : <?= formaterMontant(MONTANT_MIN_RETRAIT) ?></p>
  </div>
  <div class="flex flex-col gap-1 sm:items-end">
    <button type="button" class="btn btn-primaire" data-ouvrir="retraitModal"<?= $retrait_possible ? '' : ' disabled aria-describedby="raison-retrait"' ?>><?= ico('banknote') ?>Demander un retrait</button>
    <?php if (!$retrait_possible): ?>
      <p id="raison-retrait" class="text-xs text-text-2">Il vous manque <?= formaterMontant($retrait_manque) ?> pour atteindre le minimum.</p>
    <?php endif; ?>
  </div>
</section>

<dialog class="feuille" id="retraitModal" aria-labelledby="retrait-titre"<?= $retrait_error ? ' data-ouvrir-auto' : '' ?><?= $retrait_success ? ' data-envoye' : '' ?>>
  <div class="poignee"></div>
  <div class="feuille-entete">
    <h2 class="feuille-titre" id="retrait-titre">Demander un retrait</h2>
    <button class="btn btn-icone btn-discret" type="button" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
  </div>
  <form method="POST" action="" id="retraitForm" class="flex flex-col" novalidate>
    <div class="feuille-corps flex flex-col gap-4">
      <?php if ($retrait_error): ?>
        <p class="alerte alerte-danger" role="alert"><?= ico('circle-alert') ?><span><?= e($retrait_error) ?></span></p>
      <?php endif; ?>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="retrait">

      <div class="champ">
        <label class="champ-label" for="r_montant">Montant</label>
        <div class="champ-groupe">
          <input class="champ-saisie chiffres" type="number" name="montant_retrait" id="r_montant" inputmode="numeric"
                 min="<?= (int) MONTANT_MIN_RETRAIT ?>" max="<?= (int) $balance ?>" step="1" required autocomplete="off"
                 aria-describedby="r_montant_aide r_montant_err" data-solde="<?= (float) $balance ?>" data-minimum="<?= (int) MONTANT_MIN_RETRAIT ?>">
          <span class="champ-prefixe rounded-l-none border-l-0 border-r">FCFA</span>
        </div>
        <p class="champ-aide" id="r_montant_aide">Solde disponible : <?= formaterMontant($balance) ?>. Minimum : <?= formaterMontant(MONTANT_MIN_RETRAIT) ?>.</p>
        <p class="champ-erreur" id="r_montant_err" hidden><?= ico('circle-alert', 'ico-16 mt-0.5') ?><span></span></p>
      </div>

      <div class="champ">
        <label class="champ-label" for="r_methode">Moyen de retrait</label>
        <select class="champ-saisie" name="methode_retrait" id="r_methode" required aria-describedby="r_methode_err">
          <option value="">Choisir un moyen</option>
          <option value="Mvola">Mvola</option>
          <option value="Autre">Autre</option>
        </select>
        <p class="champ-erreur" id="r_methode_err" hidden><?= ico('circle-alert', 'ico-16 mt-0.5') ?><span>Choisissez le moyen de retrait.</span></p>
      </div>

      <div class="champ">
        <label class="champ-label" for="r_numero">Numéro de réception</label>
        <input class="champ-saisie" type="tel" name="numero_reception" id="r_numero" inputmode="tel" autocomplete="tel" required placeholder="Numéro du compte mobile money" aria-describedby="r_numero_err">
        <p class="champ-aide">Le paiement est envoyé sur ce numéro après validation par l'équipe MonRevenu.</p>
        <p class="champ-erreur" id="r_numero_err" hidden><?= ico('circle-alert', 'ico-16 mt-0.5') ?><span>Indiquez le numéro qui recevra le paiement.</span></p>
      </div>

      <dl class="recap" aria-live="polite">
        <div class="recap-ligne"><dt>Montant demandé</dt><dd class="montant" id="r_recap_montant"><?= formaterMontant(0) ?></dd></div>
        <div class="recap-ligne recap-total"><dt>Solde après la demande</dt><dd class="montant" id="r_recap_solde"><?= formaterMontant($balance) ?></dd></div>
      </dl>
    </div>
    <div class="feuille-pied">
      <button class="btn btn-secondaire" type="button" data-fermer>Annuler</button>
      <button class="btn btn-primaire" type="submit"<?= $retrait_possible ? '' : ' disabled' ?>><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Confirmer le retrait</span></button>
    </div>
  </form>
</dialog>
