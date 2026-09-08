<?php
/**
 * Configuration de la base de données - Mon Revenu
 */

$host     = 'localhost';
$dbname   = 'u783994563_mon_revenu_db';
$username = 'u783994563_rachid';
$password = '123rachiD';
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