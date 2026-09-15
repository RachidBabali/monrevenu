<?php
/**
 * register_handler.php
 * Inscription MonRevenu avec vérification par email
 *
 * Aucun système de parrainage.
 */

session_start();

require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/email_sender.php';


/* ============================================================
   MÉTHODE HTTP
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit();
}


/* ============================================================
   PROTECTION CSRF
   ============================================================ */

if (
    empty($_POST['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'] ?? '',
        $_POST['csrf_token']
    )
) {
    header('Location: /index.php?error=csrf');
    exit();
}


/* ============================================================
   RÉCUPÉRATION DES DONNÉES
   ============================================================ */

$fullname = trim(
    $_POST['fullname'] ?? ''
);

$email = trim(
    strtolower($_POST['email'] ?? '')
);

$birthdate = trim(
    $_POST['birthdate'] ?? ''
);

$phone = trim(
    $_POST['phone'] ?? ''
);

$phone_country = trim(
    $_POST['phone_country'] ?? 'KM'
);

// Mot de passe (8 caractères minimum, comme Google) — plus de
// forçage en majuscules, on garde la casse telle que saisie.
$code = trim(
    $_POST['code'] ?? ''
);

$confirm_code = trim(
    $_POST['confirm_code'] ?? ''
);

$terms = isset(
    $_POST['acceptTerms']
);


/* ============================================================
   VALIDATION DES CHAMPS OBLIGATOIRES
   ============================================================ */

if (
    empty($fullname) ||
    empty($email) ||
    empty($birthdate) ||
    empty($phone) ||
    empty($code)
) {
    header(
        'Location: /index.php?error=champs_manquants'
    );
    exit();
}


/* ============================================================
   NOM COMPLET
   ============================================================ */

if (
    mb_strlen($fullname) < 2 ||
    mb_strlen($fullname) > 100
) {
    header(
        'Location: /index.php?error=nom_invalide'
    );
    exit();
}


/* ============================================================
   EMAIL
   ============================================================ */

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header(
        'Location: /index.php?error=email_invalide'
    );
    exit();
}


/* ============================================================
   DATE DE NAISSANCE
   ============================================================ */

$date_naissance = DateTime::createFromFormat(
    'Y-m-d',
    $birthdate
);

if (
    !$date_naissance ||
    $date_naissance->format('Y-m-d') !== $birthdate
) {
    header(
        'Location: /index.php?error=birthdate_invalide'
    );
    exit();
}


/* ============================================================
   ÂGE MINIMUM : 18 ANS
   ============================================================ */

$age = $date_naissance->diff(
    new DateTime()
)->y;

if ($age < 18) {
    header(
        'Location: /index.php?error=age_insuffisant'
    );
    exit();
}


/* ============================================================
   NUMÉRO DE TÉLÉPHONE — Comores ou Sénégal
   ============================================================ */

$phone_nettoye = preg_replace(
    '/[^\d]/',
    '',
    $phone
);

/*
 * Retire un éventuel indicatif international déjà tapé par
 * l'utilisateur (00269/269 pour les Comores, 00221/221 pour
 * le Sénégal), et déduit le pays si l'indicatif est présent
 * même si le menu déroulant n'a pas été changé.
 */

if (str_starts_with($phone_nettoye, '00269')) {
    $phone_local = substr($phone_nettoye, 5);
    $phone_country = 'KM';
} elseif (str_starts_with($phone_nettoye, '00221')) {
    $phone_local = substr($phone_nettoye, 5);
    $phone_country = 'SN';
} elseif (str_starts_with($phone_nettoye, '269') && strlen($phone_nettoye) === 10) {
    $phone_local = substr($phone_nettoye, 3);
    $phone_country = 'KM';
} elseif (str_starts_with($phone_nettoye, '221') && strlen($phone_nettoye) === 12) {
    $phone_local = substr($phone_nettoye, 3);
    $phone_country = 'SN';
} else {
    $phone_local = $phone_nettoye;
}

if ($phone_country === 'SN') {

    /*
     * Numéro local sénégalais : 9 chiffres, commence par 7
     * (tous les opérateurs mobiles sénégalais : Orange, Free, Expresso).
     */

    if (!preg_match('/^7\d{8}$/', $phone_local)) {
        header(
            'Location: /index.php?error=phone_invalide'
        );
        exit();
    }

    $phone_normalise = '221' . $phone_local;

} else {

    /*
     * Numéro local comorien : (3 ou 4) + 6 chiffres
     * — 3 pour l'opérateur Huri, 4 pour l'opérateur Yas
     */

    if (!preg_match('/^[34]\d{6}$/', $phone_local)) {
        header(
            'Location: /index.php?error=phone_non_comorien'
        );
        exit();
    }

    $phone_normalise = '269' . $phone_local;
}


/* ============================================================
   MOT DE PASSE — 8 caractères minimum, comme Google
   ============================================================ */

if (mb_strlen($code) < 8) {

    header(
        'Location: /index.php?error=code_invalide'
    );
    exit();
}


/* ============================================================
   CONFIRMATION DU MOT DE PASSE
   ============================================================ */

if ($code !== $confirm_code) {

    header(
        'Location: /index.php?error=code_different'
    );
    exit();
}


/* ============================================================
   CONDITIONS
   ============================================================ */

if (!$terms) {

    header(
        'Location: /index.php?error=conditions'
    );
    exit();
}


/* ============================================================
   CRÉATION DU COMPTE
   ============================================================ */

try {

    /*
     * Vérifie si l'email ou le téléphone existe déjà.
     *
     * On exclut les comptes 'deleted' (soft delete admin) : une
     * personne dont le compte a été supprimé doit pouvoir se
     * réinscrire normalement avec les mêmes coordonnées.
     *
     * On récupère aussi le statut pour distinguer :
     *  - un vrai compte actif/suspendu déjà existant (bloqué)
     *  - un compte 'pending' resté coincé faute d'avoir reçu
     *    son code de vérification (repris ci-dessous, pas bloqué)
     */

    $stmt = $pdo->prepare(
        "SELECT id, status, is_active, fullname
         FROM users_monrevenu
         WHERE (email = ? OR phone = ?)
           AND status != 'deleted'
         LIMIT 1"
    );

    $stmt->execute([
        $email,
        $phone_normalise
    ]);

    $compte_existant = $stmt->fetch();

    $compte_en_attente_verification =
        $compte_existant
        && (int) $compte_existant['is_active'] === 0
        && $compte_existant['status'] !== 'suspended';

    if ($compte_existant && !$compte_en_attente_verification) {

        header(
            'Location: /index.php?error=existe_deja'
        );
        exit();
    }

    if ($compte_en_attente_verification) {

        $nouvel_utilisateur_id = (int) $compte_existant['id'];

        $code_verification = str_pad(
            (string) random_int(0, 999999),
            6,
            '0',
            STR_PAD_LEFT
        );

        $code_verification_hash = password_hash(
            $code_verification,
            PASSWORD_BCRYPT
        );

        $code_expiration = date(
            'Y-m-d H:i:s',
            strtotime('+10 minutes')
        );

        $stmtMaj = $pdo->prepare(
            "UPDATE users_monrevenu
             SET verification_code = ?,
                 code_expires_at = ?,
                 code_sent_at = NOW()
             WHERE id = ?"
        );

        $stmtMaj->execute([
            $code_verification_hash,
            $code_expiration,
            $nouvel_utilisateur_id
        ]);

        // Après création, l'utilisateur doit recevoir un code par
        // email pour vérifier son compte : redirection vers
        // verification.php (inchangée).
        $resultatEnvoi = envoyerCodeEmail(
            $email,
            $code_verification,
            $compte_existant['fullname'] ?: $fullname
        );

        if (!$resultatEnvoi['ok']) {

            error_log(
                'Échec renvoi code email (compte pending repris) ID ' .
                $nouvel_utilisateur_id .
                ' : ' .
                ($resultatEnvoi['error'] ?? 'Erreur inconnue')
            );

            $_SESSION['pending_verification_user_id'] = $nouvel_utilisateur_id;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header(
                'Location: /verification.php?nouveau=1&envoi=echec'
            );
            exit();
        }

        $_SESSION['pending_verification_user_id'] = $nouvel_utilisateur_id;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header(
            'Location: /verification.php?nouveau=1'
        );
        exit();
    }


    /* --------------------------------------------------------
       Hash du mot de passe
       -------------------------------------------------------- */

    $password_hash = password_hash(
        $code,
        PASSWORD_BCRYPT,
        [
            'cost' => 12
        ]
    );


    /* --------------------------------------------------------
       Génération du code email
       -------------------------------------------------------- */

    $code_verification = str_pad(
        (string) random_int(0, 999999),
        6,
        '0',
        STR_PAD_LEFT
    );


    $code_verification_hash = password_hash(
        $code_verification,
        PASSWORD_BCRYPT
    );


    $code_expiration = date(
        'Y-m-d H:i:s',
        strtotime('+10 minutes')
    );


    /* --------------------------------------------------------
       Transaction
       -------------------------------------------------------- */

    $pdo->beginTransaction();


    $stmt = $pdo->prepare(
        "INSERT INTO users_monrevenu
        (
            fullname,
            email,
            birthdate,
            phone,
            phone_verified,
            verification_code,
            code_expires_at,
            code_sent_at,
            password,
            role,
            balance,
            is_active,
            created_at
        )
        VALUES
        (
            :fullname,
            :email,
            :birthdate,
            :phone,
            0,
            :verification_code,
            :code_expires_at,
            NOW(),
            :password,
            'affilie',
            0.00,
            0,
            NOW()
        )"
    );


    $stmt->execute([
        ':fullname' => $fullname,
        ':email' => $email,
        ':birthdate' => $birthdate,
        ':phone' => $phone_normalise,
        ':verification_code' => $code_verification_hash,
        ':code_expires_at' => $code_expiration,
        ':password' => $password_hash
    ]);


    $nouvel_utilisateur_id = (int) $pdo->lastInsertId();


    $pdo->commit();


    /* ========================================================
       ENVOI DU CODE PAR EMAIL
       Le compte vient d'être créé (is_active = 0) : l'utilisateur
       doit maintenant recevoir un code par email et le saisir sur
       verification.php avant de pouvoir se connecter.
       ======================================================== */

    $resultatEnvoi = envoyerCodeEmail(
        $email,
        $code_verification,
        $fullname
    );


    if (!$resultatEnvoi['ok']) {

        error_log(
            'Échec envoi code email pour utilisateur ID ' .
            $nouvel_utilisateur_id .
            ' : ' .
            ($resultatEnvoi['error'] ?? 'Erreur inconnue')
        );

        $_SESSION['pending_verification_user_id'] =
            $nouvel_utilisateur_id;

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));

        header(
            'Location: /verification.php?nouveau=1&envoi=echec'
        );
        exit();
    }


    /* ========================================================
       SESSION DE VÉRIFICATION
       ======================================================== */

    $_SESSION['pending_verification_user_id'] =
        $nouvel_utilisateur_id;

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));


    header(
        'Location: /verification.php?nouveau=1'
    );

    exit();


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    error_log(
        'Erreur inscription MonRevenu : ' .
        $e->getMessage()
    );


    header(
        'Location: /index.php?error=serveur'
    );

    exit();
}