<?php
session_start();

// 1. Connexion à la base de données (fichier centralisé du projet)
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/geoip.php';

// Vérification de la sécurité de session
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /index.php'); exit();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /index.php'); exit();
}

// --- Jeton CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Messages flash (survivent à la redirection PRG) ---
$message_success = $_SESSION['flash_success'] ?? '';
$message_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/**
 * Petite fonction utilitaire de validation du nom.
 */
function nom_est_valide(string $nom): bool {
    return (bool) preg_match("/^[\p{L}\p{M}' \-]{2,80}$/u", $nom);
}

/**
 * Valide le format du code secret : exactement 4 caractères,
 * 2 chiffres et 2 lettres (même règle qu'à l'inscription).
 */
function code_secret_valide(string $code): bool {
    if (strlen($code) !== 4) return false;
    $nb_chiffres = preg_match_all('/[0-9]/', $code);
    $nb_lettres  = preg_match_all('/[A-Z]/', $code);
    return $nb_chiffres === 2 && $nb_lettres === 2;
}

// 2. TRAITEMENT DU FORMULAIRE (POST + redirection pour éviter le renvoi de formulaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérification CSRF commune à tous les sous-formulaires de cette page
    $token_recu = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token_recu)) {
        $_SESSION['flash_error'] = "Session expirée, merci de réessayer.";
        header('Location: profil.php'); exit();
    }

    // --- 2a. Mise à jour du profil (nom / email) ---
    if (isset($_POST['update_profile'])) {
        $nouveau_nom  = trim($_POST['nom_complet'] ?? '');
        $nouvel_email = trim(strtolower($_POST['email'] ?? ''));

        if ($nouveau_nom === '' || $nouvel_email === '') {
            $_SESSION['flash_error'] = "Veuillez remplir tous les champs.";
        } elseif (!nom_est_valide($nouveau_nom)) {
            $_SESSION['flash_error'] = "Le nom saisi n'est pas valide.";
        } elseif (!filter_var($nouvel_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "L'adresse email n'est pas valide.";
        } else {
            try {
                $update = $pdo->prepare("UPDATE users_monrevenu SET fullname = ?, email = ? WHERE id = ?");
                $update->execute([$nouveau_nom, $nouvel_email, $user_id]);

                // Met à jour la session pour que navbar.php affiche le nouveau nom sans reconnexion
                $_SESSION['user_fullname'] = $nouveau_nom;
                $_SESSION['user_email']    = $nouvel_email;

                $_SESSION['flash_success'] = "Profil mis à jour avec succès !";
            } catch (PDOException $e) {
                // Code 23000 = violation de contrainte d'unicité (email déjà pris)
                if ($e->getCode() === '23000') {
                    $_SESSION['flash_error'] = "Cet email est déjà utilisé par un autre compte.";
                } else {
                    $_SESSION['flash_error'] = "Une erreur est survenue lors de la mise à jour.";
                }
            }
        }

        header('Location: profil.php'); exit();
    }

    // --- 2b. Changement du code secret ---
    if (isset($_POST['update_password'])) {
        $code_actuel  = strtoupper(trim($_POST['mot_de_passe_actuel'] ?? ''));
        $code_nouveau = strtoupper(trim($_POST['nouveau_mot_de_passe'] ?? ''));
        $code_confirm = strtoupper(trim($_POST['confirmation_mot_de_passe'] ?? ''));

        if ($code_actuel === '' || $code_nouveau === '' || $code_confirm === '') {
            $_SESSION['flash_error'] = "Veuillez remplir tous les champs du code secret.";
        } elseif (!code_secret_valide($code_nouveau)) {
            $_SESSION['flash_error'] = "Le nouveau code doit contenir exactement 2 chiffres et 2 lettres (ex: A1B2).";
        } elseif ($code_nouveau !== $code_confirm) {
            $_SESSION['flash_error'] = "La confirmation ne correspond pas au nouveau code.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users_monrevenu WHERE id = ?");
                $stmt->execute([$user_id]);
                $row = $stmt->fetch();

                if (!$row || !password_verify($code_actuel, $row['password'])) {
                    $_SESSION['flash_error'] = "Le code secret actuel est incorrect.";
                } else {
                    $hash = password_hash($code_nouveau, PASSWORD_BCRYPT, ['cost' => 12]);
                    $upd  = $pdo->prepare("UPDATE users_monrevenu SET password = ? WHERE id = ?");
                    $upd->execute([$hash, $user_id]);
                    $_SESSION['flash_success'] = "Code secret mis à jour avec succès !";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Une erreur est survenue lors du changement de code.";
            }
        }

        header('Location: profil.php'); exit();
    }

    // --- 2c. Suppression du compte ---
    if (isset($_POST['action_supprimer_compte'])) {

        try {
            $pdo->beginTransaction();

            $stmtLock = $pdo->prepare(
                "SELECT balance, password, google_id, phone FROM users_monrevenu WHERE id = ? FOR UPDATE"
            );
            $stmtLock->execute([$user_id]);
            $compte = $stmtLock->fetch();

            if (!$compte) {
                $pdo->rollBack();
                header('Location: /index.php'); exit();
            }

            // Confirmation : mot de passe pour un compte classique, mot-clé
            // "SUPPRIMER" pour un compte Google Sign-In (mot de passe local
            // inutilisable, généré aléatoirement à la création du compte).
            $est_compte_google = !empty($compte['google_id']);

            if ($est_compte_google) {
                $confirmation_ok = trim($_POST['confirmation_mot_cle'] ?? '') === 'SUPPRIMER';
                $type_suppression = 'mot_cle_google';
            } else {
                $confirmation_ok = password_verify($_POST['confirmation_mot_de_passe'] ?? '', $compte['password']);
                $type_suppression = 'mot_de_passe';
            }

            if (!$confirmation_ok) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = $est_compte_google
                    ? "Merci de saisir exactement le mot SUPPRIMER pour confirmer."
                    : "Code secret incorrect, la suppression a été annulée.";
                header('Location: profil.php'); exit();
            }

            if ((float) $compte['balance'] > 0) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Vous avez un solde de " . number_format((float) $compte['balance'], 0, ',', ' ') . " KMF non retiré. Retirez-le avant de supprimer votre compte.";
                header('Location: profil.php'); exit();
            }

            $stmtRetrait = $pdo->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = 'en_attente'");
            $stmtRetrait->execute([$user_id]);
            if ((int) $stmtRetrait->fetchColumn() > 0) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Vous avez une demande de retrait en cours de traitement. Attendez qu'elle soit traitée avant de supprimer votre compte.";
                header('Location: profil.php'); exit();
            }

            // Anonymisation en place : l'id reste stable, donc toutes les lignes
            // à valeur comptable (vendeur_ventes, ventes_stock, transactions_monrevenu,
            // withdrawals, agent_commissions...) restent intactes et continuent de
            // pointer vers cette même ligne, désormais anonymisée — rien à modifier
            // dans ces tables. status='deleted' + is_active=0 : le numéro/email
            // redeviennent utilisables pour une nouvelle inscription (déjà géré par
            // register_handler.php, qui exclut status='deleted' de la vérification
            // de doublon) et produit.php refuse désormais de créditer un ref vers
            // un compte is_active=0/status!='active'.
            $email_anonyme = 'compte-supprime-' . $user_id . '@monrevenu.invalid';
            $phone_anonyme = 'suppr-' . $user_id;
            $mot_de_passe_verrouille = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

            $stmtAnonymise = $pdo->prepare(
                "UPDATE users_monrevenu SET
                    fullname = 'Compte supprimé',
                    email = ?,
                    phone = ?,
                    password = ?,
                    google_id = NULL,
                    verification_code = NULL,
                    code_expires_at = NULL,
                    code_sent_at = NULL,
                    whatsapp_verif_code = NULL,
                    whatsapp_verif_expire_at = NULL,
                    whatsapp_verif_numero = NULL,
                    reset_password_code = NULL,
                    reset_password_expires_at = NULL,
                    status = 'deleted',
                    is_active = 0
                 WHERE id = ?"
            );
            $stmtAnonymise->execute([$email_anonyme, $phone_anonyme, $mot_de_passe_verrouille, $user_id]);

            // Jetons techniques liés à l'appareil : suppression réelle (pas de valeur comptable).
            $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ?")->execute([$user_id]);

            if (!empty($compte['phone'])) {
                $pdo->prepare("DELETE FROM login_attempts_compte WHERE phone = ?")->execute([$compte['phone']]);
            }

            // Journal d'audit, sans donnée personnelle (voir MIGRATION_suppression_compte.sql).
            // Non bloquant : si la table n'existe pas encore, on continue quand même la suppression.
            try {
                $pdo->prepare(
                    "INSERT INTO journal_suppressions_compte (compte_id, type_suppression) VALUES (?, ?)"
                )->execute([$user_id, $type_suppression]);
            } catch (PDOException $e) {
                error_log('journal_suppressions_compte (table absente ?) : ' . $e->getMessage());
            }

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erreur suppression compte : ' . $e->getMessage());
            $_SESSION['flash_error'] = "Une erreur est survenue, votre compte n'a pas été supprimé. Réessayez.";
            header('Location: profil.php'); exit();
        }

        // Destruction complète de la session en cours.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        header('Location: /index.php?success=compte_supprime'); exit();
    }
}

// 3. RÉCUPÉRATION DES DONNÉES EN DIRECT DEPUIS LA BDD
try {
    $stmt = $pdo->prepare("SELECT fullname, email, phone, pays_code, pays_nom, google_id FROM users_monrevenu WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $user = false;
}

// Valeurs de secours : priorité à la BDD (fraîche), puis à la session
$user_fullname = $user['fullname'] ?? $_SESSION['user_fullname'] ?? '';
$user_email    = $user['email'] ?? $_SESSION['user_email'] ?? '';
$user_pays_code = $user['pays_code'] ?? null;
$user_pays_nom  = $user['pays_nom'] ?? null;
$user_phone    = $user['phone'] ?? '';
$est_compte_google = !empty($user['google_id']);

// Affichage lisible du numéro comorien : +269 XX XX XXX
$user_phone_affiche = $user_phone;
if (preg_match('/^269(\d{7})$/', $user_phone, $m)) {
    $user_phone_affiche = '+269 ' . $m[1];
}

// Initiales robustes : prend la première lettre de chaque mot du nom (max 2)
$mots = preg_split('/\s+/', trim($user_fullname), -1, PREG_SPLIT_NO_EMPTY);
if (count($mots) >= 2) {
    $user_initials = mb_strtoupper(mb_substr($mots[0], 0, 1) . mb_substr($mots[1], 0, 1));
} elseif (count($mots) === 1) {
    $user_initials = mb_strtoupper(mb_substr($mots[0], 0, 2));
} else {
    $user_initials = 'U';
}
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MonRevenu – Profil</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    (function () {
      var theme = localStorage.getItem('theme');
      if (theme === 'dark') document.documentElement.classList.add('dark');
      else document.documentElement.classList.remove('dark');
    })();
    tailwind.config={
      darkMode:'class',
      theme:{
        extend:{
          fontFamily:{sora:['Sora','sans-serif']},
          colors:{brand:{DEFAULT:'#1246A0',mid:'#1A5FCC',light:'#3B82F6',soft:'#EEF4FF'}}
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css" rel="stylesheet"/>
  <style>body{font-family:'Sora',sans-serif;}</style>
</head>
<body class="bg-[#F8F9FB] dark:bg-[#0B1120] text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-300">

<div class="min-h-screen flex flex-col pb-24 lg:pl-64">

<header class="bg-transparent px-4 lg:px-6 pt-6 pb-2 flex items-center justify-between max-w-2xl w-full mx-auto">
    <div class="flex items-center gap-3">
      <!-- Bouton menu burger, visible uniquement sur mobile/tablette -->
      <button onclick="toggleSidebar()" type="button" aria-label="Ouvrir le menu"
              class="lg:hidden w-10 h-10 rounded-full bg-white dark:bg-[#141E33] shadow-sm flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0">
        <svg class="w-5 h-5 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6" x2="21" y2="6"/>
          <line x1="3" y1="12" x2="21" y2="12"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>

      <!-- Bouton retour -->
      <a href="/dashboard.php" aria-label="Retour au dashboard"
         class="w-10 h-10 rounded-full bg-white dark:bg-[#141E33] shadow-sm flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0 hover:border-brand/40 transition-colors">
        <svg class="w-5 h-5 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
      </a>

      <h1 class="font-bold text-[20px] text-slate-800 dark:text-white">Mon Profil</h1>
    </div>
    <button onclick="toggleTheme()" type="button" aria-label="Changer le thème" class="w-10 h-10 rounded-full bg-white dark:bg-[#141E33] shadow-sm flex items-center justify-center border border-slate-100 dark:border-slate-800 shrink-0">
      <svg class="w-4 h-4 text-slate-700 dark:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg>
    </button>
  </header>

  <main class="flex-1 px-4 lg:px-6 py-4 max-w-2xl w-full mx-auto flex flex-col gap-4">

    <?php if (!empty($message_success)): ?>
      <div role="status" class="p-4 bg-emerald-500/10 text-emerald-500 text-[13px] font-medium rounded-2xl border border-emerald-500/20 text-center">
        <?= htmlspecialchars($message_success) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($message_error)): ?>
      <div role="alert" class="p-4 bg-red-500/10 text-red-500 text-[13px] font-medium rounded-2xl border border-red-500/20 text-center">
        <?= htmlspecialchars($message_error) ?>
      </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-[#141E33] rounded-[24px] p-6 text-center shadow-sm border border-slate-100/70 dark:border-slate-800/50">
      <div class="w-20 h-20 rounded-full bg-[#EEF4FF] dark:bg-slate-800 flex items-center justify-center font-bold text-[24px] text-[#1246A0] dark:text-blue-400 mx-auto mb-3 tracking-wider shadow-inner">
        <?= htmlspecialchars($user_initials) ?>
      </div>
      <h2 class="font-bold text-[17px] text-slate-800 dark:text-white flex items-center justify-center gap-2">
        <?= htmlspecialchars($user_fullname ?: 'Utilisateur') ?>
        <?php if ($user_pays_code): ?>
          <?= drapeauHtml($user_pays_code, 'text-[16px] rounded-sm') ?>
        <?php endif; ?>
      </h2>
      <?php if ($user_pays_nom): ?>
        <p class="text-[11px] text-slate-400 -mt-0.5">Connecté depuis : <?= htmlspecialchars($user_pays_nom) ?></p>
      <?php endif; ?>
      <p class="text-[12px] text-slate-400 mt-0.5"><?= htmlspecialchars($user_email ?: 'Email non renseigné') ?></p>
      <?php if ($user_phone_affiche): ?>
        <p class="text-[12px] text-slate-400 mt-0.5 flex items-center justify-center gap-1.5">
          <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.21 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <?= htmlspecialchars($user_phone_affiche) ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Formulaire : informations du profil -->
    <form action="" method="POST" id="form-profil" class="bg-white dark:bg-[#141E33] rounded-[24px] p-5 shadow-sm border border-slate-100/70 dark:border-slate-800/50 flex flex-col gap-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <h3 class="font-bold text-[14px] text-slate-800 dark:text-white border-b border-slate-50 dark:border-slate-800/50 pb-2">Modifier mes détails</h3>

      <div class="flex flex-col gap-1.5">
        <label for="nom_complet" class="text-slate-400 font-medium text-[12px]">Nom complet</label>
        <input type="text" id="nom_complet" name="nom_complet" value="<?= htmlspecialchars($user_fullname) ?>" required
               minlength="2" maxlength="80"
               class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 text-[13px] text-slate-800 dark:text-slate-100 font-medium outline-none focus:border-[#1246A0] dark:focus:border-blue-500 transition-all">
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="email" class="text-slate-400 font-medium text-[12px]">Adresse Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_email) ?>" required
               class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 text-[13px] text-slate-800 dark:text-slate-100 font-medium outline-none focus:border-[#1246A0] dark:focus:border-blue-500 transition-all">
      </div>

      <?php if ($user_phone_affiche): ?>
      <div class="flex flex-col gap-1.5">
        <label class="text-slate-400 font-medium text-[12px]">Numéro de téléphone</label>
        <div class="bg-slate-100 dark:bg-slate-800 border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 text-[13px] text-slate-500 dark:text-slate-400 font-medium flex items-center justify-between">
          <?= htmlspecialchars($user_phone_affiche) ?>
          <span class="text-[10px] font-bold uppercase tracking-wide bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-full">Vérifié</span>
        </div>
        <span class="text-[11px] text-slate-400">Le numéro sert à la connexion et ne peut pas être modifié ici — contactez le support si besoin.</span>
      </div>
      <?php endif; ?>

      <button type="submit" name="update_profile"
              class="btn-submit mt-2 bg-[#1246A0] hover:bg-opacity-90 text-white font-bold text-[13px] py-3.5 px-4 rounded-xl shadow-sm active:scale-[0.98] transition-all disabled:opacity-60">
        Enregistrer les modifications
      </button>
    </form>

    <!-- Formulaire : changement du code secret -->
    <form action="" method="POST" id="form-password" class="bg-white dark:bg-[#141E33] rounded-[24px] p-5 shadow-sm border border-slate-100/70 dark:border-slate-800/50 flex flex-col gap-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <h3 class="font-bold text-[14px] text-slate-800 dark:text-white border-b border-slate-50 dark:border-slate-800/50 pb-2">Changer mon code secret</h3>

      <div class="flex flex-col gap-1.5">
        <label for="mot_de_passe_actuel" class="text-slate-400 font-medium text-[12px]">Code secret actuel</label>
        <input type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required maxlength="4" autocomplete="current-password"
               style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;"
               class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 text-[15px] text-slate-800 dark:text-slate-100 outline-none focus:border-[#1246A0] dark:focus:border-blue-500 transition-all">
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="nouveau_mot_de_passe" class="text-slate-400 font-medium text-[12px]">Nouveau code secret</label>
        <input type="text" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required maxlength="4" autocomplete="new-password" placeholder="A1B2"
               style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;"
               class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 text-[15px] text-slate-800 dark:text-slate-100 outline-none focus:border-[#1246A0] dark:focus:border-blue-500 transition-all">
        <span class="text-[11px] text-slate-400">Exactement 2 chiffres et 2 lettres (ex: A1B2, 12AB).</span>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="confirmation_mot_de_passe" class="text-slate-400 font-medium text-[12px]">Confirmer le nouveau code</label>
        <input type="text" id="confirmation_mot_de_passe" name="confirmation_mot_de_passe" required maxlength="4" autocomplete="new-password" placeholder="A1B2"
               style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;"
               class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 text-[15px] text-slate-800 dark:text-slate-100 outline-none focus:border-[#1246A0] dark:focus:border-blue-500 transition-all">
      </div>

      <button type="submit" name="update_password"
              class="btn-submit mt-2 bg-slate-800 hover:bg-opacity-90 text-white font-bold text-[13px] py-3.5 px-4 rounded-xl shadow-sm active:scale-[0.98] transition-all disabled:opacity-60">
        Mettre à jour le code secret
      </button>
    </form>

    <!-- Zone de danger : suppression du compte -->
    <section id="supprimer-compte" class="bg-white dark:bg-[#141E33] rounded-[24px] p-5 shadow-sm border border-red-200 dark:border-red-500/30 flex flex-col gap-3">
      <h3 class="font-bold text-[14px] text-red-600 border-b border-red-100 dark:border-red-500/20 pb-2">Supprimer mon compte</h3>
      <p class="text-[12px] text-slate-500 dark:text-slate-400 leading-relaxed">
        Cette action est <strong>irréversible</strong>. Vos informations personnelles (nom, email, téléphone, vérification WhatsApp)
        seront effacées et votre session fermée immédiatement. Vos ventes, commissions et retraits déjà effectués sont conservés,
        mais dissociés de votre identité, pour des raisons comptables et légales.
        Plus de détails sur <a href="/suppression-donnees.php" class="underline hover:text-red-600">la page de suppression des données</a>.
      </p>
      <p class="text-[12px] text-amber-600 dark:text-amber-400 font-medium">
        La suppression est refusée si votre solde n'est pas nul ou si une demande de retrait est en cours de traitement.
      </p>

      <form action="" method="POST" id="form-suppression" class="flex flex-col gap-3 mt-1" onsubmit="return confirm('Supprimer définitivement votre compte MonRevenu ? Cette action est irréversible.');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <?php if ($est_compte_google): ?>
          <div class="flex flex-col gap-1.5">
            <label for="confirmation_mot_cle" class="text-slate-400 font-medium text-[12px]">
              Tapez <strong>SUPPRIMER</strong> pour confirmer (compte connecté via Google, pas de code secret local)
            </label>
            <input type="text" id="confirmation_mot_cle" name="confirmation_mot_cle" required
                   placeholder="SUPPRIMER"
                   class="bg-slate-50 dark:bg-slate-900 border border-red-200 dark:border-red-500/30 rounded-xl px-4 py-3 text-[13px] text-slate-800 dark:text-slate-100 font-semibold outline-none focus:border-red-500 transition-all">
          </div>
        <?php else: ?>
          <div class="flex flex-col gap-1.5">
            <label for="confirmation_mot_de_passe" class="text-slate-400 font-medium text-[12px]">Confirmez avec votre code secret</label>
            <input type="password" id="confirmation_mot_de_passe" name="confirmation_mot_de_passe" required autocomplete="current-password"
                   style="text-transform:uppercase; letter-spacing:0.3em; text-align:center; font-weight:700;"
                   class="bg-slate-50 dark:bg-slate-900 border border-red-200 dark:border-red-500/30 rounded-xl px-4 py-3 text-[15px] text-slate-800 dark:text-slate-100 outline-none focus:border-red-500 transition-all">
          </div>
        <?php endif; ?>

        <button type="submit" name="action_supprimer_compte"
                class="btn-submit mt-1 bg-red-600 hover:bg-red-700 text-white font-bold text-[13px] py-3.5 px-4 rounded-xl shadow-sm active:scale-[0.98] transition-all disabled:opacity-60">
          Supprimer définitivement mon compte
        </button>
      </form>
    </section>

  </main>

  <?php include __DIR__ . '/../sections/navbar.php'; ?>
</div>

<script>
function toggleTheme(){
  document.documentElement.classList.toggle('dark');
  localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
}

// Force la saisie en majuscules en direct pour les champs de code secret
['mot_de_passe_actuel', 'nouveau_mot_de_passe', 'confirmation_mot_de_passe'].forEach(function (id) {
  var champ = document.getElementById(id);
  if (champ) {
    champ.addEventListener('input', function (e) {
      e.target.value = e.target.value.toUpperCase();
    });
  }
});

// Empêche le double envoi et donne un retour visuel pendant la soumission
document.querySelectorAll('form').forEach(function (form) {
  form.addEventListener('submit', function () {
    var btn = form.querySelector('.btn-submit');
    if (btn) {
      btn.disabled = true;
      btn.dataset.label = btn.textContent;
      btn.textContent = 'Enregistrement...';
    }
  });
});

// Vérifie côté client que les deux codes correspondent avant l'envoi
var formPassword = document.getElementById('form-password');
if (formPassword) {
  formPassword.addEventListener('submit', function (e) {
    var nouveau = document.getElementById('nouveau_mot_de_passe').value;
    var confirmation = document.getElementById('confirmation_mot_de_passe').value;
    if (nouveau !== confirmation) {
      e.preventDefault();
      var btn = formPassword.querySelector('.btn-submit');
      if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label; }
      alert('La confirmation ne correspond pas au nouveau code secret.');
    }
  });
}
</script>
</body>
</html>