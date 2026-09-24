<?php
require_once __DIR__ . '/includs/session.php';
demarrerSession();

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/includs/audit.php';
require_once __DIR__ . '/includs/incident.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/email_sender.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/mot_de_passe.php';

$user_id = $_SESSION['reset_password_user_id'] ?? null;
if (!$user_id) {
    header('Location: /mot_de_passe_oublie.php'); exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

$stmtUser = $pdo->prepare("SELECT id, fullname, email, reset_password_code, reset_password_expires_at FROM users_monrevenu WHERE id = ? LIMIT 1");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

if (!$user) {
    unset($_SESSION['reset_password_user_id'], $_SESSION['reset_password_email']);
    header('Location: /mot_de_passe_oublie.php'); exit();
}

/* ============================================================
   TRAITEMENT : RENVOYER UN NOUVEAU CODE (par email, pour prouver l'identité)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_renvoyer'])) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Votre session a expiré. Rechargez la page puis recommencez.';
        auditCsrf($pdo, 'reinitialisation_renvoi');
    } else {
        $nouveau_code_email = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $nouveau_hash = password_hash($nouveau_code_email, PASSWORD_BCRYPT);
        $nouvelle_expiration = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $resultat = envoyerCodeResetMotDePasse($user['email'], $nouveau_code_email, $user['fullname']);

        if ($resultat['ok']) {
            $pdo->prepare("UPDATE users_monrevenu SET reset_password_code = ?, reset_password_expires_at = ? WHERE id = ?")
                ->execute([$nouveau_hash, $nouvelle_expiration, $user_id]);
            auditInfo($pdo, ['category' => 'auth', 'action' => 'reinitialisation_code_renvoye', 'entity_type' => 'utilisateur',
                'entity_id' => $user_id, 'actor_id' => null, 'actor_role' => null]);

            $success = 'Nouveau code envoyé par email.';

            $stmtUser->execute([$user_id]);
            $user = $stmtUser->fetch();
        } else {
            error_log('Erreur renvoi code reset pour ' . journalMasquerEmail($user['email']) . ' : ' . ($resultat['error'] ?? 'Erreur inconnue'));
            $error = "Impossible d'envoyer le code par email. Veuillez réessayer.";
        }
    }
}

/* ============================================================
   TRAITEMENT : VALIDER LE CODE EMAIL ET DÉFINIR UN NOUVEAU MOT DE PASSE
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reinitialiser'])) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Votre session a expiré. Rechargez la page puis recommencez.';
        auditCsrf($pdo, 'reinitialisation');
    } else {
        $code_email_saisi = trim($_POST['code'] ?? '');
        $nouveau_code      = trim((string) ($_POST['nouveau_code'] ?? ''));
        $confirmation_code = trim((string) ($_POST['confirmation_code'] ?? ''));

        if (empty($user['reset_password_code']) || empty($user['reset_password_expires_at'])) {
            $error = "Aucun code actif. Merci de redemander un code.";
        } elseif (strtotime($user['reset_password_expires_at']) < time()) {
            $error = "Ce code a expiré. Merci de redemander un code.";
        } elseif (!password_verify($code_email_saisi, $user['reset_password_code'])) {
            $error = "Code incorrect.";
            // Cinq essais au plus : au-dela, le code est detruit (un code a 6 chiffres ne doit pas pouvoir etre devine par force brute)
            $_SESSION['reset_essais'] = (int) ($_SESSION['reset_essais'] ?? 0) + 1;
            if ($_SESSION['reset_essais'] >= 5) {
                $pdo->prepare("UPDATE users_monrevenu SET reset_password_code = NULL, reset_password_expires_at = NULL WHERE id = ?")->execute([$user_id]);
                $_SESSION['reset_essais'] = 0;
                $error = "Trop d'essais : ce code n'est plus valable. Demandez-en un nouveau.";
            }
            auditInfo($pdo, ['category' => 'auth', 'action' => 'reinitialisation_echec', 'result' => 'echec', 'entity_type' => 'utilisateur',
                'entity_id' => $user_id, 'actor_id' => null, 'actor_role' => null]);
        } elseif (($erreur_mdp = erreurMotDePasse($nouveau_code)) !== null) {
            $error = $erreur_mdp;
        } else {
            if ($nouveau_code !== $confirmation_code) {
                $error = "La confirmation ne correspond pas au nouveau mot de passe.";
            } else {
                try {
                    $hash = password_hash($nouveau_code, PASSWORD_BCRYPT, ['cost' => 12]);

                    $pdo->prepare(
                        "UPDATE users_monrevenu SET password = ?, reset_password_code = NULL, reset_password_expires_at = NULL WHERE id = ?"
                    )->execute([$hash, $user_id]);
                    auditInfo($pdo, ['category' => 'auth', 'action' => 'reinitialisation_validee', 'entity_type' => 'utilisateur',
                        'entity_id' => $user_id, 'actor_id' => $user_id, 'actor_role' => null]);

                    unset($_SESSION['reset_password_user_id'], $_SESSION['reset_password_email']);
                    $_SESSION['flash_success'] = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.";

                    header('Location: /index.php');
                    exit();
                } catch (\Throwable $e) {
                    $error = messageIncident(incidentEnregistrer($pdo, $e, 'reinitialiser_mot_de_passe'),
                        "Une erreur est survenue. Veuillez réessayer.");
                }
            }
        }
    }
}

function masquerEmailReset(string $email): string
{
    $parties = explode('@', $email);
    if (count($parties) !== 2) return $email;
    $local = $parties[0];
    $masque = mb_substr($local, 0, 2) . str_repeat('*', max(mb_strlen($local) - 2, 2));
    return $masque . '@' . $parties[1];
}
$email_masque = masquerEmailReset($user['email']);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
$titre_page   = 'Nouveau mot de passe';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_debut.php';
?>
    <h1 class="text-2xl font-semibold">Choisir un nouveau mot de passe</h1>
    <p class="mt-2 text-text-2">Un code à 6 chiffres a été envoyé à <strong class="font-medium text-text"><?= e($email_masque) ?></strong>. Il est valable 10 minutes.</p>

    <?php if ($error): ?>
      <p class="alerte alerte-danger mt-5" role="alert"><?= ico('circle-alert') ?><span><?= e($error) ?></span></p>
    <?php endif; ?>
    <?php if ($success): ?>
      <p class="alerte alerte-succes mt-5" role="status"><?= ico('circle-check') ?><span><?= e($success) ?></span></p>
    <?php endif; ?>

    <form method="POST" action="" id="formReset" class="mt-6 flex flex-col gap-4">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="champ">
        <label class="champ-label" for="code-email">Code reçu par email</label>
        <input class="champ-saisie chiffres font-mono tracking-[.3em]" type="text" id="code-email" name="code" required maxlength="6" minlength="6"
               inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" autofocus placeholder="000000">
      </div>
      <div class="champ">
        <label class="champ-label" for="nouveau_code">Nouveau mot de passe</label>
        <input class="champ-saisie" type="password" id="nouveau_code" name="nouveau_code" required minlength="8" maxlength="64"
               autocomplete="new-password" aria-describedby="aide-nouveau-code">
        <p class="champ-aide" id="aide-nouveau-code"><?= e(MOT_DE_PASSE_AIDE) ?></p>
      </div>
      <div class="champ">
        <label class="champ-label" for="confirmation_code">Confirmer le mot de passe</label>
        <input class="champ-saisie" type="password" id="confirmation_code" name="confirmation_code" required minlength="8" maxlength="64"
               autocomplete="new-password">
      </div>
      <button type="submit" name="action_reinitialiser" class="btn btn-primaire btn-bloc"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Enregistrer le mot de passe</span></button>
    </form>

    <form method="POST" action="" class="mt-3">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <button type="submit" name="action_renvoyer" class="btn btn-discret btn-bloc">Je n'ai rien reçu : renvoyer le code</button>
    </form>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_fin.php'; ?>
