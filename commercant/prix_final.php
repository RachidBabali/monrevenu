<?php
/**
 * commercant/prix_final.php : apercu du prix affiche au client pour un prix net saisi.
 * Reserve au commercant connecte ; ne renvoie que le prix final (jamais tranches, taux ni repartition).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/commercant.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';

$profil = exigerCommercant($pdo);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$net = (float) str_replace([' ', ','], ['', '.'], (string) ($_GET['net'] ?? ''));
if ($net < 100 || $net > 10000000) { echo json_encode(['ok' => false]); exit; }
echo json_encode(['ok' => true, 'prix_final' => commissionCalculer($net, commissionConfigMarche($profil['marche']))['prix_final']]);
