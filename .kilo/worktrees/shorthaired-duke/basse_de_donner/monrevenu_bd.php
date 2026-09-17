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
    die("Désolé, une erreur est survenue lors de la connexion à la base de données : " . $e->getMessage());
}
?>