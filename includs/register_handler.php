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
require_once __DIR__ . '/config_marche.php';
require_once __DIR__ . '/mot_de_passe.php';


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
    require_once __DIR__ . '/audit.php';
    auditCsrf($pdo ?? null, 'inscription');
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

// Mot de passe : regle unique includs/mot_de_passe.php, casse conservee telle que saisie.
$code = trim((string) ($_POST['code'] ?? '')); // trim comme la connexion

$confirm_code = trim(
    $_POST['confirm_code'] ?? ''
);

$terms = isset(
    $_POST['acceptTerms']
);

// Type de compte : affilie (promouvoir des produits) ou commercant (vendre ses produits)
$type_compte  = ($_POST['type_compte'] ?? 'affilie') === 'commercant' ? 'commercant' : 'affilie';
$nom_boutique = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nom_boutique'] ?? '')));
$ville        = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['ville'] ?? '')));
// Garde le choix et la boutique en session pour reafficher le formulaire en cas d'erreur (jamais dans l'URL)
$_SESSION['inscription_saisie'] = ['type' => $type_compte, 'nom_boutique' => mb_substr($nom_boutique, 0, 120), 'ville' => mb_substr($ville, 0, 100)];


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
   BOUTIQUE (commercant seulement)
   ============================================================ */

if ($type_compte === 'commercant' && (mb_strlen($nom_boutique) < 2 || mb_strlen($nom_boutique) > 120 || mb_strlen($ville) > 100)) {
    header('Location: /index.php?error=boutique_invalide');
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
   NUMÉRO DE TÉLÉPHONE, Comores ou Sénégal
   ============================================================ */

/*
 * Le marché choisi dans le formulaire sert d'indication de depart ; si le
 * numero porte lui-meme un indicatif (00269/269, 00221/221), il l'emporte
 * (voir includs/config_marche.php > normaliserNumero). Validation par
 * longueur nationale seulement, sans plage de prefixes.
 */
$phone_country_saisi = marcheValide($phone_country) ?? MARCHE_DEFAUT;
$phone_normalise = normaliserNumero($phone, $phone_country_saisi) ?? normaliserNumero($phone);

if ($phone_normalise === null) {
    header(
        'Location: /inscription.php?error=phone_invalide'
    );
    exit();
}

// Marche reel du compte : celui deduit du numero normalise (fiable), jamais la geolocalisation.
$phone_country = marcheDeNumero($phone_normalise) ?? $phone_country_saisi;


/* ============================================================
   MOT DE PASSE, règle includs/mot_de_passe.php
   ============================================================ */

if (erreurMotDePasse($code) !== null) {

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

        if ($type_compte === 'commercant') {
            $pdo->prepare("UPDATE users_monrevenu SET role = 'commercant' WHERE id = ? AND role = 'affilie'")->execute([$nouvel_utilisateur_id]);
            $pdo->prepare(
                "INSERT INTO commercants_profils (user_id, nom_boutique, ville) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE nom_boutique = VALUES(nom_boutique), ville = VALUES(ville)"
            )->execute([$nouvel_utilisateur_id, $nom_boutique, $ville !== '' ? $ville : null]);
        }

        require_once __DIR__ . '/audit.php';
        auditInfo($pdo, ['category' => 'auth', 'action' => 'inscription_reprise_compte_non_verifie', 'entity_type' => 'utilisateur',
            'entity_id' => $nouvel_utilisateur_id, 'actor_id' => $nouvel_utilisateur_id, 'actor_role' => null, 'meta' => ['email' => $email]]);

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

    // Le marche du compte est celui de son numero verifie, jamais la geolocalisation
    // (peu fiable et modifiable par l'utilisateur) : voir includs/config_marche.php.
    $pays = ['code' => $phone_country, 'nom' => marche($phone_country)['nom']];

    $stmt = $pdo->prepare(
        "INSERT INTO users_monrevenu
        (
            fullname,
            email,
            birthdate,
            phone,
            phone_verified,
            pays_code,
            pays_nom,
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
            :pays_code,
            :pays_nom,
            :verification_code,
            :code_expires_at,
            NOW(),
            :password,
            :role,
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
        ':pays_code' => $pays['code'],
        ':pays_nom' => $pays['nom'],
        ':verification_code' => $code_verification_hash,
        ':code_expires_at' => $code_expiration,
        ':password' => $password_hash,
        ':role' => $type_compte,
    ]);


    $nouvel_utilisateur_id = (int) $pdo->lastInsertId();

    if ($type_compte === 'commercant') {
        $pdo->prepare("INSERT INTO commercants_profils (user_id, nom_boutique, ville) VALUES (?, ?, ?)")
            ->execute([$nouvel_utilisateur_id, $nom_boutique, $ville !== '' ? $ville : null]);
    }

    require_once __DIR__ . '/audit.php';
    auditInfo($pdo, ['category' => 'auth', 'action' => 'inscription', 'entity_type' => 'utilisateur', 'entity_id' => $nouvel_utilisateur_id,
        'actor_id' => $nouvel_utilisateur_id, 'actor_role' => $type_compte,
        'after' => ['email' => $email, 'phone' => $phone_normalise, 'role' => $type_compte, 'pays' => $pays['code']]
            + ($type_compte === 'commercant' ? ['nom_boutique' => $nom_boutique, 'ville' => $ville] : []),
        'meta' => ['methode' => 'formulaire']]);

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