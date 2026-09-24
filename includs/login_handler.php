<?php

/**
 *          LOGIN HANDLER, Mon Revenu                      
 *   Gère : admin vers dashboard_admin / agent vers dashboard_agent
 *           affilie vers dashboard.php                        
 *   Connexion par : numéro de téléphone + mot de passe       
 *   Numéros acceptés : Comores (+269) et Sénégal (+221)     
 * À placer dans : includs/login_handler.php
 *
 * Les deux tables de protection (login_attempts et login_attempts_compte)
 * viennent des migrations : aucune table n'est créée à la connexion.
 * Si l'une manque, l'onglet Santé de l'audit le signale (tables attendues absentes).
 */
session_start();
require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/config_marche.php';

// email_sender.php est optionnel : si absent, la connexion continue de
// fonctionner normalement, juste sans l'email d'alerte de sécurité.
$email_sender_disponible = @include_once __DIR__ . '/email_sender.php';

define('MAX_TENTATIVES', 5);
define('DUREE_BLOCAGE_SECONDES', 1200); // 20 minutes

//  1. POST uniquement 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit();
}

//  2. CSRF 
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    require_once __DIR__ . '/audit.php';
    auditCsrf($pdo ?? null, 'connexion');
    header('Location: /index.php?error=csrf');
    exit();
}

//  3. Les tables login_attempts et login_attempts_compte existent en base (structure de production, migration 001).
require_once __DIR__ . '/audit.php';

//  4. Récupération des champs 
$identifiant = trim($_POST['identifiant'] ?? '');
$code = trim($_POST['code'] ?? '');

if (!$identifiant || !$code) {
    header('Location: /index.php?error=champs_manquants');
    exit();
}

//  4bis. Détecter le type d'identifiant : email ou téléphone 
// Si ça contient un @, on cherche par email. Sinon, on applique les mêmes
// règles de normalisation de numéro qu'à l'inscription (Comores + Sénégal).
$est_email = str_contains($identifiant, '@');

if ($est_email) {
    $cle_recherche = strtolower($identifiant);
} else {
    // Meme normalisation que l'inscription (includs/config_marche.php), sans distinction de
    // marche impose : le numero seul suffit a retrouver le compte, quel que soit son marche.
    $cle_recherche = normaliserNumero($identifiant) ?? preg_replace('/[^\d]/', '', $identifiant);
}

$ip = $_SERVER['REMOTE_ADDR'];

try {
    //  5. Blocage par IP 
    $stmt = $pdo->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $rowIp = $stmt->fetch();

    if ($rowIp) {
        $diffIp = time() - strtotime($rowIp['last_attempt']);
        if ($rowIp['attempts'] >= MAX_TENTATIVES && $diffIp < DUREE_BLOCAGE_SECONDES) {
            $reste = ceil((DUREE_BLOCAGE_SECONDES - $diffIp) / 60);
            auditInfo($pdo, ['category' => 'auth', 'action' => 'connexion_refusee_verrou_ip', 'result' => 'refus', 'actor_id' => null, 'actor_role' => null,
                'meta' => ['identifiant' => $cle_recherche, 'tentatives' => (int) $rowIp['attempts']]]);
            header("Location: /index.php?error=trop_tentatives&reste={$reste}");
            exit();
        }
        if ($diffIp >= DUREE_BLOCAGE_SECONDES) {
            $pdo->prepare("UPDATE login_attempts SET attempts=0 WHERE ip=?")->execute([$ip]);
        }
    }

    //  5bis. Blocage par compte (numéro visé), indépendant de l'IP 
    $stmtCompte = $pdo->prepare("SELECT attempts, last_attempt FROM login_attempts_compte WHERE phone = ?");
    $stmtCompte->execute([$cle_recherche]);
    $rowCompte = $stmtCompte->fetch();

    if ($rowCompte) {
        $diffCompte = time() - strtotime($rowCompte['last_attempt']);
        if ($rowCompte['attempts'] >= MAX_TENTATIVES && $diffCompte < DUREE_BLOCAGE_SECONDES) {
            $reste = ceil((DUREE_BLOCAGE_SECONDES - $diffCompte) / 60);
            auditInfo($pdo, ['category' => 'auth', 'action' => 'connexion_refusee_verrou_compte', 'result' => 'refus', 'actor_id' => null, 'actor_role' => null,
                'meta' => ['identifiant' => $cle_recherche, 'tentatives' => (int) $rowCompte['attempts']]]);
            header("Location: /index.php?error=compte_bloque&reste={$reste}");
            exit();
        }
        if ($diffCompte >= DUREE_BLOCAGE_SECONDES) {
            $pdo->prepare("UPDATE login_attempts_compte SET attempts=0 WHERE phone=?")->execute([$cle_recherche]);
        }
    }

    //  6. Rechercher l'utilisateur par téléphone 
    $stmt = $pdo->prepare("
        SELECT id, fullname, email, phone, password, role, is_active, phone_verified
        FROM users_monrevenu
        WHERE phone = ? OR email = ?
        LIMIT 1
    ");
    $stmt->execute([$cle_recherche, $cle_recherche]);
    $user = $stmt->fetch();

    //  7. Vérifier le mot de passe 
    $hash_reference = $user['password'] ?? '$2y$12$D9m5x1sJZ3yKf6q1r0aFZO7hV1Q6qk4pQnR2eYkD5vXbG8tJmW3Ke';
    $code_valide = password_verify($code, $hash_reference);

    if (!$user || !$code_valide) {
        $pdo->prepare("
            INSERT INTO login_attempts (ip, attempts, last_attempt)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
        ")->execute([$ip]);

        $pdo->prepare("
            INSERT INTO login_attempts_compte (phone, attempts, last_attempt)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
        ")->execute([$cle_recherche]);

        $nouvelles_tentatives_compte = $rowCompte ? ((int) $rowCompte['attempts'] + 1) : 1;
        if ($user && $nouvelles_tentatives_compte === MAX_TENTATIVES && $email_sender_disponible && function_exists('envoyerAlerteTentativesConnexion') && !empty($user['email'])) {
            try {
                envoyerAlerteTentativesConnexion(
                    $user['email'],
                    $user['fullname'] ?? '',
                    $cle_recherche,
                    (int) round(DUREE_BLOCAGE_SECONDES / 60)
                );
            } catch (\Throwable $e) {
                error_log('Erreur envoi alerte connexion : ' . $e->getMessage());
            }
        }

        $tentatives_ip = $rowIp ? ((int) $rowIp['attempts'] + 1) : 1;
        auditInfo($pdo, ['category' => 'auth', 'action' => $nouvelles_tentatives_compte >= MAX_TENTATIVES ? 'verrouillage_compte' : 'connexion_echec',
            'result' => 'echec', 'actor_id' => null, 'actor_role' => null, 'entity_type' => $user ? 'utilisateur' : null, 'entity_id' => $user['id'] ?? null,
            'meta' => ['identifiant' => $cle_recherche, 'tentatives_compte' => $nouvelles_tentatives_compte, 'tentatives_ip' => $tentatives_ip, 'compte_connu' => (bool) $user]]);
        $reste_tentatives = max(0, MAX_TENTATIVES - $tentatives_ip);

        header("Location: /index.php?error=identifiants&reste_tentatives={$reste_tentatives}");
        exit();
    }

    //  8. Compte actif 
    if (!$user['is_active']) {
        auditInfo($pdo, ['category' => 'auth', 'action' => 'connexion_refusee_inactif', 'result' => 'refus', 'actor_id' => null, 'actor_role' => null,
            'entity_type' => 'utilisateur', 'entity_id' => $user['id']]);
        header('Location: /index.php?error=compte_inactif');
        exit();
    }

    //  8bis. Numéro de téléphone vérifié 
    // Un affilie, client ou commercant non verifie se connecte quand meme : ses actions sont bloquees cote
    // serveur (compteVerifie) jusqu'a la verification. Admin et agent, eux, doivent etre verifies.
    if ((int) $user['phone_verified'] !== 1 && in_array($user['role'], ['admin', 'agent'], true)) {
        $_SESSION['unverified_login_user_id'] = $user['id'];
        header('Location: /verification.php');
        exit();
    }

    //  9. Connexion réussie : on réinitialise les deux compteurs 
    $pdo->prepare("
        INSERT INTO login_attempts (ip, attempts, last_attempt)
        VALUES (?, 0, NOW())
        ON DUPLICATE KEY UPDATE attempts = 0, last_attempt = NOW()
    ")->execute([$ip]);

    $pdo->prepare("
        INSERT INTO login_attempts_compte (phone, attempts, last_attempt)
        VALUES (?, 0, NOW())
        ON DUPLICATE KEY UPDATE attempts = 0, last_attempt = NOW()
    ")->execute([$cle_recherche]);

    try {
        $pdo->prepare("UPDATE users_monrevenu SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
    } catch (\PDOException $e) { /* colonne last_login absente, ignoré */
    }

    // Le pays du compte (son marche : devise, commissions, moyens de retrait) vient de son
    // numero verifie, jamais de la geolocalisation de la connexion en cours : un membre qui se
    // connecte depuis un autre pays (voyage, VPN, reseau mal detecte) garde son marche d'origine.
    // La geolocalisation reste utilisee ailleurs (page d'accueil) pour le visiteur non connecte.
    require_once __DIR__ . '/geoip.php';
    $pays = detecterPaysVisiteur();

    session_regenerate_id(true);

    // Score de coherence du pays (signal pour le support, ne bloque rien) : includs/geo_coherence.php
    require_once __DIR__ . '/geo_coherence.php';
    geoCoherenceEnregistrer($pdo, (int) $user['id'], $user['phone'] ?? null);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_fullname'] = $user['fullname'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['logged_in']     = true;
    $_SESSION['login_time']    = time();
    $_SESSION['ip']            = $ip;
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

    auditInfo($pdo, ['category' => 'auth', 'action' => 'connexion', 'entity_type' => 'utilisateur', 'entity_id' => $user['id'],
        'meta' => ['methode' => 'mot_de_passe', 'pays' => $pays['code'] ?? null]]);

    //  10. Redirection selon le rôle (inchangée) 
    switch ($user['role']) {
        case 'admin':
            header('Location:/admin/dashboard_admin.php');
            break;
        case 'agent':
            header('Location: /admin/dashboard_agent.php');
            break;
        case 'commercant':
            header('Location: /commercant/index.php');
            break;
        default: // affilie
            header('Location:/dashboard.php');
            break;
    }
    exit();
} catch (\PDOException $e) {
    error_log('Erreur login : ' . $e->getMessage());
    header('Location: /index.php?error=serveur');
    exit();
}
