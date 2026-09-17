<?php
/**
 * completer-telephone.php
 * Page obligatoire pour les comptes créés/connectés via Google Sign-In
 * qui n'ont pas encore de téléphone vérifié (phone_verified = 0).
 * Tant que ce n'est pas fait, exigerTelephoneVerifie() (auth_middleware.php)
 * redirige systématiquement ici depuis les autres pages protégées.
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/whatsapp_sender.php';

exigerConnexion();

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT fullname, phone, phone_verified FROM users_monrevenu WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /index.php'); exit();
}

// Déjà vérifié : rien à faire ici.
if ((int) $user['phone_verified'] === 1) {
    header('Location: /dashboard.php'); exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreur = 'Session expirée, veuillez recharger la page.';
    } else {
        $phone_brut    = trim($_POST['phone'] ?? '');
        $phone_country = trim($_POST['phone_country'] ?? 'KM');
        $phone_nettoye = preg_replace('/[^\d]/', '', $phone_brut);

        // Même logique de normalisation que l'inscription classique (register_handler.php)
        if (str_starts_with($phone_nettoye, '00269')) {
            $phone_local = substr($phone_nettoye, 5); $phone_country = 'KM';
        } elseif (str_starts_with($phone_nettoye, '00221')) {
            $phone_local = substr($phone_nettoye, 5); $phone_country = 'SN';
        } elseif (str_starts_with($phone_nettoye, '269') && strlen($phone_nettoye) === 10) {
            $phone_local = substr($phone_nettoye, 3); $phone_country = 'KM';
        } elseif (str_starts_with($phone_nettoye, '221') && strlen($phone_nettoye) === 12) {
            $phone_local = substr($phone_nettoye, 3); $phone_country = 'SN';
        } else {
            $phone_local = $phone_nettoye;
        }

        if ($phone_country === 'SN') {
            if (!preg_match('/^7\d{8}$/', $phone_local)) {
                $erreur = 'Numéro sénégalais invalide (ex: 771234567).';
            } else {
                $phone_normalise = '221' . $phone_local;
            }
        } else {
            if (!preg_match('/^[34]\d{6}$/', $phone_local)) {
                $erreur = 'Numéro comorien invalide (ex: 3212345 ou 4212345).';
            } else {
                $phone_normalise = '269' . $phone_local;
            }
        }

        if (!$erreur) {
            // Le numéro ne doit appartenir à aucun autre compte.
            $stmtExiste = $pdo->prepare("SELECT id FROM users_monrevenu WHERE phone = ? AND id != ? AND status != 'deleted'");
            $stmtExiste->execute([$phone_normalise, $user_id]);
            if ($stmtExiste->fetch()) {
                $erreur = 'Ce numéro est déjà associé à un autre compte MonRevenu.';
            }
        }

        if (!$erreur) {
            $code_verification      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $code_verification_hash = password_hash($code_verification, PASSWORD_BCRYPT);
            $code_expiration        = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            try {
                $pdo->prepare(
                    "UPDATE users_monrevenu
                     SET phone = ?, verification_method = 'whatsapp',
                         verification_code = ?, code_expires_at = ?, code_sent_at = NOW()
                     WHERE id = ?"
                )->execute([$phone_normalise, $code_verification_hash, $code_expiration, $user_id]);

                $resultatEnvoi = envoyerCodeWhatsApp($phone_normalise, $code_verification);

                if (!$resultatEnvoi['ok']) {
                    error_log("completer-telephone.php : échec envoi WhatsApp pour user {$user_id} : " . ($resultatEnvoi['erreur'] ?? ''));
                    $erreur = "Impossible d'envoyer le code par WhatsApp pour le moment. Réessayez dans quelques instants ou contactez le support.";
                } else {
                    $_SESSION['pending_verification_user_id'] = $user_id;
                    header('Location: /verification.php?nouveau=1');
                    exit();
                }
            } catch (PDOException $e) {
                error_log('completer-telephone.php : ' . $e->getMessage());
                $erreur = 'Erreur serveur, veuillez réessayer.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Complétez votre profil</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>body{font-family:'Sora',sans-serif;}</style>
</head>
<body class="bg-[#f7faff] min-h-screen flex items-center justify-center px-5 py-10">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-7 md:p-8">
    <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mb-5">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.21 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <h1 class="text-lg font-extrabold text-[#0f2547] mb-1">Encore une étape, <?= htmlspecialchars(explode(' ', $user['fullname'])[0]) ?> !</h1>
    <p class="text-sm text-[#5c6c80] mb-6">
      Votre compte a été créé avec Google. Pour promouvoir des produits et recevoir vos commissions, nous devons vérifier un numéro de téléphone comorien ou sénégalais qui vous appartient.
    </p>

    <?php if ($erreur): ?>
      <div class="text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-xl px-3.5 py-2.5 mb-4"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div>
        <label class="block text-xs font-bold text-[#5c6c80] uppercase tracking-wide mb-1.5">Numéro de téléphone</label>
        <div class="flex items-center gap-2 border border-slate-200 rounded-xl px-2 py-1 focus-within:border-[#1465e0] transition-shadow">
          <select name="phone_country" class="text-sm font-bold text-[#0f2547] bg-transparent outline-none py-2 pl-1.5 pr-1 shrink-0">
            <option value="KM">🇰🇲 +269</option>
            <option value="SN">🇸🇳 +221</option>
          </select>
          <span class="w-px h-5 bg-slate-200 shrink-0"></span>
          <input type="tel" name="phone" placeholder="3000000 ou 4000000" required
                 inputmode="numeric" maxlength="9"
                 class="flex-1 min-w-0 outline-none text-sm text-[#0f2547] bg-transparent placeholder:text-slate-300 py-2">
        </div>
        <p class="text-[11px] text-slate-400 mt-1.5">Un code vous sera envoyé par WhatsApp sur ce numéro.</p>
      </div>

      <button type="submit" class="w-full bg-[#1465e0] hover:bg-[#0d47ad] text-white font-bold text-sm py-3.5 rounded-full transition-colors">
        Recevoir mon code de vérification
      </button>
    </form>

    <p class="text-center text-xs text-slate-400 mt-6">
      Ce numéro doit vous appartenir personnellement — c'est celui qui recevra vos commissions.
    </p>
  </div>
</body>
</html>