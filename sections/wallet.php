<?php
// sections/wallet.php
// Variables requises : $balance, $user_fullname, $pdo, $user_id, $sender
// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$transfert_error   = '';
$transfert_success = '';
$retrait_error      = '';
$retrait_success    = '';

if (!defined('MONTANT_MIN_RETRAIT')) {
    define('MONTANT_MIN_RETRAIT', 1000);
}

// Traitement transfert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfert') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $transfert_error = 'Session expirée.';
    } else {
        $phone_dest = trim($_POST['phone_dest'] ?? '');
        $montant    = floatval($_POST['montant'] ?? 0);

        if (!$phone_dest || !$montant)               { $transfert_error = 'Remplissez tous les champs.'; }
        elseif ($phone_dest === ($sender['phone'] ?? '')) { $transfert_error = 'Vous ne pouvez pas vous envoyer de l\'argent.'; }
        elseif ($montant < 100)                      { $transfert_error = 'Montant minimum : 100 KMF.'; }
        elseif ($montant > $balance)                 { $transfert_error = 'Solde insuffisant.'; }
        else {
            $stmt = $pdo->prepare("SELECT id, fullname FROM users_monrevenu WHERE phone = ? AND id != ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$phone_dest, $user_id]);
            $dest = $stmt->fetch();
            if (!$dest) {
                $transfert_error = 'Ce numéro n\'existe pas sur MonRevenu.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $ref = 'TRF-' . strtoupper(uniqid());
                    $pdo->prepare("UPDATE users_monrevenu SET balance = balance - ? WHERE id = ?")->execute([$montant, $user_id]);
                    $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?")->execute([$montant, $dest['id']]);
                    $pdo->prepare("INSERT INTO transactions_monrevenu (user_id,type,amount,reference,status,description,created_at) VALUES (?,?,?,?,'complete',?,NOW())")->execute([$user_id,'retrait',$montant,$ref,'Transfert vers '.$dest['fullname']]);
                    $pdo->prepare("INSERT INTO transactions_monrevenu (user_id,type,amount,reference,status,description,created_at) VALUES (?,?,?,?,'complete',?,NOW())")->execute([$dest['id'],'depot',$montant,$ref.'-R','Reçu de '.$user_fullname]);
                    $pdo->commit();
                    $balance -= $montant;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $transfert_success = 'Transfert de '.number_format($montant,0,',','.').' KMF envoyé à '.$dest['fullname'].' !';
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $transfert_error = 'Erreur lors du transfert. Réessayez.';
                }
            }
        }
    }
}

// Traitement demande de retrait
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retrait') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $retrait_error = 'Session expirée.';
    } else {
        $montant_r = floatval($_POST['montant_retrait'] ?? 0);
        $methode_r = trim($_POST['methode_retrait'] ?? '');
        $numero_r  = trim($_POST['numero_reception'] ?? '');

        if (!$montant_r || !$methode_r || !$numero_r) {
            $retrait_error = 'Remplissez tous les champs.';
        } elseif ($montant_r < MONTANT_MIN_RETRAIT) {
            $retrait_error = 'Montant minimum : ' . number_format(MONTANT_MIN_RETRAIT, 0, ',', '.') . ' KMF.';
        } elseif ($montant_r > $balance) {
            $retrait_error = 'Solde insuffisant.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmtLock = $pdo->prepare("SELECT balance FROM users_monrevenu WHERE id = ? FOR UPDATE");
                $stmtLock->execute([$user_id]);
                $solde_verifie = (float) $stmtLock->fetchColumn();

                if ($montant_r > $solde_verifie) {
                    $pdo->rollBack();
                    $retrait_error = 'Solde insuffisant.';
                } else {
                    $pdo->prepare("UPDATE users_monrevenu SET balance = balance - ? WHERE id = ?")->execute([$montant_r, $user_id]);

                    $note = $methode_r . ' — ' . $numero_r;
                    $stmtRetrait = $pdo->prepare(
                        "INSERT INTO withdrawals (user_id, amount, status, method, note, created_at)
                         VALUES (?, ?, 'en_attente', ?, ?, NOW())"
                    );
                    $stmtRetrait->execute([$user_id, $montant_r, $methode_r, $note]);
                    $withdrawal_id = (int) $pdo->lastInsertId();

                    $referenceTx = 'RETRAIT-' . $withdrawal_id;
                    $pdo->prepare(
                        "INSERT INTO transactions_monrevenu (user_id, type, amount, reference, status, description)
                         VALUES (?, 'retrait', ?, ?, 'en_attente', ?)"
                    )->execute([$user_id, $montant_r, $referenceTx, 'Demande de retrait via ' . $methode_r]);

                    $textNotif = "🏦 Votre demande de retrait de " . number_format($montant_r, 0, ',', '.') . " KMF a été enregistrée et est en attente de validation.";
                    $pdo->prepare(
                        "INSERT INTO messages (user_id, expediteur, message, statut) VALUES (?, 'MonRevenu', ?, 'non_lu')"
                    )->execute([$user_id, $textNotif]);

                    $pdo->commit();
                    $balance -= $montant_r;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $retrait_success = 'Demande de retrait de ' . number_format($montant_r, 0, ',', '.') . ' KMF envoyée ! Vous serez notifié(e) une fois le paiement effectué.';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $retrait_error = 'Erreur lors de la demande. Réessayez.';
            }
        }
    }
}

// AJAX vérification numéro
if (isset($_GET['check_phone'])) {
    header('Content-Type: application/json');
    $phone = trim($_GET['check_phone']);
    $stmt  = $pdo->prepare("SELECT fullname, phone FROM users_monrevenu WHERE phone = ? AND id != ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$phone, $user_id]);
    $u = $stmt->fetch();
    echo $u ? json_encode(['found'=>true,'name'=>$u['fullname'],'phone'=>$u['phone']]) : json_encode(['found'=>false]);
    exit();
}
?>

<!-- ═══════════ WALLET SECTION ═══════════ -->
<section id="section-wallet" class="px-4 pt-4 pb-2">
  <div class="relative rounded-2xl overflow-hidden shadow-xl bg-gradient-to-br from-[#1246A0] via-[#1A5FCC] to-[#3B82F6] p-6">
    <div class="absolute -top-8 -right-8 w-40 h-40 rounded-full bg-white/5"></div>
    <div class="absolute -bottom-10 right-12 w-28 h-28 rounded-full bg-white/4"></div>

    <p class="text-white/70 text-xs mb-1 relative z-10">Solde disponible</p>
    <h2 class="text-white font-extrabold text-4xl tracking-tight mb-5 relative z-10">
      <?= htmlspecialchars(number_format($balance, 0, ',', '.')) ?> <span class="text-base font-medium opacity-70">KMF</span>
    </h2>

    <div class="flex gap-3 flex-wrap relative z-10">

      <button onclick="openModal()"
              class="flex items-center gap-2 bg-white/15 border border-white/25 text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-white/25 transition-colors">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
          <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
        </svg>
        Transférer
      </button>

      <button onclick="openRetraitModal()"
              class="flex items-center gap-2 bg-white/15 border border-white/25 text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-white/25 transition-colors">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 17V3"/><path d="m6 11 6 6 6-6"/><path d="M19 21H5"/>
        </svg>
        Retirer
      </button>
    </div>
  </div>
</section>

<!-- ═══════════ MODAL TRANSFERT ═══════════ -->
<div id="transfertModal"
     class="fixed inset-0 z-50 flex items-center justify-center px-4
            bg-black/50
            opacity-0 pointer-events-none transition-opacity duration-200"
     style="position:fixed; top:0; left:0; right:0; bottom:0;">

  <div id="modalBox"
       class="bg-white dark:bg-[#141E33] rounded-2xl shadow-2xl w-full max-w-md
              translate-y-4 transition-transform duration-200">

    <!-- Header modal -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
          <svg class="w-5 h-5 text-[#1246A0]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
            <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-[15px] text-slate-800 dark:text-white">Transférer de l'argent</h3>
          <p class="text-[11px] text-slate-400">Solde : <?= number_format($balance,0,',','.') ?> KMF</p>
        </div>
      </div>
      <button onclick="closeModal()"
              class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
        <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Body modal -->
    <div class="px-6 py-5">

      <?php if($transfert_success): ?>
      <div class="flex flex-col items-center text-center py-4">
        <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-3">
          <svg class="w-8 h-8 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h4 class="font-bold text-[16px] text-slate-800 dark:text-white mb-1">Transfert réussi !</h4>
        <p class="text-[13px] text-slate-500"><?= htmlspecialchars($transfert_success) ?></p>
        <button onclick="closeModal()" class="mt-5 bg-[#1246A0] text-white font-semibold text-[13px] px-6 py-2.5 rounded-xl hover:bg-[#1A5FCC] transition-colors">
          Fermer
        </button>
      </div>

      <?php else: ?>

      <?php if($transfert_error): ?>
      <div class="flex items-center gap-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-600 dark:text-red-400 rounded-xl p-3 mb-4">
        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-[12px] font-semibold"><?= htmlspecialchars($transfert_error) ?></p>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>
        <input type="hidden" name="action" value="transfert"/>

        <!-- Numéro destinataire -->
        <div class="mb-4">
          <label class="block text-[12px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wide">
            Numéro du destinataire
          </label>
          <div class="relative">
            <input type="tel" name="phone_dest" id="w_phone_dest"
                   placeholder="Ex: 77 000 00 00"
                   autocomplete="off" maxlength="20"
                   oninput="wCheckPhone(this.value)"
                   class="w-full px-4 py-3 pr-12 rounded-xl border-2 border-slate-200 dark:border-slate-700
                          bg-slate-50 dark:bg-slate-800 text-[14px] text-slate-800 dark:text-white
                          focus:outline-none focus:border-[#1246A0] focus:bg-white dark:focus:bg-slate-700 transition-all"/>
            <div id="w_statusIcon" class="absolute right-4 top-1/2 -translate-y-1/2"></div>
          </div>

          <div id="w_recipientCard" class="hidden mt-2 flex items-center gap-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-xl p-3">
            <div class="w-9 h-9 rounded-full bg-[#1246A0] flex items-center justify-center flex-shrink-0">
              <span id="w_recipientInitials" class="text-white font-bold text-xs"></span>
            </div>
            <div>
              <p id="w_recipientName" class="font-bold text-[13px] text-slate-800 dark:text-white"></p>
              <p id="w_recipientPhone" class="text-[11px] text-slate-400"></p>
            </div>
            <svg class="w-4 h-4 text-green-500 ml-auto flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>

          <p id="w_notFound" class="hidden mt-1.5 text-[12px] text-red-500 font-medium">
            ❌ Numéro introuvable sur MonRevenu.
          </p>
        </div>

        <!-- Montant -->
        <div class="mb-5">
          <label class="block text-[12px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wide">
            Montant (KMF)
          </label>
          <div class="relative">
            <input type="number" name="montant" id="w_montant"
                   placeholder="0" min="100" max="<?= $balance ?>"
                   class="w-full px-4 py-3 pr-16 rounded-xl border-2 border-slate-200 dark:border-slate-700
                          bg-slate-50 dark:bg-slate-800 text-[14px] text-slate-800 dark:text-white
                          focus:outline-none focus:border-[#1246A0] focus:bg-white dark:focus:bg-slate-700 transition-all"/>
            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[12px] font-bold text-slate-400">KMF</span>
          </div>
          <div class="flex gap-2 mt-2">
            <?php foreach([500,1000,2000,5000] as $q): ?>
            <button type="button"
                    onclick="document.getElementById('w_montant').value=<?= $q ?>"
                    class="flex-1 text-[11px] font-semibold py-1.5 rounded-lg
                           bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300
                           hover:bg-blue-50 hover:text-[#1246A0] transition-colors">
              <?= number_format($q,0,',','.') ?> F
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Bouton Envoyer -->
        <button type="submit" id="w_submitBtn" disabled
                class="w-full flex items-center justify-center gap-2
                       bg-[#1246A0] hover:bg-[#1A5FCC]
                       disabled:bg-slate-200 dark:disabled:bg-slate-700
                       disabled:text-slate-400 disabled:cursor-not-allowed
                       text-white font-bold text-[14px] py-3 rounded-xl
                       transition-all shadow-md shadow-blue-500/20">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"/>
            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
          Envoyer
        </button>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- ═══════════ MODAL RETRAIT ═══════════ -->
<div id="retraitModal"
     class="fixed inset-0 z-50 flex items-center justify-center px-4
            bg-black/50
            opacity-0 pointer-events-none transition-opacity duration-200"
     style="position:fixed; top:0; left:0; right:0; bottom:0;">

  <div id="retraitModalBox"
       class="bg-white dark:bg-[#141E33] rounded-2xl shadow-2xl w-full max-w-md
              translate-y-4 transition-transform duration-200">

    <!-- Header modal -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
          <svg class="w-5 h-5 text-[#1246A0]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 17V3"/><path d="m6 11 6 6 6-6"/><path d="M19 21H5"/>
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-[15px] text-slate-800 dark:text-white">Demander un retrait</h3>
          <p class="text-[11px] text-slate-400">Solde : <?= number_format($balance,0,',','.') ?> KMF</p>
        </div>
      </div>
      <button onclick="closeRetraitModal()"
              class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
        <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Body modal -->
    <div class="px-6 py-5">

      <?php if ($retrait_success): ?>
      <div class="flex flex-col items-center text-center py-4">
        <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-3">
          <svg class="w-8 h-8 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h4 class="font-bold text-[16px] text-slate-800 dark:text-white mb-1">Demande envoyée !</h4>
        <p class="text-[13px] text-slate-500"><?= htmlspecialchars($retrait_success) ?></p>
        <button onclick="closeRetraitModal()" class="mt-5 bg-[#1246A0] text-white font-semibold text-[13px] px-6 py-2.5 rounded-xl hover:bg-[#1A5FCC] transition-colors">
          Fermer
        </button>
      </div>

      <?php else: ?>

      <?php if ($retrait_error): ?>
      <div class="flex items-center gap-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-600 dark:text-red-400 rounded-xl p-3 mb-4">
        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-[12px] font-semibold"><?= htmlspecialchars($retrait_error) ?></p>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>
        <input type="hidden" name="action" value="retrait"/>

        <!-- Montant -->
        <div class="mb-4">
          <label class="block text-[12px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wide">
            Montant à retirer (KMF)
          </label>
          <div class="relative">
            <input type="number" name="montant_retrait" id="r_montant"
                   placeholder="0" min="<?= MONTANT_MIN_RETRAIT ?>" max="<?= (int) $balance ?>" required
                   class="w-full px-4 py-3 pr-16 rounded-xl border-2 border-slate-200 dark:border-slate-700
                          bg-slate-50 dark:bg-slate-800 text-[14px] text-slate-800 dark:text-white
                          focus:outline-none focus:border-[#1246A0] focus:bg-white dark:focus:bg-slate-700 transition-all"/>
            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[12px] font-bold text-slate-400">KMF</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-1.5">Minimum : <?= number_format(MONTANT_MIN_RETRAIT, 0, ',', '.') ?> KMF</p>
          <div class="flex gap-2 mt-2">
            <?php foreach([1000,2000,5000,10000] as $q): ?>
            <button type="button"
                    onclick="document.getElementById('r_montant').value=<?= $q ?>"
                    class="flex-1 text-[11px] font-semibold py-1.5 rounded-lg
                           bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300
                           hover:bg-blue-50 hover:text-[#1246A0] transition-colors">
              <?= number_format($q,0,',','.') ?> F
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Moyen de retrait -->
        <div class="mb-4">
          <label class="block text-[12px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wide">
            Moyen de retrait
          </label>
          <select name="methode_retrait" required
                  class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 dark:border-slate-700
                         bg-slate-50 dark:bg-slate-800 text-[14px] text-slate-800 dark:text-white
                         focus:outline-none focus:border-[#1246A0] focus:bg-white dark:focus:bg-slate-700 transition-all">
            <option value="">-- Choisir --</option>
            <option value="Mvola">Mvola</option>
            <option value="Autre">Autre</option>
          </select>
        </div>

        <!-- Numéro de réception -->
        <div class="mb-5">
          <label class="block text-[12px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wide">
            Numéro de réception
          </label>
          <input type="text" name="numero_reception" required placeholder="Numéro Mvola"
                 class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 dark:border-slate-700
                        bg-slate-50 dark:bg-slate-800 text-[14px] text-slate-800 dark:text-white
                        focus:outline-none focus:border-[#1246A0] focus:bg-white dark:focus:bg-slate-700 transition-all"/>
        </div>

        <!-- Bouton Envoyer -->
        <button type="submit" <?= $balance < MONTANT_MIN_RETRAIT ? 'disabled' : '' ?>
                class="w-full flex items-center justify-center gap-2
                       bg-[#1246A0] hover:bg-[#1A5FCC]
                       disabled:bg-slate-200 dark:disabled:bg-slate-700
                       disabled:text-slate-400 disabled:cursor-not-allowed
                       text-white font-bold text-[14px] py-3 rounded-xl
                       transition-all shadow-md shadow-blue-500/20">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 17V3"/><path d="m6 11 6 6 6-6"/><path d="M19 21H5"/>
          </svg>
          Envoyer la demande
        </button>
        <?php if ($balance < MONTANT_MIN_RETRAIT): ?>
          <p class="text-[11px] text-slate-400 text-center mt-2">Solde insuffisant pour effectuer un retrait.</p>
        <?php endif; ?>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
// ── Modal Transfert ──────────────────────────────────────────────────────────
function openModal() {
  const modal = document.getElementById('transfertModal');
  const box   = document.getElementById('modalBox');
  modal.classList.remove('opacity-0', 'pointer-events-none');
  setTimeout(() => box.classList.remove('translate-y-4'), 10);
  document.body.style.overflow = 'hidden';
  document.body.style.position = 'fixed';
  document.body.style.width    = '100%';
}

function closeModal() {
  const modal = document.getElementById('transfertModal');
  const box   = document.getElementById('modalBox');
  box.classList.add('translate-y-4');
  setTimeout(() => modal.classList.add('opacity-0', 'pointer-events-none'), 150);
  document.body.style.overflow = '';
  document.body.style.position = '';
  document.body.style.width    = '';
}

document.getElementById('transfertModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── Modal Retrait ────────────────────────────────────────────────────────────
function openRetraitModal() {
  const modal = document.getElementById('retraitModal');
  const box   = document.getElementById('retraitModalBox');
  modal.classList.remove('opacity-0', 'pointer-events-none');
  setTimeout(() => box.classList.remove('translate-y-4'), 10);
  document.body.style.overflow = 'hidden';
  document.body.style.position = 'fixed';
  document.body.style.width    = '100%';
}

function closeRetraitModal() {
  const modal = document.getElementById('retraitModal');
  const box   = document.getElementById('retraitModalBox');
  box.classList.add('translate-y-4');
  setTimeout(() => modal.classList.add('opacity-0', 'pointer-events-none'), 150);
  document.body.style.overflow = '';
  document.body.style.position = '';
  document.body.style.width    = '';
}

document.getElementById('retraitModal').addEventListener('click', function(e) {
  if (e.target === this) closeRetraitModal();
});

// Fermer avec Escape (les deux modales)
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeModal(); closeRetraitModal(); }
});

<?php if($transfert_error || $transfert_success): ?>
document.addEventListener('DOMContentLoaded', () => openModal());
<?php endif; ?>

<?php if($retrait_error || $retrait_success): ?>
document.addEventListener('DOMContentLoaded', () => openRetraitModal());
<?php endif; ?>

// ── Vérification numéro (transfert) ─────────────────────────────────────────
let wTimer   = null;
let wPhoneOk = false;

function wCheckPhone(val) {
  clearTimeout(wTimer);
  const phone    = val.trim();
  const icon     = document.getElementById('w_statusIcon');
  const card     = document.getElementById('w_recipientCard');
  const notFound = document.getElementById('w_notFound');
  const btn      = document.getElementById('w_submitBtn');

  card.classList.add('hidden');
  notFound.classList.add('hidden');
  wPhoneOk  = false;
  btn.disabled = true;

  if (phone.length < 6) { icon.innerHTML = ''; return; }

  icon.innerHTML = '<svg class="w-4 h-4 text-slate-400 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';

  wTimer = setTimeout(() => {
    fetch(`?check_phone=${encodeURIComponent(phone)}`)
      .then(r => r.json())
      .then(data => {
        if (data.found) {
          wPhoneOk = true;
          icon.innerHTML = '<svg class="w-4 h-4 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
          document.getElementById('w_recipientInitials').textContent = data.name.substring(0,2).toUpperCase();
          document.getElementById('w_recipientName').textContent     = data.name;
          document.getElementById('w_recipientPhone').textContent    = data.phone;
          card.classList.remove('hidden');
          btn.disabled = false;
        } else {
          icon.innerHTML = '<svg class="w-4 h-4 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
          notFound.classList.remove('hidden');
          btn.disabled = true;
        }
      });
  }, 600);
}
</script>