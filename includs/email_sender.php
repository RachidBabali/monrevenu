<?php
require_once __DIR__ . '/audit.php';
/**
 * email_sender.php, Envoi du code de vérification par email
 * À placer dans : includs/email_sender.php
 *
 * Nécessite PHPMailer. Si vous avez déjà la librairie dans
 * /lib/PHPMailer-master (vue dans votre arborescence côté "jeu"),
 * ajustez simplement le chemin des require_once ci-dessous pour
 * pointer vers cet emplacement partagé, ou copiez le dossier
 * PHPMailer-master à la racine du projet.
 */

require_once __DIR__ . '/env_loader.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/PHPMailer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/SMTP.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//  Configuration SMTP 
// Valeurs lues depuis .env, jamais codées en dur ici.
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER'));
define('SMTP_PASS', env('SMTP_PASS'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'MonRevenu'));

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
        auditEnvoi('email', 'envoyerCodeEmail', true, $destinataire);
        return ['ok' => true];

    } catch (Exception $e) {
        error_log('Erreur envoi email de vérification : ' . $mail->ErrorInfo);
        auditEnvoi('email', 'envoyerCodeEmail', false, $destinataire, $mail->ErrorInfo);
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Gabarit HTML unique des emails : table de 600 px, styles en ligne, logo en URL absolue.
 * $contenuHtml est deja echappe par l'appelant.
 */
function gabaritEmail(string $titre, string $contenuHtml, ?array $bouton = null): string
{
    $base  = rtrim((string) (function_exists('env') ? env('APP_URL', 'https://monrevenu.xyz') : 'https://monrevenu.xyz'), '/');
    $titre = htmlspecialchars($titre, ENT_QUOTES, 'UTF-8');
    $btn   = '';
    if ($bouton) {
        $btn = "<tr><td style='padding:8px 32px 24px;'><a href='" . htmlspecialchars($bouton[1], ENT_QUOTES, 'UTF-8') . "' style='display:inline-block; background:#123F91; color:#ffffff; font-weight:600; font-size:15px; text-decoration:none; padding:12px 20px; border-radius:6px;'>"
            . htmlspecialchars($bouton[0], ENT_QUOTES, 'UTF-8') . "</a></td></tr>";
    }
    return "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>{$titre}</title></head>
<body style='margin:0; padding:0; background:#F6F7F9;'>
<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#F6F7F9;'><tr><td align='center' style='padding:24px 12px;'>
<table role='presentation' width='600' cellpadding='0' cellspacing='0' style='width:100%; max-width:600px; background:#ffffff; border:1px solid #E3E6EB; border-radius:8px; font-family:Arial, Helvetica, sans-serif; color:#141A24;'>
<tr><td style='padding:24px 32px 8px;'><img src='{$base}/assets/img/logo-64.png' width='32' height='32' alt='MonRevenu' style='display:block; border:0;'></td></tr>
<tr><td style='padding:8px 32px 0;'><h1 style='margin:0; font-size:20px; line-height:28px; font-weight:bold; color:#141A24;'>{$titre}</h1></td></tr>
<tr><td style='padding:12px 32px 16px; font-size:15px; line-height:24px; color:#4A5565;'>{$contenuHtml}</td></tr>
{$btn}
<tr><td style='padding:16px 32px 24px; border-top:1px solid #E3E6EB; font-size:12px; line-height:18px; color:#5F6B7C;'>MonRevenu, plateforme d'affiliation. Vous recevez cet email car un compte MonRevenu est associé à cette adresse. Cet email est automatique : pour nous écrire, utilisez contact@monrevenu.xyz.</td></tr>
</table></td></tr></table></body></html>";
}

function blocCodeEmail(string $code): string
{
    $code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    return "<p style='margin:16px 0; padding:16px; background:#F1F3F6; border-radius:6px; text-align:center; font-family:Courier New, monospace; font-size:28px; font-weight:bold; letter-spacing:8px; color:#123F91;'>{$code}</p>";
}

function construireContenuEmail(string $code, string $nom = ''): string
{
    $salutation = $nom ? 'Bonjour ' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . ',' : 'Bonjour,';
    return gabaritEmail(
        'Votre code de vérification',
        "<p style='margin:0 0 8px;'>{$salutation}</p><p style='margin:0;'>Saisissez ce code sur MonRevenu pour activer votre compte :</p>"
        . blocCodeEmail($code)
        . "<p style='margin:0; font-size:13px; color:#5F6B7C;'>Le code expire dans 10 minutes. Si vous n'avez pas demandé ce code, ignorez cet email.</p>"
    );
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
        auditEnvoi('email', 'envoyerCodeResetMotDePasse', true, $destinataire);
        return ['ok' => true];

    } catch (Exception $e) {
        error_log('Erreur envoi email de réinitialisation : ' . $mail->ErrorInfo);
        auditEnvoi('email', 'envoyerCodeResetMotDePasse', false, $destinataire, $mail->ErrorInfo);
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

function construireContenuEmailReset(string $code, string $nom = ''): string
{
    $salutation = $nom ? 'Bonjour ' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . ',' : 'Bonjour,';
    return gabaritEmail(
        'Réinitialisation de votre mot de passe',
        "<p style='margin:0 0 8px;'>{$salutation}</p><p style='margin:0;'>Une réinitialisation du mot de passe a été demandée pour votre compte. Voici votre code :</p>"
        . blocCodeEmail($code)
        . "<p style='margin:0; font-size:13px; color:#5F6B7C;'>Le code expire dans 10 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre mot de passe ne change pas.</p>"
    );
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
        $mail->Subject = 'Tentatives de connexion suspectes sur votre compte MonRevenu';
        $mail->Body    = construireContenuAlerteConnexion($nom, $telephone, $dureeBlocageMinutes);
        $mail->AltBody = "Plusieurs tentatives de connexion incorrectes ont été détectées sur votre compte MonRevenu (numéro $telephone). "
            . "Le compte a été bloqué temporairement pendant $dureeBlocageMinutes minutes par mesure de sécurité. "
            . "Si ce n'était pas vous, changez votre code secret dès que possible.";

        $mail->send();
        auditEnvoi('email', 'envoyerAlerteTentativesConnexion', true, $destinataire);
        return ['ok' => true];

    } catch (Exception $e) {
        error_log('Erreur envoi alerte connexion suspecte : ' . $mail->ErrorInfo);
        auditEnvoi('email', 'envoyerAlerteTentativesConnexion', false, $destinataire, $mail->ErrorInfo);
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

function construireContenuAlerteConnexion(string $nom, string $telephone, int $dureeBlocageMinutes): string
{
    $salutation = $nom ? 'Bonjour ' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . ',' : 'Bonjour,';
    $telephone  = htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8');
    $base       = rtrim((string) (function_exists('env') ? env('APP_URL', 'https://monrevenu.xyz') : 'https://monrevenu.xyz'), '/');
    return gabaritEmail(
        'Tentatives de connexion refusées',
        "<p style='margin:0 0 8px;'>{$salutation}</p>"
        . "<p style='margin:0 0 12px;'>Plusieurs tentatives de connexion avec un mauvais code ont été faites sur votre compte MonRevenu associé au numéro <strong style='color:#141A24;'>{$telephone}</strong>.</p>"
        . "<p style='margin:0 0 12px;'>Par sécurité, le compte est bloqué pendant <strong style='color:#141A24;'>{$dureeBlocageMinutes} minutes</strong>.</p>"
        . "<p style='margin:0 0 4px;'><strong style='color:#141A24;'>C'était vous ?</strong> Réessayez une fois le blocage terminé.</p>"
        . "<p style='margin:0;'><strong style='color:#141A24;'>Ce n'était pas vous ?</strong> Changez votre code secret dès maintenant.</p>",
        ['Changer mon code secret', $base . '/mot_de_passe_oublie.php']
    );
}
