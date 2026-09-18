<?php
session_start();

// 1. Connexion à la base de données (fichier centralisé du projet)
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
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
        $_SESSION['flash_error'] = "Votre session a expiré. Rechargez la page puis recommencez.";
        header('Location: profil.php'); exit();
    }

    // --- 2a. Mise à jour du profil (nom / email) ---
    if (isset($_POST['update_profile'])) {
        $nouveau_nom  = trim($_POST['nom_complet'] ?? '');
        $nouvel_email = trim(strtolower($_POST['email'] ?? ''));

        if ($nouveau_nom === '' || $nouvel_email === '') {
            $_SESSION['flash_error'] = "Indiquez votre nom et votre adresse email.";
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

                $_SESSION['flash_success'] = "Vos informations sont enregistrées.";
            } catch (PDOException $e) {
                // Code 23000 = violation de contrainte d'unicité (email déjà pris)
                if ($e->getCode() === '23000') {
                    $_SESSION['flash_error'] = "Cet email est déjà utilisé par un autre compte.";
                } else {
                    $_SESSION['flash_error'] = "Vos informations n'ont pas pu être enregistrées. Réessayez dans un instant.";
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
            $_SESSION['flash_error'] = "Remplissez les trois champs du code secret.";
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
                    $_SESSION['flash_success'] = "Votre code secret est modifié.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Le code secret n'a pas pu être modifié. Réessayez dans un instant.";
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
                    ? "Saisissez exactement le mot SUPPRIMER pour confirmer."
                    : "Code secret incorrect, la suppression a été annulée.";
                header('Location: profil.php'); exit();
            }

            if ((float) $compte['balance'] > 0) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Il reste " . formaterMontant($compte['balance']) . " sur votre solde. Retirez-le avant de supprimer votre compte.";
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
if (preg_match('/^221(\d{2})(\d{3})(\d{2})(\d{2})$/', $user_phone, $m)) {
    $user_phone_affiche = '+221 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4];
}
$titre_page   = 'Compte';
$scripts_page = ['/assets/js/profil.js'];
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>

    <section class="carte flex items-center gap-4 p-4" aria-label="Identité">
      <span class="avatar avatar-lg"><?= e($user_initials) ?></span>
      <div class="min-w-0">
        <h2 class="truncate text-lg font-semibold"><?= e($user_fullname ?: 'Utilisateur') ?></h2>
        <p class="truncate text-sm text-text-2"><?= e($user_email ?: 'Email non renseigné') ?></p>
        <?php if ($user_pays_nom): ?><p class="text-xs text-text-3">Dernière connexion depuis : <?= e($user_pays_nom) ?></p><?php endif; ?>
      </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
      <form action="" method="POST" id="form-profil" class="carte self-start">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <h2 class="carte-entete carte-titre">Mes informations</h2>
        <div class="flex flex-col gap-4 p-4">
          <div class="champ">
            <label class="champ-label" for="nom_complet">Nom complet</label>
            <input class="champ-saisie" type="text" id="nom_complet" name="nom_complet" value="<?= e($user_fullname) ?>" required autocomplete="name" maxlength="100">
          </div>
          <div class="champ">
            <label class="champ-label" for="email">Adresse email</label>
            <input class="champ-saisie" type="email" id="email" name="email" value="<?= e($user_email) ?>" required autocomplete="email" inputmode="email">
          </div>
          <?php if ($user_phone_affiche): ?>
            <div class="champ">
              <span class="champ-label">Numéro WhatsApp</span>
              <p class="flex h-12 items-center justify-between gap-3 rounded border border-line bg-surface-2 px-3 lg:h-11">
                <span class="chiffres text-text-2"><?= e($user_phone_affiche) ?></span>
                <span class="pastille pastille-succes">Vérifié</span>
              </p>
              <p class="champ-aide">Le numéro sert à la connexion et ne peut pas être modifié ici.</p>
            </div>
          <?php endif; ?>
          <button type="submit" name="update_profile" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Enregistrer</span></button>
        </div>
      </form>

      <form action="" method="POST" id="form-password" class="carte self-start">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <h2 class="carte-entete carte-titre">Code secret</h2>
        <div class="flex flex-col gap-4 p-4">
          <div class="champ">
            <label class="champ-label" for="mot_de_passe_actuel">Code secret actuel</label>
            <input class="champ-saisie font-mono uppercase tracking-[.3em]" type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required maxlength="4" autocomplete="current-password">
          </div>
          <div class="champ">
            <label class="champ-label" for="nouveau_mot_de_passe">Nouveau code secret</label>
            <input class="champ-saisie font-mono uppercase tracking-[.3em]" type="text" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required maxlength="4" autocomplete="new-password" autocapitalize="characters" placeholder="A1B2" aria-describedby="aide-code-secret">
            <p class="champ-aide" id="aide-code-secret">Exactement 2 chiffres et 2 lettres. Exemple : A1B2.</p>
          </div>
          <div class="champ">
            <label class="champ-label" for="confirmation_mot_de_passe">Confirmer le nouveau code</label>
            <input class="champ-saisie font-mono uppercase tracking-[.3em]" type="text" id="confirmation_mot_de_passe" name="confirmation_mot_de_passe" required maxlength="4" autocomplete="new-password" autocapitalize="characters" placeholder="A1B2" aria-describedby="err-confirmation-code">
            <p class="champ-erreur" id="err-confirmation-code" hidden><?= ico('circle-alert', 'ico-16 mt-0.5') ?><span>Les deux codes sont différents.</span></p>
          </div>
          <button type="submit" name="update_password" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Modifier le code secret</span></button>
        </div>
      </form>
    </div>

    <section id="supprimer-compte" class="carte scroll-mt-20 border-danger/40" aria-labelledby="t-suppression">
      <h2 id="t-suppression" class="carte-entete carte-titre text-danger">Supprimer mon compte</h2>
      <div class="flex flex-col gap-3 p-4 text-sm text-text-2">
        <p>La suppression efface votre nom, votre email et votre numéro. L'historique des ventes et des retraits est conservé sans lien avec votre identité. Cette action est définitive.</p>
        <p>Avant de supprimer : retirez votre solde et attendez la fin de toute demande de retrait en cours. <a class="lien" href="/suppression-donnees.php">En savoir plus</a></p>
        <button type="button" class="btn btn-secondaire self-start text-danger" data-ouvrir="feuille-suppression"><?= ico('trash-2') ?>Supprimer mon compte</button>
      </div>
    </section>

    <dialog class="feuille" id="feuille-suppression" aria-labelledby="titre-suppression">
      <div class="poignee"></div>
      <div class="feuille-entete">
        <h2 class="feuille-titre" id="titre-suppression">Confirmer la suppression</h2>
        <button type="button" class="btn btn-icone btn-discret" data-fermer aria-label="Fermer"><?= ico('x') ?></button>
      </div>
      <form action="" method="POST" id="form-suppression" class="flex flex-col">
        <div class="feuille-corps flex flex-col gap-4">
          <p class="alerte alerte-danger"><?= ico('circle-alert') ?><span>Votre compte MonRevenu sera supprimé définitivement. Vous ne pourrez plus vous connecter.</span></p>
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
          <?php if ($est_compte_google): ?>
            <div class="champ">
              <label class="champ-label" for="confirmation_mot_cle">Saisissez SUPPRIMER pour confirmer</label>
              <input class="champ-saisie font-mono" type="text" id="confirmation_mot_cle" name="confirmation_mot_cle" required autocomplete="off" autocapitalize="characters" autofocus>
            </div>
          <?php else: ?>
            <div class="champ">
              <label class="champ-label" for="suppression_mot_de_passe">Votre code secret</label>
              <input class="champ-saisie" type="password" id="suppression_mot_de_passe" name="confirmation_mot_de_passe" required autocomplete="current-password" autofocus>
            </div>
          <?php endif; ?>
        </div>
        <div class="feuille-pied">
          <button type="button" class="btn btn-secondaire" data-fermer>Annuler</button>
          <button type="submit" name="action_supprimer_compte" class="btn btn-danger"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Supprimer définitivement</span></button>
        </div>
      </form>
    </dialog>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
