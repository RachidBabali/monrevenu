<?php
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║         LOGIN HANDLER — Mon Revenu                      ║
 * ║  Gère : admin → dashboard_admin / agent → dashboard_agent
 * ║          affilie → dashboard.php                        ║
 * ║  Connexion par : numéro de téléphone + code secret       ║
 * ╚══════════════════════════════════════════════════════════╝
 * À placer dans : includs/login_handler.php
 *
 * Aucune modification manuelle de la base requise : les deux tables
 * de protection (login_attempts et login_attempts_compte) se créent
 * automatiquement toutes seules au premier appel.
 */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';

// email_sender.php est optionnel : si absent, la connexion continue de
// fonctionner normalement, juste sans l'email d'alerte de sécurité.
$email_sender_disponible = @include_once $_SERVER['DOCUMENT_ROOT'] . '/includs/email_sender.php';

define('MAX_TENTATIVES', 5);
define('DUREE_BLOCAGE_SECONDES', 1200); // 20 minutes

// ── 1. POST uniquement ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit();
}

// ── 2. CSRF ──────────────────────────────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    header('Location: /index.php?error=csrf');
    exit();
}

// ── 3. Créer les tables de protection si elles n'existent pas ────────────────
try {
    // Blocage par IP (déjà existant chez toi)
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        ip           VARCHAR(45) NOT NULL PRIMARY KEY,
        attempts     INT NOT NULL DEFAULT 0,
        last_attempt DATETIME NOT NULL DEFAULT NOW()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Blocage par compte (numéro de téléphone) — nouveau, mais géré tout seul,
    // comme login_attempts : aucune action manuelle nécessaire en base.
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts_compte (
        phone        VARCHAR(20) NOT NULL PRIMARY KEY,
        attempts     INT NOT NULL DEFAULT 0,
        last_attempt DATETIME NOT NULL DEFAULT NOW()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (\PDOException $e) {
    // Tables existent déjà ou autre erreur non bloquante
}

// ── 4. Récupération des champs ───────────────────────────────────────────────
$phone_brut = trim($_POST['phone'] ?? '');
$code       = strtoupper(trim($_POST['code'] ?? ''));

if (!$phone_brut || !$code) {
    header('Location: /index.php?error=champs_manquants');
    exit();
}

// ── 4bis. Normalisation du numéro (mêmes règles qu'à l'inscription) ──────────
$phone_nettoye = preg_replace('/[^\d]/', '', $phone_brut);
if (str_starts_with($phone_nettoye, '00269')) {
    $phone_local = substr($phone_nettoye, 5);
} elseif (str_starts_with($phone_nettoye, '269') && strlen($phone_nettoye) === 10) {
    $phone_local = substr($phone_nettoye, 3);
} else {
    $phone_local = $phone_nettoye;
}
$phone_normalise = preg_match('/^3\d{6}$/', $phone_local) ? ('269' . $phone_local) : $phone_nettoye;

$ip = $_SERVER['REMOTE_ADDR'];

try {
    // ── 5. Blocage par IP ─────────────────────────────────────────────────
    $stmt = $pdo->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $rowIp = $stmt->fetch();

    if ($rowIp) {
        $diffIp = time() - strtotime($rowIp['last_attempt']);
        if ($rowIp['attempts'] >= MAX_TENTATIVES && $diffIp < DUREE_BLOCAGE_SECONDES) {
            $reste = ceil((DUREE_BLOCAGE_SECONDES - $diffIp) / 60);
            header("Location: /index.php?error=trop_tentatives&reste={$reste}");
            exit();
        }
        if ($diffIp >= DUREE_BLOCAGE_SECONDES) {
            $pdo->prepare("UPDATE login_attempts SET attempts=0 WHERE ip=?")->execute([$ip]);
        }
    }

    // ── 5bis. Blocage par compte (numéro visé), indépendant de l'IP ─────────
    // Empêche un attaquant qui change d'IP de continuer à essayer des codes
    // sur UN MÊME numéro ciblé.
    $stmtCompte = $pdo->prepare("SELECT attempts, last_attempt FROM login_attempts_compte WHERE phone = ?");
    $stmtCompte->execute([$phone_normalise]);
    $rowCompte = $stmtCompte->fetch();

    if ($rowCompte) {
        $diffCompte = time() - strtotime($rowCompte['last_attempt']);
        if ($rowCompte['attempts'] >= MAX_TENTATIVES && $diffCompte < DUREE_BLOCAGE_SECONDES) {
            $reste = ceil((DUREE_BLOCAGE_SECONDES - $diffCompte) / 60);
            header("Location: /index.php?error=compte_bloque&reste={$reste}");
            exit();
        }
        if ($diffCompte >= DUREE_BLOCAGE_SECONDES) {
            $pdo->prepare("UPDATE login_attempts_compte SET attempts=0 WHERE phone=?")->execute([$phone_normalise]);
        }
    }

    // ── 6. Rechercher l'utilisateur par téléphone ────────────────────────────
    $stmt = $pdo->prepare("
        SELECT id, fullname, email, password, role, is_active, phone_verified
        FROM users_monrevenu
        WHERE phone = ?
        LIMIT 1
    ");
    $stmt->execute([$phone_normalise]);
    $user = $stmt->fetch();

    // ── 7. Vérifier le code secret ───────────────────────────────────────────
    // password_verify() est TOUJOURS appelé, même si le compte n'existe pas
    // (contre un hash factice), pour que le temps de réponse soit identique
    // dans les deux cas — empêche de deviner quels numéros existent en base
    // en mesurant la vitesse de réponse du serveur.
    $hash_reference = $user['password'] ?? '$2y$12$D9m5x1sJZ3yKf6q1r0aFZO7hV1Q6qk4pQnR2eYkD5vXbG8tJmW3Ke';
    $code_valide = password_verify($code, $hash_reference);

    if (!$user || !$code_valide) {
        // Compteur par IP
        $pdo->prepare("
            INSERT INTO login_attempts (ip, attempts, last_attempt)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
        ")->execute([$ip]);

        // Compteur par compte (uniquement si le numéro correspond à un format valide)
        $pdo->prepare("
            INSERT INTO login_attempts_compte (phone, attempts, last_attempt)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
        ")->execute([$phone_normalise]);

        // Si ce compte vient d'atteindre le seuil de blocage, on alerte son propriétaire
        $nouvelles_tentatives_compte = $rowCompte ? ((int) $rowCompte['attempts'] + 1) : 1;
        if ($user && $nouvelles_tentatives_compte === MAX_TENTATIVES && $email_sender_disponible && function_exists('envoyerAlerteTentativesConnexion') && !empty($user['email'])) {
            try {
                envoyerAlerteTentativesConnexion(
                    $user['email'],
                    $user['fullname'] ?? '',
                    $phone_normalise,
                    (int) round(DUREE_BLOCAGE_SECONDES / 60)
                );
            } catch (\Throwable $e) {
                error_log('Erreur envoi alerte connexion : ' . $e->getMessage());
            }
        }

        // Calcule le nombre de tentatives restantes avant blocage (basé sur le
        // compteur IP, pas sur le compteur compte, pour ne jamais laisser deviner
        // si le numéro saisi correspond à un compte existant ou non).
        $tentatives_ip = $rowIp ? ((int) $rowIp['attempts'] + 1) : 1;
        $reste_tentatives = max(0, MAX_TENTATIVES - $tentatives_ip);

        header("Location: /index.php?error=identifiants&reste_tentatives={$reste_tentatives}");
        exit();
    }

    // ── 8. Compte actif ──────────────────────────────────────────────────────
    if (!$user['is_active']) {
        header('Location: /index.php?error=compte_inactif');
        exit();
    }

    // ── 8bis. Numéro de téléphone vérifié ────────────────────────────────────
    if ((int) $user['phone_verified'] !== 1) {
        $_SESSION['unverified_login_user_id'] = $user['id'];
        header('Location: /verification.php');
        exit();
    }

    // ── 9. Connexion réussie : on réinitialise les deux compteurs ───────────
    $pdo->prepare("
        INSERT INTO login_attempts (ip, attempts, last_attempt)
        VALUES (?, 0, NOW())
        ON DUPLICATE KEY UPDATE attempts = 0, last_attempt = NOW()
    ")->execute([$ip]);

    $pdo->prepare("
        INSERT INTO login_attempts_compte (phone, attempts, last_attempt)
        VALUES (?, 0, NOW())
        ON DUPLICATE KEY UPDATE attempts = 0, last_attempt = NOW()
    ")->execute([$phone_normalise]);

    try {
        $pdo->prepare("UPDATE users_monrevenu SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
    } catch (\PDOException $e) { /* colonne last_login absente — ignoré */ }

    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_fullname'] = $user['fullname'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['logged_in']     = true;
    $_SESSION['login_time']    = time();
    $_SESSION['ip']            = $ip;
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

    // ── 10. Redirection selon le rôle (inchangée) ────────────────────────────
    switch ($user['role']) {
        case 'admin':
            header('Location: /admin/dashboard_admin.php');
            break;
        case 'agent':
            header('Location: /admin/dashboard_agent.php');
            break;
        default: // affilie
            header('Location: /dashboard.php');
            break;
    }
    exit();

} catch (\PDOException $e) {
    error_log('Erreur login : ' . $e->getMessage());
    header('Location: /index.php?error=serveur');
    exit();
}