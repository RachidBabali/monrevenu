<?php
/**
 * register_handler.php
 * Inscription MonRevenu avec vérification par email
 *
 * Aucun système de parrainage.
 */

session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/email_sender.php';


/* ============================================================
   MÉTHODE HTTP
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /inscription.php');
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
    header('Location: /inscription.php?error=csrf');
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

$code = strtoupper(
    trim($_POST['code'] ?? '')
);

$confirm_code = strtoupper(
    trim($_POST['confirm_code'] ?? '')
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
        'Location: /inscription.php?error=champs_manquants'
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
        'Location: /inscription.php?error=nom_invalide'
    );
    exit();
}


/* ============================================================
   EMAIL
   ============================================================ */

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header(
        'Location: /inscription.php?error=email_invalide'
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
        'Location: /inscription.php?error=birthdate_invalide'
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
        'Location: /inscription.php?error=age_insuffisant'
    );
    exit();
}


/* ============================================================
   NUMÉRO COMORIEN
   ============================================================ */

$phone_nettoye = preg_replace(
    '/[^\d]/',
    '',
    $phone
);


/*
 * Format :
 *
 * 00269XXXXXXXX
 * 269XXXXXXXX
 * 3XXXXXX ou 4XXXXXX
 */

if (
    str_starts_with(
        $phone_nettoye,
        '00269'
    )
) {

    $phone_local = substr(
        $phone_nettoye,
        5
    );

} elseif (
    str_starts_with(
        $phone_nettoye,
        '269'
    ) &&
    strlen($phone_nettoye) === 10
) {

    $phone_local = substr(
        $phone_nettoye,
        3
    );

} else {

    $phone_local = $phone_nettoye;
}


/*
 * Numéro local comorien :
 * (3 ou 4) + 6 chiffres
 * — 3 pour l'opérateur Huri, 4 pour l'opérateur Yas
 */

if (
    !preg_match(
        '/^[34]\d{6}$/',
        $phone_local
    )
) {

    header(
        'Location: /inscription.php?error=phone_non_comorien'
    );
    exit();
}


/*
 * Format international
 */

$phone_normalise = '269' . $phone_local;


/* ============================================================
   CODE SECRET
   ============================================================ */

/*
 * Le code secret doit contenir exactement :
 *
 * 4 caractères
 * 2 chiffres
 * 2 lettres majuscules
 */

if (strlen($code) !== 4) {

    header(
        'Location: /inscription.php?error=code_invalide'
    );
    exit();
}


$nb_chiffres = preg_match_all(
    '/[0-9]/',
    $code
);

$nb_lettres = preg_match_all(
    '/[A-Z]/',
    $code
);


if (
    $nb_chiffres !== 2 ||
    $nb_lettres !== 2
) {

    header(
        'Location: /inscription.php?error=code_invalide'
    );
    exit();
}


/* ============================================================
   CONFIRMATION DU CODE
   ============================================================ */

if ($code !== $confirm_code) {

    header(
        'Location: /inscription.php?error=code_different'
    );
    exit();
}


/* ============================================================
   CONDITIONS
   ============================================================ */

if (!$terms) {

    header(
        'Location: /inscription.php?error=conditions'
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

    /*
     * NB : la colonne `status` de cette base n'accepte que
     * 'active' / 'suspended' / 'deleted' (pas de valeur 'pending').
     * On utilise donc `is_active` pour distinguer un compte pas
     * encore vérifié (is_active = 0) d'un compte pleinement actif.
     */
    $compte_en_attente_verification =
        $compte_existant
        && (int) $compte_existant['is_active'] === 0
        && $compte_existant['status'] !== 'suspended';

    if ($compte_existant && !$compte_en_attente_verification) {

        /*
         * Un vrai compte actif ou suspendu existe déjà avec cet
         * email ou ce téléphone : on bloque comme avant.
         */

        header(
            'Location: /inscription.php?error=existe_deja'
        );
        exit();
    }

    if ($compte_en_attente_verification) {

        /*
         * Un compte est resté coincé en 'pending' — très probablement
         * parce que l'envoi du code avait échoué la première fois.
         * Plutôt que de bloquer l'utilisateur, on relance simplement
         * son processus de vérification : nouveau code, nouvel envoi,
         * pas de nouvelle ligne en base.
         */

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
       Hash du code secret
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


    /*
     * Le code expire dans 10 minutes.
     */

    $code_expiration = date(
        'Y-m-d H:i:s',
        strtotime('+10 minutes')
    );


    /* --------------------------------------------------------
       Transaction
       -------------------------------------------------------- */

    $pdo->beginTransaction();


    /*
     * Création du compte.
     *
     * IMPORTANT :
     *
     * phone_verified = 0
     * is_active      = 0
     * status         = pending
     *
     * Le compte sera activé après vérification de l'email.
     */

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


    /*
     * ID du nouvel utilisateur.
     */

    $nouvel_utilisateur_id = (int) $pdo->lastInsertId();


    /*
     * Valide la transaction.
     */

    $pdo->commit();


    /* ========================================================
       ENVOI DU CODE PAR EMAIL
       ======================================================== */

    $resultatEnvoi = envoyerCodeEmail(
        $email,
        $code_verification,
        $fullname
    );


    /*
     * Si l'envoi échoue :
     *
     * Le compte existe déjà dans la base.
     *
     * On redirige quand même vers la page de vérification
     * afin d'éviter de recréer plusieurs comptes.
     */

    if (!$resultatEnvoi['ok']) {

        error_log(
            'Échec envoi code email pour utilisateur ID ' .
            $nouvel_utilisateur_id .
            ' : ' .
            ($resultatEnvoi['error'] ?? 'Erreur inconnue')
        );

        /*
         * On conserve l'utilisateur en session.
         */

        $_SESSION['pending_verification_user_id'] =
            $nouvel_utilisateur_id;

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));

        /*
         * On va quand même vers verification.php.
         *
         * L'utilisateur pourra utiliser
         * "Renvoyer le code".
         */

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


    /* ========================================================
       REDIRECTION VERS VÉRIFICATION
       ======================================================== */

    header(
        'Location: /verification.php?nouveau=1'
    );

    exit();


} catch (PDOException $e) {

    /*
     * Annule la transaction uniquement
     * si elle est encore active.
     */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    error_log(
        'Erreur inscription MonRevenu : ' .
        $e->getMessage()
    );


    header(
        'Location: /inscription.php?error=serveur'
    );

    exit();
}