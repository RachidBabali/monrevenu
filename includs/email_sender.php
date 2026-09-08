<?php
/**
 * email_sender.php — Envoi du code de vérification par email
 * À placer dans : includs/email_sender.php
 *
 * Nécessite PHPMailer. Si vous avez déjà la librairie dans
 * /lib/PHPMailer-master (vue dans votre arborescence côté "jeu"),
 * ajustez simplement le chemin des require_once ci-dessous pour
 * pointer vers cet emplacement partagé, ou copiez le dossier
 * PHPMailer-master à la racine du projet.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/PHPMailer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/SMTP.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Configuration SMTP ───────────────────────────────────────────────
// ⚠️ À adapter avec vos vrais identifiants. Ne laissez jamais un mot
// de passe en clair dans un fichier commité sur un dépôt public :
// utilisez plutôt des variables d'environnement (getenv()) ou un
// fichier config.php exclu du dépôt (.gitignore).
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'bigrach2006@gmail.com');
define('SMTP_PASS', 'wbtt lssd ticu alns'); // mot de passe d'application Gmail, pas le mot de passe du compte
define('SMTP_FROM_NAME', 'MonRevenu');

/**
 * Envoie le code de vérification par email.
 *
 * @return array{ok: bool, error?: string}
 */
function envoyerCodeEmail(string $destinataire, string $code, string $nomDestinataire = ''): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($destinataire, $nomDestinataire);

        $mail->isHTML(true);
        $mail->Subject = 'Votre code de vérification MonRevenu';
        $mail->Body    = construireContenuEmail($code, $nomDestinataire);
        $mail->AltBody = "Votre code de vérification MonRevenu est : $code\nCe code expire dans 10 minutes.";

        $mail->send();
        return ['ok' => true];

    } catch (Exception $e) {
        error_log('Erreur envoi email de vérification : ' . $mail->ErrorInfo);
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

function construireContenuEmail(string $code, string $nom = ''): string
{
    $salutation = $nom ? htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') : 'Bonjour';
    return "
        <div style='font-family:Arial,sans-serif; max-width:480px; margin:0 auto; padding:24px; background:#f8fafc; border-radius:12px;'>
            <h2 style='color:#1e3a8a; margin-bottom:8px;'>MonRevenu</h2>
            <p>Bonjour {$salutation},</p>
            <p>Voici votre code de vérification :</p>
            <div style='font-size:28px; font-weight:700; letter-spacing:0.3em; text-align:center; color:#1e3a8a; background:#fff; padding:16px; border-radius:8px; margin:16px 0;'>
                {$code}
            </div>
            <p style='color:#64748b; font-size:13px;'>Ce code expire dans 10 minutes. Si vous n'avez pas demandé ce code, ignorez cet email.</p>
        </div>
    ";
}

/**
 * Envoie le code de réinitialisation de mot de passe par email.
 *
 * @return array{ok: bool, error?: string}
 */
function envoyerCodeResetMotDePasse(string $destinataire, string $code, string $nomDestinataire = ''): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($destinataire, $nomDestinataire);

        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation de votre mot de passe MonRevenu';
        $mail->Body    = construireContenuEmailReset($code, $nomDestinataire);
        $mail->AltBody = "Votre code de réinitialisation MonRevenu est : $code\nCe code expire dans 10 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.";

        $mail->send();
        return ['ok' => true];

    } catch (Exception $e) {
        error_log('Erreur envoi email de réinitialisation : ' . $mail->ErrorInfo);
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

function construireContenuEmailReset(string $code, string $nom = ''): string
{
    $salutation = $nom ? htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') : 'Bonjour';
    return "
        <div style='font-family:Arial,sans-serif; max-width:480px; margin:0 auto; padding:24px; background:#f8fafc; border-radius:12px;'>
            <h2 style='color:#1e3a8a; margin-bottom:8px;'>MonRevenu</h2>
            <p>Bonjour {$salutation},</p>
            <p>Une demande de réinitialisation de mot de passe a été effectuée pour votre compte. Voici votre code :</p>
            <div style='font-size:28px; font-weight:700; letter-spacing:0.3em; text-align:center; color:#1e3a8a; background:#fff; padding:16px; border-radius:8px; margin:16px 0;'>
                {$code}
            </div>
            <p style='color:#64748b; font-size:13px;'>Ce code expire dans 10 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email — votre mot de passe restera inchangé.</p>
        </div>
    ";
}

/**
 * Envoie une alerte de sécurité au propriétaire du compte quand trop de
 * tentatives de connexion échouées ont été détectées sur son compte.
 *
 * @return array{ok: bool, error?: string}
 */
function envoyerAlerteTentativesConnexion(string $destinataire, string $nom, string $telephone, int $dureeBlocageMinutes): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($destinataire, $nom);

        $mail->isHTML(true);
        $mail->Subject = '⚠️ Tentatives de connexion suspectes sur votre compte MonRevenu';
        $mail->Body    = construireContenuAlerteConnexion($nom, $telephone, $dureeBlocageMinutes);
        $mail->AltBody = "Plusieurs tentatives de connexion incorrectes ont été détectées sur votre compte MonRevenu (numéro $telephone). "
            . "Le compte a été bloqué temporairement pendant $dureeBlocageMinutes minutes par mesure de sécurité. "
            . "Si ce n'était pas vous, changez votre code secret dès que possible.";

        $mail->send();
        return ['ok' => true];

    } catch (Exception $e) {
        error_log('Erreur envoi alerte connexion suspecte : ' . $mail->ErrorInfo);
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

function construireContenuAlerteConnexion(string $nom, string $telephone, int $dureeBlocageMinutes): string
{
    $salutation = $nom ? htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') : 'Bonjour';
    $telephone  = htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8');
    return "
        <div style='font-family:Arial,sans-serif; max-width:480px; margin:0 auto; padding:24px; background:#f8fafc; border-radius:12px;'>
            <h2 style='color:#dc2626; margin-bottom:8px;'>⚠️ Activité suspecte détectée</h2>
            <p>Bonjour {$salutation},</p>
            <p>Nous avons détecté <strong>plusieurs tentatives de connexion échouées</strong> sur votre compte MonRevenu associé au numéro <strong>{$telephone}</strong>.</p>
            <p>Par mesure de sécurité, ce compte a été bloqué temporairement pendant <strong>{$dureeBlocageMinutes} minutes</strong>.</p>
            <div style='background:#fff; border-left:4px solid #dc2626; padding:12px 16px; border-radius:6px; margin:16px 0;'>
                <p style='margin:0; font-weight:600; color:#1e3a8a;'>Est-ce bien vous qui avez essayé de vous connecter ?</p>
                <p style='margin:8px 0 0; font-size:13px; color:#64748b;'>
                    — Si <strong>oui</strong>, vous pouvez simplement réessayer une fois le blocage terminé.<br>
                    — Si <strong>non</strong>, quelqu'un essaie peut-être d'accéder à votre compte. Nous vous conseillons de réinitialiser votre code secret dès que possible via « Mot de passe oublié ».
                </p>
            </div>
            <p style='color:#64748b; font-size:12px;'>Cet email est automatique, merci de ne pas y répondre directement.</p>
        </div>
    ";
}