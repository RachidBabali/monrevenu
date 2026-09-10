<?php
/**
 * test_smtp.php — Diagnostic temporaire de la configuration email
 * À placer dans : admin/test_smtp.php
 * ⚠️ Protégé par la connexion admin. À SUPPRIMER du serveur une fois le
 * diagnostic terminé — ne pas le laisser en ligne durablement.
 *
 * Usage : connecte-toi en admin sur le site, puis va sur
 *   https://monrevenu.xyz/admin/test_smtp.php
 * Optionnel : https://monrevenu.xyz/admin/test_smtp.php?to=tonadresse@exemple.com
 * pour recevoir un vrai email de test (sinon il s'envoie à SMTP_USER lui-même).
 */

require_once '../basse_de_donner/monrevenu_bd.php';
require_once '../includs/env_loader.php';
require_once 'auth_middleware.php';

$admin = requireRole($pdo, 'admin');

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/PHPMailer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/SMTP.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/plain; charset=utf-8');

echo "=== 1. Vérification des variables .env ===\n";

$smtpHost = env('SMTP_HOST');
$smtpPort = env('SMTP_PORT');
$smtpUser = env('SMTP_USER');
$smtpPass = env('SMTP_PASS');

echo "SMTP_HOST : " . ($smtpHost ? "OK ($smtpHost)" : "❌ MANQUANT") . "\n";
echo "SMTP_PORT : " . ($smtpPort ? "OK ($smtpPort)" : "❌ MANQUANT") . "\n";
echo "SMTP_USER : " . ($smtpUser ? "OK ($smtpUser)" : "❌ MANQUANT") . "\n";
echo "SMTP_PASS : " . ($smtpPass ? "OK (" . strlen($smtpPass) . " caractères, jamais affiché en clair)" : "❌ MANQUANT") . "\n";

if (!$smtpHost || !$smtpPort || !$smtpUser || !$smtpPass) {
    echo "\n⚠️ Au moins une variable est manquante dans .env — corrige ça d'abord, inutile de continuer le test.\n";
    exit;
}

echo "\n=== 2. Tentative d'envoi réel (avec le détail de la conversation SMTP) ===\n\n";

$destinataire = $_GET['to'] ?? $smtpUser;

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; // affiche l'échange complet avec le serveur Gmail
    $mail->Debugoutput = function ($str, $level) {
        echo $str . "\n";
    };

    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int) $smtpPort;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtpUser, 'MonRevenu (test)');
    $mail->addAddress($destinataire);

    $mail->Subject = 'Test SMTP MonRevenu';
    $mail->Body    = 'Ceci est un email de test envoyé depuis test_smtp.php.';

    $mail->send();

    echo "\n✅ SUCCÈS — l'email a bien été envoyé à {$destinataire}.\n";
} catch (Exception $e) {
    echo "\n❌ ÉCHEC — PHPMailer a renvoyé : " . $mail->ErrorInfo . "\n";
}

echo "\n=== Fin du diagnostic — pense à supprimer ce fichier du serveur ===\n";