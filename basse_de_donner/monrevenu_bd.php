<?php
/**
 * Configuration de la base de données - Mon Revenu
 * Les identifiants sont lus depuis .env (jamais codés en dur ici).
 */

require_once __DIR__ . '/../includs/env_loader.php';

$host     = env('DB_HOST', 'localhost');
$dbname   = env('DB_NAME');
$username = env('DB_USER');
$password = env('DB_PASS');
$charset  = 'utf8mb4';

// Options PDO pour la sécurité et la performance
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // Detail technique dans le journal d'erreurs seulement : jamais d'hote, d'utilisateur ni de message SQL a l'ecran
    error_log('[monrevenu_bd] connexion impossible : ' . $e->getCode());
    http_response_code(503);
    die("Le service est momentanément indisponible. Réessayez dans quelques minutes.");
}
?>