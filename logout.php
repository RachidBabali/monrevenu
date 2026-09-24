<?php
require_once __DIR__ . '/includs/session.php';
// Tout au début de index.php, avant le HTML
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    demarrerSession();
    if (!empty($_SESSION['user_id'])) {
        require_once __DIR__ . '/basse_de_donner/monrevenu_bd.php';
        require_once __DIR__ . '/includs/audit.php';
        auditInfo($pdo, ['category' => 'auth', 'action' => 'deconnexion', 'entity_type' => 'utilisateur', 'entity_id' => (int) $_SESSION['user_id']]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    // Redirige vers la page d'accueil proprement
    header('Location: /index.php');
    exit;
}

// Continuez avec le reste de votre code normal (vérification session, rôle, contenu...)