<?php
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/email_sender.php';

$user_id = $_SESSION['reset_password_user_id'] ?? null;
if (!$user_id) {
    header('Location: /mot_de_passe_oublie.php'); exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

$stmtUser = $pdo->prepare("SELECT id, fullname, email, reset_password_code, reset_password_expires_at FROM users_monrevenu WHERE id = ? LIMIT 1");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

if (!$user) {
    unset($_SESSION['reset_password_user_id'], $_SESSION['reset_password_email']);
    header('Location: /mot_de_passe_oublie.php'); exit();
}

/* ============================================================
   TRAITEMENT : RENVOYER UN NOUVEAU CODE (par email, pour prouver l'identité)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_renvoyer'])) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Session expirée, merci de recharger la page.';
    } else {
        $nouveau_code_email = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $nouveau_hash = password_hash($nouveau_code_email, PASSWORD_BCRYPT);
        $nouvelle_expiration = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $resultat = envoyerCodeResetMotDePasse($user['email'], $nouveau_code_email, $user['fullname']);

        if ($resultat['ok']) {
            $pdo->prepare("UPDATE users_monrevenu SET reset_password_code = ?, reset_password_expires_at = ? WHERE id = ?")
                ->execute([$nouveau_hash, $nouvelle_expiration, $user_id]);

            $success = 'Nouveau code envoyé par email !';

            $stmtUser->execute([$user_id]);
            $user = $stmtUser->fetch();
        } else {
            error_log('Erreur renvoi code reset pour ' . $user['email'] . ' : ' . ($resultat['error'] ?? 'Erreur inconnue'));
            $error = "Impossible d'envoyer le code par email. Veuillez réessayer.";
        }
    }
}

/* ============================================================
   TRAITEMENT : VALIDER LE CODE EMAIL ET DÉFINIR UN NOUVEAU CODE SECRET
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reinitialiser'])) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Session expirée, merci de recharger la page.';
    } else {
        $code_email_saisi = trim($_POST['code'] ?? '');
        $nouveau_code      = strtoupper(trim($_POST['nouveau_code'] ?? ''));
        $confirmation_code = strtoupper(trim($_POST['confirmation_code'] ?? ''));

        if (empty($user['reset_password_code']) || empty($user['reset_password_expires_at'])) {
            $error = "Aucun code actif. Merci de redemander un code.";
        } elseif (strtotime($user['reset_password_expires_at']) < time()) {
            $error = "Ce code a expiré. Merci de redemander un code.";
        } elseif (!password_verify($code_email_saisi, $user['reset_password_code'])) {
            $error = "Code incorrect.";
        } elseif (strlen($nouveau_code) !== 4) {
            $error = "Le code secret doit contenir exactement 4 caractères.";
        } else {
            $nb_chiffres = preg_match_all('/[0-9]/', $nouveau_code);
            $nb_lettres  = preg_match_all('/[A-Z]/', $nouveau_code);

            if ($nb_chiffres !== 2 || $nb_lettres !== 2) {
                $error = "Le code secret doit contenir exactement 2 chiffres et 2 lettres.";
            } elseif ($nouveau_code !== $confirmation_code) {
                $error = "La confirmation ne correspond pas au nouveau code secret.";
            } else {
                try {
                    $hash = password_hash($nouveau_code, PASSWORD_BCRYPT, ['cost' => 12]);

                    $pdo->prepare(
                        "UPDATE users_monrevenu SET password = ?, reset_password_code = NULL, reset_password_expires_at = NULL WHERE id = ?"
                    )->execute([$hash, $user_id]);

                    unset($_SESSION['reset_password_user_id'], $_SESSION['reset_password_email']);
                    $_SESSION['flash_success'] = "Votre code secret a été réinitialisé avec succès. Vous pouvez vous connecter.";

                    header('Location: /index.php');
                    exit();
                } catch (\Throwable $e) {
                    error_log('Erreur réinitialisation code secret : ' . $e->getMessage());
                    $error = "Une erreur est survenue. Veuillez réessayer.";
                }
            }
        }
    }
}

function masquerEmailReset(string $email): string
{
    $parties = explode('@', $email);
    if (count($parties) !== 2) return $email;
    $local = $parties[0];
    $masque = mb_substr($local, 0, 2) . str_repeat('•', max(mb_strlen($local) - 2, 2));
    return $masque . '@' . $parties[1];
}
$email_masque = masquerEmailReset($user['email']);
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Réinitialiser le code secret</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sora: ['Sora', 'sans-serif'] },
          colors: { brand: { DEFAULT: '#1246A0', mid: '#1A5FCC', light: '#3B82F6', soft: '#EEF4FF' } }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>body { font-family: 'Sora', sans-serif; }</style>
</head>
<body class="bg-[#F8F9FB] text-slate-900 min-h-screen flex items-center justify-center px-4">

  <div class="w-full max-w-sm">

    <div class="flex flex-col items-center mb-6">
      <div class="w-14 h-14 rounded-2xl bg-brand flex items-center justify-center mb-3">
        <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <h1 class="font-extrabold text-[20px] text-slate-800">Réinitialiser le code secret</h1>
      <p class="text-[13px] text-slate-400 text-center mt-1">
        Un code de vérification à 6 chiffres a été envoyé par email à<br>
        <span class="font-bold text-slate-700"><?= htmlspecialchars($email_masque) ?></span>
      </p>
    </div>

    <div class="bg-white rounded-[24px] shadow-sm border border-slate-100 p-6">

      <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 rounded-xl p-3 mb-4 text-[12px] font-semibold text-center">
          ❌ <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-600 rounded-xl p-3 mb-4 text-[12px] font-semibold text-center">
          ✅ <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="formReset" class="flex flex-col gap-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div>
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2 text-center">Code de vérification (email)</label>
          <input type="text" name="code" required maxlength="6" minlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" autofocus
                 placeholder="000000"
                 class="w-full text-center tracking-[0.5em] font-mono font-extrabold text-[24px] px-4 py-3 rounded-xl border-2 border-slate-200 bg-slate-50 focus:outline-none focus:border-brand transition-all">
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-slate-400 font-medium text-[12px] text-center">Nouveau code secret (2 chiffres + 2 lettres)</label>
          <input type="text" id="nouveau_code" name="nouveau_code" required maxlength="4" minlength="4" autocomplete="new-password"
                 placeholder="Ex: A1B2"
                 style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;"
                 class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-[16px] outline-none focus:border-brand transition-all">
          <span class="text-[11px] text-slate-400 text-center">Exactement 2 chiffres et 2 lettres, dans l'ordre de votre choix (ex: A1B2, 12AB, B4A9).</span>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-slate-400 font-medium text-[12px] text-center">Confirmer le nouveau code secret</label>
          <input type="text" id="confirmation_code" name="confirmation_code" required maxlength="4" minlength="4" autocomplete="new-password"
                 placeholder="Ex: A1B2"
                 style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;"
                 class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-[16px] outline-none focus:border-brand transition-all">
        </div>

        <button type="submit" name="action_reinitialiser"
                class="w-full bg-brand hover:bg-brand-mid text-white font-bold text-[14px] py-3 rounded-xl transition-all shadow-md shadow-blue-500/20">
          Réinitialiser le code secret
        </button>
      </form>

      <form method="POST" action="" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" name="action_renvoyer"
                class="w-full text-brand font-semibold text-[12px] py-2 hover:underline">
          Je n'ai rien reçu — renvoyer le code
        </button>
      </form>
    </div>

  </div>

  <script>
    // Force la saisie en majuscules en direct, comme sur le formulaire d'inscription
    ['nouveau_code', 'confirmation_code'].forEach(function (id) {
      document.getElementById(id).addEventListener('input', function (e) {
        e.target.value = e.target.value.toUpperCase();
      });
    });
  </script>

</body>
</html>