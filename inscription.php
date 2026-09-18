<?php
// Ancienne adresse de retour d'erreur de includs/register_handler.php : renvoie vers la landing,
// ou la feuille d'inscription s'ouvre avec le message (parametres conserves).
$requete = $_SERVER['QUERY_STRING'] ?? '';
header('Location: /index.php' . ($requete !== '' ? '?' . $requete : ''), true, 302);
exit();
