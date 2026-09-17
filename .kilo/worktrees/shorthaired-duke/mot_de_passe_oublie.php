<?php
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/email_sender.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_demander_reset'])) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Merci de saisir une adresse email valide.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, fullname, email, is_active, status FROM users_monrevenu WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // On ne révèle jamais si l'email existe ou non (protection contre
                // l'énumération de comptes) : le message est identique dans tous les cas.
                if ($user && (int) $user['is_active'] === 1 && $user['status'] === 'active') {
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $code_hash = password_hash($code, PASSWORD_BCRYPT);
                    $expiration = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    $pdo->prepare("UPDATE users_monrevenu SET reset_password_code = ?, reset_password_expires_at = ? WHERE id = ?")
                        ->execute([$code_hash, $expiration, $user['id']]);

                    $resultat = envoyerCodeResetMotDePasse($user['email'], $code, $user['fullname']);

                    if ($resultat['ok']) {
                        $_SESSION['reset_password_user_id'] = $user['id'];
                        $_SESSION['reset_password_email']   = $user['email'];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        header('Location: reinitialiser_mot_de_passe.php');
                        exit();
                    } else {
                        error_log('Erreur envoi email reset pour ' . $email . ' : ' . ($resultat['error'] ?? 'Erreur inconnue'));
                        $error = "Une erreur est survenue lors de l'envoi. Merci de réessayer.";
                    }
                } else {
                    // Compte inexistant, inactif ou bloqué : même message que le cas normal
                    $success = "Si un compte existe avec cette adresse, un code de réinitialisation vient d'être envoyé par email.";
                }
            } catch (\Throwable $e) {
                error_log('Erreur mot de passe oublié : ' . $e->getMessage());
                $error = "Une erreur est survenue. Merci de réessayer.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Mot de passe oublié</title>
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
      <h1 class="font-extrabold text-[20px] text-slate-800">Mot de passe oublié</h1>
      <p class="text-[13px] text-slate-400 text-center mt-1">
        Entrez votre adresse email, on vous envoie un code pour réinitialiser votre mot de passe.
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

      <?php if (!$success): ?>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2">Adresse email</label>
        <input type="email" name="email" required autofocus
               placeholder="exemple@domaine.com"
               class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 bg-slate-50 text-[14px] focus:outline-none focus:border-brand transition-all mb-4">

        <button type="submit" name="action_demander_reset"
                class="w-full bg-brand hover:bg-brand-mid text-white font-bold text-[14px] py-3 rounded-xl transition-all shadow-md shadow-blue-500/20">
          Envoyer le code
        </button>
      </form>
      <?php endif; ?>

      <div class="bottom-note text-center mt-4">
        <a href="/index.php" class="text-[12px] text-brand font-semibold hover:underline">← Retour à la connexion</a>
      </div>
    </div>

  </div>

</body>
</html>