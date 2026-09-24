<?php
require_once __DIR__ . '/includs/session.php';
/**
 * verification.php
 *
 * Vérification du code reçu par WhatsApp ou email
 * (selon le choix fait par l'utilisateur à l'inscription).
 *
 * Fonctionnement :
 * 1. Vérifie le code à 6 chiffres.
 * 2. Active le compte après vérification.
 * 3. Connecte automatiquement le nouvel utilisateur.
 * Le parrainage a été retiré (décision du 18/09/2026).
 */

demarrerSession();

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/includs/audit.php';
require_once __DIR__ . '/includs/incident.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/email_sender.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/whatsapp_sender.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/reglages_publication.php';

/* ============================================================
   DÉTERMINER L'UTILISATEUR À VÉRIFIER
   ============================================================ */

$user_id =
    $_SESSION['pending_verification_user_id']
    ?? $_SESSION['unverified_login_user_id']
    ?? null;

if (!$user_id) {
    header('Location: /index.php');
    exit();
}

$user_id = (int) $user_id;

if ($user_id <= 0) {
    header('Location: /index.php');
    exit();
}

/* ============================================================
   CSRF
   ============================================================ */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

/* ============================================================
   RÉCUPÉRER L'UTILISATEUR
   ============================================================ */

$stmtUser = $pdo->prepare("
    SELECT
        id,
        fullname,
        email,
        phone,
        verification_method,
        phone_verified,
        verification_code,
        code_expires_at,
        code_sent_at,
        parrain_id,
        is_active,
        status
    FROM users_monrevenu
    WHERE id = ?
    LIMIT 1
");

$stmtUser->execute([$user_id]);

$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    unset(
        $_SESSION['pending_verification_user_id'],
        $_SESSION['unverified_login_user_id']
    );

    header('Location: /index.php');
    exit();
}

/*
 * Canal de vérification choisi par l'utilisateur.
 * 'email' reste la valeur par défaut si jamais la colonne
 * est vide (compte créé avant l'ajout de cette fonctionnalité).
 */

$methode = $user['verification_method'] ?? 'email';

if (!in_array($methode, ['whatsapp', 'email'], true)) {
    $methode = 'email';
}

/* ============================================================
   SI DÉJÀ VÉRIFIÉ
   ============================================================ */

if ((int) $user['phone_verified'] === 1) {

    unset(
        $_SESSION['pending_verification_user_id'],
        $_SESSION['unverified_login_user_id']
    );

    header('Location: /index.php');
    exit();
}

/* ============================================================
   TRAITEMENT : RENVOYER UN NOUVEAU CODE
   ============================================================ */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action_renvoyer'])
) {

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $error = 'Session expirée, merci de recharger la page.';
        auditCsrf($pdo, 'verification_renvoi');

    } else {

        $dernierEnvoi = !empty($user['code_sent_at'])
            ? strtotime($user['code_sent_at'])
            : 0;

        /* Limite : 1 renvoi toutes les 60 secondes */

        if ($dernierEnvoi > 0 && time() - $dernierEnvoi < 60) {

            $secondesRestantes =
                60 - (time() - $dernierEnvoi);

            $error =
                'Merci de patienter encore '
                . $secondesRestantes
                . ' seconde(s) avant de redemander un code.';

        } else {

            try {

                /*
                 * Génération du nouveau code.
                 */

                $nouveau_code = str_pad(
                    (string) random_int(0, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                /*
                 * Hash du code.
                 */

                $nouveau_hash = password_hash(
                    $nouveau_code,
                    PASSWORD_BCRYPT
                );

                if ($nouveau_hash === false) {
                    throw new RuntimeException(
                        'Impossible de sécuriser le nouveau code.'
                    );
                }

                $nouvelle_expiration = date(
                    'Y-m-d H:i:s',
                    strtotime('+10 minutes')
                );

                /*
                 * IMPORTANT :
                 * On envoie d'abord le nouveau code, via le canal
                 * choisi par l'utilisateur à l'inscription.
                 *
                 * Cela évite de remplacer le code en base
                 * si l'envoi échoue.
                 */

                if ($methode === 'whatsapp') {

                    $resultat = envoyerCodeWhatsApp(
                        $user['phone'],
                        $nouveau_code
                    );

                    $messageErreurLog = $resultat['erreur'] ?? 'Erreur inconnue';
                    $libelleCanal = 'WhatsApp';

                } else {

                    $resultat = envoyerCodeEmail(
                        $user['email'],
                        $nouveau_code,
                        $user['fullname']
                    );

                    $messageErreurLog = $resultat['error'] ?? 'Erreur inconnue';
                    $libelleCanal = 'email';
                }

                if (!$resultat['ok']) {

                    error_log(
                        'Erreur renvoi code ('
                        . $methode
                        . ') pour utilisateur ID '
                        . $user_id
                        . ' : '
                        . $messageErreurLog
                    );

                    $error =
                        'Impossible d\'envoyer le code par '
                        . $libelleCanal
                        . '. Veuillez réessayer dans quelques instants.';

                } else {

                    /*
                     * L'envoi a été effectué avec succès.
                     * On enregistre alors le nouveau code.
                     */

                    $stmtUpdateCode = $pdo->prepare("
                        UPDATE users_monrevenu
                        SET
                            verification_code = ?,
                            code_expires_at = ?,
                            code_sent_at = NOW()
                        WHERE id = ?
                          AND phone_verified = 0
                    ");

                    $stmtUpdateCode->execute([
                        $nouveau_hash,
                        $nouvelle_expiration,
                        $user_id
                    ]);
                    auditInfo($pdo, ['category' => 'auth', 'action' => 'verification_code_renvoye', 'entity_type' => 'utilisateur',
                        'entity_id' => $user_id, 'actor_id' => $user_id, 'actor_role' => null, 'meta' => ['canal' => $libelleCanal]]);

                    $success =
                        'Nouveau code envoyé par ' . $libelleCanal . '.';

                    /*
                     * Recharge les données utilisateur.
                     */

                    $stmtUser->execute([$user_id]);
                    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
                }

            } catch (\Throwable $e) {

                $error = messageIncident(
                    incidentEnregistrer($pdo, $e, 'verification/renvoi_code'),
                    'Une erreur est survenue. Veuillez réessayer.'
                );
            }
        }
    }
}

/* ============================================================
   TRAITEMENT : VÉRIFICATION DU CODE
   ============================================================ */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action_verifier'])
) {

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $error =
            'Session expirée, merci de recharger la page.';
        auditCsrf($pdo, 'verification');

    } else {

        $code_saisi = trim($_POST['code'] ?? '');

        /*
         * Le code doit obligatoirement contenir 6 chiffres.
         */

        if (!preg_match('/^\d{6}$/', $code_saisi)) {

            $error =
                'Le code doit contenir exactement 6 chiffres.';

        } elseif (
            empty($user['verification_code']) ||
            empty($user['code_expires_at'])
        ) {

            $error =
                'Aucun code actif. Merci de redemander un code.';

        } elseif (
            strtotime($user['code_expires_at']) < time()
        ) {

            $error =
                'Ce code a expiré. Merci de redemander un code.';

        } elseif (
            !password_verify(
                $code_saisi,
                $user['verification_code']
            )
        ) {

            $error = 'Code incorrect.';
            auditInfo($pdo, ['category' => 'auth', 'action' => 'verification_echec', 'result' => 'echec', 'entity_type' => 'utilisateur',
                'entity_id' => $user_id, 'actor_id' => $user_id, 'actor_role' => null]);

        } else {

            /*
             * ====================================================
             * CODE CORRECT
             * ====================================================
             */

            try {

                $pdo->beginTransaction();

                /*
                 * Verrouillage de l'utilisateur pendant
                 * la transaction afin d'éviter deux validations
                 * simultanées.
                 */

                $stmtLockUser = $pdo->prepare("
                    SELECT
                        id,
                        fullname,
                        email,
                        role,
                        parrain_id,
                        phone_verified
                    FROM users_monrevenu
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmtLockUser->execute([$user_id]);

                $userVerif = $stmtLockUser->fetch(PDO::FETCH_ASSOC);

                if (!$userVerif) {
                    throw new RuntimeException(
                        'Utilisateur introuvable.'
                    );
                }

                /*
                 * Si quelqu'un a déjà validé le compte,
                 * on ne crédite surtout pas une deuxième fois.
                 */

                if ((int) $userVerif['phone_verified'] === 1) {

                    $pdo->rollBack();

                    unset(
                        $_SESSION['pending_verification_user_id'],
                        $_SESSION['unverified_login_user_id']
                    );

                    header(
                        'Location: /index.php'
                    );

                    exit();
                }

                /* =================================================
                   ACTIVER LE COMPTE
                   ================================================= */

                $stmtActivate = $pdo->prepare("
                    UPDATE users_monrevenu
                    SET
                        phone_verified = 1,
                        is_active = 1,
                        status = 'active',
                        verification_code = NULL,
                        code_expires_at = NULL
                    WHERE id = ?
                ");

                $stmtActivate->execute([
                    $user_id
                ]);
                auditInfo($pdo, ['category' => 'auth', 'action' => 'verification_email', 'entity_type' => 'utilisateur',
                    'entity_id' => $user_id, 'actor_id' => $user_id, 'actor_role' => $userVerif['role'],
                    'before' => ['phone_verified' => 0, 'is_active' => $userVerif['is_active'] ?? null], 'after' => ['phone_verified' => 1, 'is_active' => 1, 'status' => 'active']]);

                // Publication automatique (bloc H) : une boutique devient valide des la verification
                // de son compte, sauf en mode manuel (comportement historique, validation par un admin).
                if ($userVerif['role'] === 'commercant') {
                    $reglages = reglagesPublication($pdo);
                    if ($reglages['mode'] !== 'manuelle') {
                        $stmtBoutique = $pdo->prepare("SELECT statut FROM commercants_profils WHERE user_id = ? FOR UPDATE");
                        $stmtBoutique->execute([$user_id]);
                        $statutBoutique = $stmtBoutique->fetchColumn();
                        if ($statutBoutique === 'en_attente') {
                            $pdo->prepare("UPDATE commercants_profils SET statut = 'valide', valide_le = NOW() WHERE user_id = ?")
                                ->execute([$user_id]);
                            auditInfo($pdo, ['category' => 'admin', 'action' => 'commercant_validation_automatique', 'entity_type' => 'commercant',
                                'entity_id' => $user_id, 'actor_id' => null, 'actor_role' => 'systeme',
                                'before' => ['statut' => 'en_attente'], 'after' => ['statut' => 'valide'], 'meta' => ['mode' => $reglages['mode']]]);
                        }
                    }
                }

                /*
                 * Tout s'est bien passé.
                 */

                $pdo->commit();

                /* =================================================
                   CONNEXION AUTOMATIQUE
                   ================================================= */

                session_regenerate_id(true);

                // Score de coherence du pays (signal pour le support, ne bloque rien)
                require_once __DIR__ . '/includs/geo_coherence.php';
                geoCoherenceEnregistrer($pdo, (int) $userVerif['id'], null);

                $_SESSION['user_id'] =
                    $userVerif['id'];

                $_SESSION['user_fullname'] =
                    $userVerif['fullname'];

                $_SESSION['user_email'] =
                    $userVerif['email'];

                $_SESSION['user_role'] =
                    $userVerif['role'];

                $_SESSION['logged_in'] = true;

                $_SESSION['login_time'] = time();

                require_once __DIR__ . '/includs/ip_client.php';
                $_SESSION['ip'] = ipClient();

                $_SESSION['csrf_token'] =
                    bin2hex(random_bytes(32));

                unset(
                    $_SESSION['pending_verification_user_id'],
                    $_SESSION['unverified_login_user_id']
                );

                /* =================================================
                   REDIRECTION SELON LE RÔLE
                   ================================================= */

                switch ($userVerif['role']) {

                    case 'admin':

                        header(
                            'Location: /admin/dashboard_admin.php'
                        );

                        break;

                    case 'agent':

                        header(
                            'Location: /admin/dashboard_agent.php'
                        );

                        break;

                    case 'commercant':

                        header(
                            'Location: /commercant/index.php'
                        );

                        break;

                    default:

                        header(
                            'Location: /dashboard.php'
                        );

                        break;
                }

                exit();

            } catch (\Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = messageIncident(
                    incidentEnregistrer($pdo, $e, 'verification/validation'),
                    'Une erreur est survenue pendant la validation de votre compte. Veuillez réessayer.'
                );
            }
        }
    }
}

/* ============================================================
   MASQUER L'EMAIL
   ============================================================ */

function masquerEmail(string $email): string
{
    $parties = explode('@', $email);

    if (count($parties) !== 2) {
        return $email;
    }

    $local = $parties[0];

    $longueur = mb_strlen($local);

    if ($longueur <= 2) {
        $masque = mb_substr($local, 0, 1) . '**';
    } else {
        $masque =
            mb_substr($local, 0, 2)
            . str_repeat(
                '*',
                max($longueur - 2, 2)
            );
    }

    return $masque . '@' . $parties[1];
}

/* ============================================================
   MASQUER LE TÉLÉPHONE
   ============================================================ */

function masquerTelephone(string $telephone): string
{
    // $telephone est au format 269XXXXXXX (indicatif + numéro local)
    $longueur = mb_strlen($telephone);

    if ($longueur <= 4) {
        return $telephone;
    }

    $indicatif = mb_substr($telephone, 0, 3);      // 269
    $debut     = mb_substr($telephone, 3, 1);       // premier chiffre local
    $fin       = mb_substr($telephone, -2);         // 2 derniers chiffres

    return '+' . $indicatif . ' ' . $debut . str_repeat('*', $longueur - 6) . $fin;
}

/*
 * Coordonnée masquée à afficher, selon le canal choisi.
 */

if ($methode === 'whatsapp') {
    $contact_masque = masquerTelephone($user['phone']);
} else {
    $contact_masque = masquerEmail($user['email']);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
$titre_page = $methode === 'whatsapp' ? 'Vérifier votre WhatsApp' : 'Vérifier votre email';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_debut.php';
?>
    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-primary-soft text-primary-ink"><?= ico($methode === 'whatsapp' ? 'whatsapp' : 'mail', 'ico-24') ?></span>
    <h1 class="mt-4 text-2xl font-semibold"><?= $methode === 'whatsapp' ? 'Vérifiez votre WhatsApp' : 'Vérifiez votre email' ?></h1>
    <p class="mt-2 text-text-2">
      Un code à 6 chiffres a été envoyé <?= $methode === 'whatsapp' ? 'par WhatsApp au' : 'par email à' ?>
      <strong class="whitespace-nowrap font-medium text-text"><?= e($contact_masque) ?></strong>. Saisissez-le pour activer votre compte.
    </p>

    <?php if ($error): ?>
      <p class="alerte alerte-danger mt-5" role="alert"><?= ico('circle-alert') ?><span><?= e($error) ?></span></p>
    <?php endif; ?>
    <?php if ($success): ?>
      <p class="alerte alerte-succes mt-5" role="status"><?= ico('circle-check') ?><span><?= e($success) ?></span></p>
    <?php endif; ?>

    <form method="POST" action="" class="mt-6 flex flex-col gap-4">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="champ">
        <label class="champ-label" for="code-verification">Code de vérification</label>
        <input class="champ-saisie chiffres text-center font-mono text-xl tracking-[.4em]" type="text" id="code-verification" name="code" required maxlength="6" minlength="6"
               inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" autofocus placeholder="000000">
      </div>
      <button type="submit" name="action_verifier" class="btn btn-primaire btn-bloc"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Vérifier le code</span></button>
    </form>

    <form method="POST" action="" class="mt-3">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <button type="submit" name="action_renvoyer" class="btn btn-discret btn-bloc">Je n'ai rien reçu : renvoyer le code</button>
    </form>
    <?php if ($methode !== 'whatsapp'): ?>
      <p class="mt-4 text-center text-sm text-text-3">Pensez à regarder dans les courriers indésirables.</p>
    <?php endif; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_fin.php'; ?>
