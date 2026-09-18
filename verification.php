<?php
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

session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/email_sender.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/whatsapp_sender.php';

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
                        'Impossible d\u2019envoyer le code par '
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

                    $success =
                        'Nouveau code envoyé par ' . $libelleCanal . ' !';

                    /*
                     * Recharge les données utilisateur.
                     */

                    $stmtUser->execute([$user_id]);
                    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
                }

            } catch (\Throwable $e) {

                error_log(
                    'Erreur renvoi code MonRevenu : '
                    . $e->getMessage()
                );

                $error =
                    'Une erreur est survenue. '
                    . 'Veuillez réessayer.';
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

                /*
                 * Tout s'est bien passé.
                 */

                $pdo->commit();

                /* =================================================
                   CONNEXION AUTOMATIQUE
                   ================================================= */

                session_regenerate_id(true);

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

                $_SESSION['ip'] =
                    $_SERVER['REMOTE_ADDR'] ?? '';

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

                error_log(
                    'Erreur vérification MonRevenu : '
                    . $e->getMessage()
                );

                $error =
                    'Une erreur est survenue pendant la '
                    . 'validation de votre compte. '
                    . 'Veuillez réessayer.';
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
        $masque = mb_substr($local, 0, 1) . '••';
    } else {
        $masque =
            mb_substr($local, 0, 2)
            . str_repeat(
                '•',
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

    return '+' . $indicatif . ' ' . $debut . str_repeat('•', $longueur - 6) . $fin;
}

/*
 * Coordonnée masquée à afficher, selon le canal choisi.
 */

if ($methode === 'whatsapp') {
    $contact_masque = masquerTelephone($user['phone']);
} else {
    $contact_masque = masquerEmail($user['email']);
}

?>
<!DOCTYPE html>
<html lang="fr" class="light">

<head>

    <meta charset="UTF-8"/>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    />

    <title>MonRevenu – Vérification</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sora: ['Sora', 'sans-serif']
                    },
                    colors: {
                        brand: {
                            DEFAULT: '#1246A0',
                            mid: '#1A5FCC',
                            light: '#3B82F6',
                            soft: '#EEF4FF'
                        }
                    }
                }
            }
        }
    </script>

    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    />

    <style>
        body {
            font-family: 'Sora', sans-serif;
        }
    </style>

</head>

<body class="bg-[#F8F9FB] text-slate-900 min-h-screen flex items-center justify-center px-4">

<div class="w-full max-w-sm">

    <!-- En-tête -->

    <div class="flex flex-col items-center mb-6">

        <?php if ($methode === 'whatsapp'): ?>

            <div class="w-14 h-14 rounded-2xl bg-[#25D366] flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.42-1.36a9.9 9.9 0 0 0 4.62 1.14h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm0 18.06h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.83.83-3.05-.2-.31a8.16 8.16 0 0 1-1.26-4.36c0-4.52 3.68-8.2 8.21-8.2 2.19 0 4.25.86 5.8 2.4a8.15 8.15 0 0 1 2.4 5.8c0 4.53-3.68 8.22-8.16 8.22Zm4.5-6.15c-.25-.12-1.46-.72-1.68-.8-.23-.08-.39-.12-.56.12-.16.25-.64.8-.78.96-.14.16-.29.18-.53.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.48c-.16 0-.42.06-.64.31-.22.25-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.7 2.6 4.13 3.64.58.25 1.03.4 1.38.51.58.18 1.11.16 1.53.1.47-.07 1.46-.6 1.66-1.17.21-.58.21-1.08.14-1.17-.06-.1-.22-.16-.47-.28Z"/>
                </svg>
            </div>

            <h1 class="font-extrabold text-[20px] text-slate-800">
                Vérifiez votre WhatsApp
            </h1>

            <p class="text-[13px] text-slate-400 text-center mt-1">
                Un code à 6 chiffres a été envoyé par WhatsApp au<br>
                <span class="font-bold text-slate-700">
                    <?= htmlspecialchars($contact_masque, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </p>

        <?php else: ?>

            <div class="w-14 h-14 rounded-2xl bg-brand flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
            </div>

            <h1 class="font-extrabold text-[20px] text-slate-800">
                Vérifiez votre email
            </h1>

            <p class="text-[13px] text-slate-400 text-center mt-1">
                Un code à 6 chiffres a été envoyé par email à<br>
                <span class="font-bold text-slate-700">
                    <?= htmlspecialchars($contact_masque, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </p>

        <?php endif; ?>

    </div>

    <!-- Carte -->

    <div class="bg-white rounded-[24px] shadow-sm border border-slate-100 p-6">

        <!-- Erreur -->

        <?php if ($error): ?>

            <div class="bg-red-50 border border-red-200 text-red-600 rounded-xl p-3 mb-4 text-[12px] font-semibold text-center">

                ❌ <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>

        <!-- Succès -->

        <?php if ($success): ?>

            <div class="bg-emerald-50 border border-emerald-200 text-emerald-600 rounded-xl p-3 mb-4 text-[12px] font-semibold text-center">

                ✅ <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>

        <!-- Formulaire vérification -->

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2 text-center">

                Code de vérification

            </label>

            <input
                type="text"
                name="code"
                required
                maxlength="6"
                minlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                autocomplete="one-time-code"
                autofocus
                placeholder="000000"
                class="w-full text-center tracking-[0.5em] font-mono font-extrabold text-[24px] px-4 py-3 rounded-xl border-2 border-slate-200 bg-slate-50 focus:outline-none focus:border-brand transition-all mb-4"
            >

            <button
                type="submit"
                name="action_verifier"
                class="w-full bg-brand hover:bg-brand-mid text-white font-bold text-[14px] py-3 rounded-xl transition-all shadow-md shadow-blue-500/20"
            >

                Vérifier

            </button>

        </form>

        <!-- Renvoyer -->

        <form method="POST" action="" class="mt-3">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <button
                type="submit"
                name="action_renvoyer"
                class="w-full text-brand font-semibold text-[12px] py-2 hover:underline"
            >

                Je n'ai rien reçu — renvoyer le code

            </button>

        </form>

    </div>

</div>

</body>

</html>