<?php
/**
 * google_auth_handler.php
 * Vérifie le jeton d'identité envoyé par le bouton "Se connecter avec Google",
 * puis connecte l'utilisateur s'il existe déjà (recherche par email),
 * ou crée son compte s'il n'existe pas encore.
 *
 * À placer dans : includs/google_auth_handler.php
 *
 * IMPORTANT : la constante GOOGLE_CLIENT_ID ci-dessous doit être EXACTEMENT
 * la même valeur que celle mise dans le <meta name="google-signin-client_id">
 * de index.php / inscription.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php'; define('GOOGLE_CLIENT_ID', '687334130412-4oatucl9n2d8fui8jio6ksa8mmcffh1b.apps.googleusercontent.com');

function repondre(bool $success, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success], $extra));
    exit();
}

// ── 1. POST uniquement ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    repondre(false, ['error' => 'Méthode invalide.']);
}

// ── 2. CSRF ──────────────────────────────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    repondre(false, ['error' => 'Session expirée, veuillez recharger la page.']);
}

// ── 3. Jeton reçu ──────────────────────────────────────────────────────────
$credential = trim($_POST['credential'] ?? '');
if (!$credential) {
    repondre(false, ['error' => 'Jeton Google manquant.']);
}

// ── 4. Vérification du jeton auprès de Google ────────────────────────────────
// On utilise le point de terminaison public tokeninfo : simple, pas besoin
// d'installer une librairie (google/apiclient) via Composer.
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
$reponse_brute = curl_exec($ch);
$code_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code_http !== 200 || !$reponse_brute) {
    repondre(false, ['error' => 'Jeton Google invalide ou expiré.']);
}

$payload = json_decode($reponse_brute, true);

// Vérifie que le jeton a bien été émis pour NOTRE application
if (!$payload || ($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    repondre(false, ['error' => 'Jeton Google non reconnu.']);
}

// Vérifie que Google a bien confirmé l'email
if (($payload['email_verified'] ?? 'false') !== 'true') {
    repondre(false, ['error' => 'Email Google non vérifié.']);
}

$google_id = $payload['sub'] ?? '';
$email     = strtolower(trim($payload['email'] ?? ''));
$fullname  = trim($payload['name'] ?? ($payload['given_name'] ?? 'Utilisateur Google'));

if (!$email || !$google_id) {
    repondre(false, ['error' => 'Impossible de récupérer les informations Google.']);
}

try {
    // ── 5. Chercher un compte existant (par google_id, puis par email) ──────
    $stmt = $pdo->prepare("
        SELECT id, fullname, email, role, is_active, phone_verified
        FROM users_monrevenu
        WHERE google_id = ? OR email = ?
        LIMIT 1
    ");
    $stmt->execute([$google_id, $email]);
    $user = $stmt->fetch();

    require_once __DIR__ . '/geoip.php';
    $pays = detecterPaysVisiteur();

    if ($user) {
        // Compte existant : on s'assure que google_id est bien enregistré
        // (utile si la personne s'était inscrite par téléphone avant, et
        // relie maintenant son compte à Google).
        $pdo->prepare("UPDATE users_monrevenu SET google_id = ? WHERE id = ? AND (google_id IS NULL OR google_id = '')")
            ->execute([$google_id, $user['id']]);

        if (!$user['is_active']) {
            repondre(false, ['error' => 'Votre compte est désactivé. Contactez le support.']);
        }

        $user_id        = (int) $user['id'];
        $user_role      = $user['role'];
        $phone_verified = (int) $user['phone_verified'];

    } else {
        // ── 6. Créer un nouveau compte ───────────────────────────────────────
        // Pas de téléphone, pas de code secret : phone_verified reste à 0
        // jusqu'à ce que la personne valide le code WhatsApp affiché en
        // bannière sur le dashboard (webhook_whatsapp.php). Tant que ce
        // n'est pas fait, l'accès aux produits d'affiliation reste bloqué
        // (voir includs/auth_middleware.php > exigerAffiliationDebloquee).
        $stmt = $pdo->prepare("
            INSERT INTO users_monrevenu
                (fullname, email, google_id, phone, phone_verified, pays_code, pays_nom, password, role, balance, is_active, created_at)
            VALUES
                (:fullname, :email, :google_id, NULL, 0, :pays_code, :pays_nom, :password, 'affilie', 0.00, 1, NOW())
        ");
        $stmt->execute([
            ':fullname' => $fullname,
            ':email'    => $email,
            ':google_id'=> $google_id,
            ':pays_code'=> $pays['code'],
            ':pays_nom' => $pays['nom'],
            // Mot de passe factice inutilisable : ce compte ne pourra se
            // connecter que via Google tant qu'aucun code secret n'est défini.
            ':password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
        ]);

        $user_id        = (int) $pdo->lastInsertId();
        $user_role      = 'affilie';
        $phone_verified = 0;
    }

    // Rafraîchit le pays détecté à chaque connexion (utile si le compte a
    // changé d'appareil/réseau depuis sa création).
    $pdo->prepare("UPDATE users_monrevenu SET pays_code = ?, pays_nom = ? WHERE id = ?")
        ->execute([$pays['code'], $pays['nom'], $user_id]);

    // ── 7. Ouvrir la session ─────────────────────────────────────────────────
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user_id;
    $_SESSION['user_fullname'] = $fullname;
    $_SESSION['user_email']    = $email;
    $_SESSION['user_role']     = $user_role;
    $_SESSION['logged_in']     = true;
    $_SESSION['login_time']    = time();
    $_SESSION['ip']            = $_SERVER['REMOTE_ADDR'];
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

    try {
        $pdo->prepare("UPDATE users_monrevenu SET last_login = NOW() WHERE id = ?")->execute([$user_id]);
    } catch (\PDOException $e) { /* colonne last_login absente — ignoré */ }

    // Téléphone non vérifié : direction le dashboard quand même (la bannière
    // de vérification WhatsApp y prend le relais) — plus de redirection vers
    // une page de complétion séparée, quel que soit le rôle.
    if ($phone_verified !== 1) {
        repondre(true, ['redirect' => '/dashboard.php']);
    }

    $redirect = match ($user_role) {
        'admin' => '/admin/dashboard_admin.php',
        'agent' => '/admin/dashboard_agent.php',
        default => '/dashboard.php',
    };

    repondre(true, ['redirect' => $redirect]);

} catch (\PDOException $e) {
    error_log('Erreur google_auth_handler : ' . $e->getMessage());
    repondre(false, ['error' => 'Erreur serveur, veuillez réessayer.']);
}