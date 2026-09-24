<?php
session_start();

// 1. Connexion à la base de données (fichier centralisé du projet)
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/../includs/audit.php';
require_once __DIR__ . '/../includs/incident.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/geoip.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/mot_de_passe.php';

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

// 2. TRAITEMENT DU FORMULAIRE (POST + redirection pour éviter le renvoi de formulaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérification CSRF commune à tous les sous-formulaires de cette page
    $token_recu = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token_recu)) {
        $_SESSION['flash_error'] = "Votre session a expiré. Rechargez la page puis recommencez.";
        auditCsrf($pdo, 'profil');
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
                $stAvant = $pdo->prepare("SELECT fullname, email FROM users_monrevenu WHERE id = ?");
                $stAvant->execute([$user_id]);
                $avant = $stAvant->fetch(PDO::FETCH_ASSOC) ?: [];
                $update = $pdo->prepare("UPDATE users_monrevenu SET fullname = ?, email = ? WHERE id = ?");
                $update->execute([$nouveau_nom, $nouvel_email, $user_id]);
                [$b, $a] = auditDiff($avant, ['fullname' => $nouveau_nom, 'email' => $nouvel_email]);
                if ($a) {
                    auditInfo($pdo, ['category' => 'compte', 'action' => 'profil_modification', 'entity_type' => 'utilisateur', 'entity_id' => $user_id,
                        'before' => $b, 'after' => $a]);
                }

                // Met à jour la session pour que navbar.php affiche le nouveau nom sans reconnexion
                $_SESSION['user_fullname'] = $nouveau_nom;
                $_SESSION['user_email']    = $nouvel_email;

                $_SESSION['flash_success'] = "Vos informations sont enregistrées.";
            } catch (PDOException $e) {
                // Code 23000 = violation de contrainte d'unicité (email déjà pris)
                if ($e->getCode() === '23000') {
                    $_SESSION['flash_error'] = "Cet email est déjà utilisé par un autre compte.";
                } else {
                    $_SESSION['flash_error'] = messageIncident(incidentEnregistrer($pdo, $e, 'profil/informations'),
                        "Vos informations n'ont pas pu être enregistrées. Réessayez dans un instant.");
                }
            }
        }

        header('Location: profil.php'); exit();
    }

    // --- 2a. Compte de reception des commissions (operateur mobile money + numero) ---
    // Paiement manuel : l'information dit a l'administration ou virer. Reserve aux comptes dont le numero est verifie.
    if (isset($_POST['update_paiement'])) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/moyen_paiement.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/notifications.php';
        try {
            $st = $pdo->prepare("SELECT pays_code, phone, google_id, password, role FROM users_monrevenu WHERE id = ?");
            $st->execute([$user_id]);
            $compte_p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $marche_p = marcheDeCompte($compte_p ?: null);
            if (in_array($compte_p['role'] ?? '', ['commercant', 'admin'], true)) {
                $_SESSION['flash_error'] = "Ce compte n'a pas de commissions à recevoir.";
            } elseif (!compteVerifie($pdo, $user_id)) {
                $_SESSION['flash_error'] = "Vérifiez d'abord votre numéro pour enregistrer votre compte de réception.";
            } elseif (empty($compte_p['google_id']) && !password_verify(trim((string) ($_POST['mot_de_passe_paiement'] ?? '')), (string) ($compte_p['password'] ?? ''))) {
                // Changer l'endroit ou l'argent est envoye est sensible : le mot de passe est redemande (sauf compte Google, sans mot de passe utilisable)
                $_SESSION['flash_error'] = 'Le mot de passe est incorrect.';
                auditInfo($pdo, ['category' => 'compte', 'action' => 'moyen_paiement_echec', 'result' => 'echec', 'entity_type' => 'utilisateur', 'entity_id' => $user_id]);
            } else {
                moyenPaiementSauvegarder($pdo, (int) $user_id, $marche_p, (string) ($_POST['operateur'] ?? ''), (string) ($_POST['numero_paiement'] ?? ''));
                $_SESSION['flash_success'] = 'Votre compte de réception des commissions est enregistré.';
                envoyerNotification($pdo, (int) $user_id, "Votre compte de réception des commissions a été " . 'enregistré : ' . trim((string) ($_POST['operateur'] ?? '')) . '. Si ce n\'est pas vous, changez votre mot de passe.', 'Compte de réception', '/page/profil.php');
            }
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = messageIncident(incidentEnregistrer($pdo, $e, 'profil/moyen_paiement'), "Le compte de réception n'a pas pu être enregistré. Réessayez dans un instant.");
        }
        header('Location: profil.php'); exit();
    }

    // --- 2b. Changement du mot de passe ---
    if (isset($_POST['update_password'])) {
        $code_actuel  = trim((string) ($_POST['mot_de_passe_actuel'] ?? ''));
        $code_nouveau = trim((string) ($_POST['nouveau_mot_de_passe'] ?? ''));
        $code_confirm = trim((string) ($_POST['confirmation_mot_de_passe'] ?? ''));

        if ($code_actuel === '' || $code_nouveau === '' || $code_confirm === '') {
            $_SESSION['flash_error'] = "Remplissez les trois champs du mot de passe.";
        } elseif (($erreur_mdp = erreurMotDePasse($code_nouveau)) !== null) {
            $_SESSION['flash_error'] = $erreur_mdp;
        } elseif ($code_nouveau !== $code_confirm) {
            $_SESSION['flash_error'] = "La confirmation ne correspond pas au nouveau mot de passe.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users_monrevenu WHERE id = ?");
                $stmt->execute([$user_id]);
                $row = $stmt->fetch();

                if (!$row || !password_verify($code_actuel, $row['password'])) {
                    $_SESSION['flash_error'] = "Le mot de passe actuel est incorrect.";
                    auditInfo($pdo, ['category' => 'auth', 'action' => 'mot_de_passe_changement_echec', 'result' => 'echec',
                        'entity_type' => 'utilisateur', 'entity_id' => $user_id]);
                } else {
                    $hash = password_hash($code_nouveau, PASSWORD_BCRYPT, ['cost' => 12]);
                    $upd  = $pdo->prepare("UPDATE users_monrevenu SET password = ? WHERE id = ?");
                    $upd->execute([$hash, $user_id]);
                    auditInfo($pdo, ['category' => 'auth', 'action' => 'mot_de_passe_changement', 'entity_type' => 'utilisateur', 'entity_id' => $user_id]);
                    $_SESSION['flash_success'] = "Votre mot de passe est modifié.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = messageIncident(incidentEnregistrer($pdo, $e, 'profil/mot_de_passe'),
                    "Le mot de passe n'a pas pu être modifié. Réessayez dans un instant.");
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
                    : "Mot de passe incorrect, la suppression a été annulée.";
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
            // pointer vers cette même ligne, désormais anonymisée, rien à modifier
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

            auditCritique($pdo, ['category' => 'compte', 'action' => 'compte_suppression', 'entity_type' => 'utilisateur', 'entity_id' => $user_id,
                'after' => ['status' => 'deleted', 'is_active' => 0, 'anonymise' => true], 'meta' => ['type' => $type_suppression]]);

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = messageIncident(incidentEnregistrer($pdo, $e, 'profil/suppression_compte'),
                "Une erreur est survenue, votre compte n'a pas été supprimé. Réessayez.");
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
$user_verifie_flag = 0; // renseigne plus bas apres chargement de auth_middleware

// Affichage lisible du numero, quel que soit le marche (includs/config_marche.php).
$user_phone_affiche = afficherNumero($user_phone);

// Compte de reception des commissions (affilies) : liste d'operateurs du marche du compte
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/moyen_paiement.php';
$marche_profil   = marcheDeCompte($user ?: null);
$role_profil     = $_SESSION['user_role'] ?? '';
$paiement_visible = !in_array($role_profil, ['commercant', 'admin'], true);
$compte_verifie_profil = compteVerifie($pdo, $user_id);
$user_verifie_flag = $compte_verifie_profil ? 1 : 0;
$moyen_paiement  = $paiement_visible ? moyenPaiementDuCompte($pdo, (int) $user_id) : null;

// Initiales robustes : prend la première lettre de chaque mot du nom (max 2)
$mots = preg_split('/\s+/', trim($user_fullname), -1, PREG_SPLIT_NO_EMPTY);
if (count($mots) >= 2) {
    $user_initials = mb_strtoupper(mb_substr($mots[0], 0, 1) . mb_substr($mots[1], 0, 1));
} elseif (count($mots) === 1) {
    $user_initials = mb_strtoupper(mb_substr($mots[0], 0, 2));
} else {
    $user_initials = 'U';
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
                <?php if (!empty($user_phone) && (int) ($user_verifie_flag ?? 0) === 1): ?><span class="pastille pastille-succes">Vérifié</span><?php else: ?><span class="pastille">Non vérifié</span><?php endif; ?>
              </p>
              <p class="champ-aide">Le numéro sert à la connexion et ne peut pas être modifié ici.</p>
            </div>
          <?php endif; ?>
          <button type="submit" name="update_profile" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Enregistrer</span></button>
        </div>
      </form>

      <?php if ($paiement_visible): ?>
      <form action="" method="POST" id="form-paiement" class="carte self-start">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <h2 class="carte-entete carte-titre">Réception des commissions</h2>
        <div class="flex flex-col gap-4 p-4">
          <?php if (!$compte_verifie_profil): ?>
            <p class="alerte alerte-attention"><?= ico('lock') ?><span>Vérifiez votre numéro (bandeau du tableau de bord) pour enregistrer votre compte de réception.</span></p>
          <?php endif; ?>
          <p class="text-sm text-text-2">Vos commissions sont payées à la main par l'équipe MonRevenu, sur l'opérateur et le numéro ci-dessous.</p>
          <div class="champ">
            <label class="champ-label" for="operateur">Opérateur mobile money</label>
            <select class="champ-saisie" id="operateur" name="operateur" required<?= $compte_verifie_profil ? '' : ' disabled' ?>>
              <option value="">Choisir un opérateur</option>
              <?php foreach (moyensRetrait($marche_profil) as $op): ?>
                <option value="<?= e($op) ?>"<?= ($moyen_paiement['operateur'] ?? '') === $op ? ' selected' : '' ?>><?= e($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="champ">
            <label class="champ-label" for="numero_paiement">Numéro qui reçoit l'argent</label>
            <input class="champ-saisie" type="tel" id="numero_paiement" name="numero_paiement" inputmode="tel" autocomplete="tel" required
                   value="<?= e($moyen_paiement ? afficherNumero($moyen_paiement['numero']) : $user_phone_affiche) ?>"
                   placeholder="<?= e(marche($marche_profil)['exemple_numero']) ?>"<?= $compte_verifie_profil ? '' : ' disabled' ?>>
            <p class="champ-aide">Numéro <?= e(marche($marche_profil)['nom']) ?> (+<?= e(marche($marche_profil)['indicatif']) ?>). Sans numéro, l'enregistrement est impossible.</p>
          </div>
          <?php if (!$est_compte_google): ?>
          <div class="champ">
            <label class="champ-label" for="mot_de_passe_paiement">Mot de passe actuel</label>
            <input class="champ-saisie" type="password" id="mot_de_passe_paiement" name="mot_de_passe_paiement" required maxlength="64" autocomplete="current-password"<?= $compte_verifie_profil ? '' : ' disabled' ?>>
            <p class="champ-aide">Demandé pour confirmer qu'il s'agit bien de vous.</p>
          </div>
          <?php endif; ?>
          <button type="submit" name="update_paiement" class="btn btn-primaire self-start"<?= $compte_verifie_profil ? '' : ' disabled' ?>><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Enregistrer</span></button>
        </div>
      </form>
      <?php endif; ?>

      <form action="" method="POST" id="form-password" class="carte self-start">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <h2 class="carte-entete carte-titre">Mot de passe</h2>
        <div class="flex flex-col gap-4 p-4">
          <div class="champ">
            <label class="champ-label" for="mot_de_passe_actuel">Mot de passe actuel</label>
            <input class="champ-saisie" type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required maxlength="64" autocomplete="current-password">
          </div>
          <div class="champ">
            <label class="champ-label" for="nouveau_mot_de_passe">Nouveau mot de passe</label>
            <input class="champ-saisie" type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required minlength="8" maxlength="64" autocomplete="new-password" aria-describedby="aide-code-secret">
            <p class="champ-aide" id="aide-code-secret"><?= e(MOT_DE_PASSE_AIDE) ?></p>
          </div>
          <div class="champ">
            <label class="champ-label" for="confirmation_mot_de_passe">Confirmer le nouveau mot de passe</label>
            <input class="champ-saisie" type="password" id="confirmation_mot_de_passe" name="confirmation_mot_de_passe" required minlength="8" maxlength="64" autocomplete="new-password" aria-describedby="err-confirmation-code">
            <p class="champ-erreur" id="err-confirmation-code" hidden><?= ico('circle-alert', 'ico-16 mt-0.5') ?><span>Les deux mots de passe sont différents.</span></p>
          </div>
          <button type="submit" name="update_password" class="btn btn-primaire self-start"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Modifier le mot de passe</span></button>
        </div>
      </form>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/sections/activer_notifications.php'; ?>

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
              <label class="champ-label" for="suppression_mot_de_passe">Votre mot de passe</label>
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
