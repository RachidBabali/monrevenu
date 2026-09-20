<?php
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/includs/audit.php';
require_once __DIR__ . '/includs/incident.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/email_sender.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_demander_reset'])) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Votre session a expiré. Rechargez la page puis recommencez.';
        auditCsrf($pdo, 'mot_de_passe_oublie');
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Adresse email invalide. Exemple : nom@exemple.com.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, fullname, email, is_active, status FROM users_monrevenu WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // On ne révèle jamais si l'email existe ou non (protection contre
                // l'énumération de comptes) : le message est identique dans tous les cas.
                if ($user && (int) $user['is_active'] === 1 && $user['status'] === 'active') {
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $code_hash = password_hash($code, PASSWORD_BCRYPT);
                    $expiration = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    $pdo->prepare("UPDATE users_monrevenu SET reset_password_code = ?, reset_password_expires_at = ? WHERE id = ?")
                        ->execute([$code_hash, $expiration, $user['id']]);
                    auditInfo($pdo, ['category' => 'auth', 'action' => 'reinitialisation_demande', 'entity_type' => 'utilisateur',
                        'entity_id' => $user['id'], 'actor_id' => null, 'actor_role' => null]);

                    $resultat = envoyerCodeResetMotDePasse($user['email'], $code, $user['fullname']);

                    if ($resultat['ok']) {
                        $_SESSION['reset_password_user_id'] = $user['id'];
                        $_SESSION['reset_password_email']   = $user['email'];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        header('Location: reinitialiser_mot_de_passe.php');
                        exit();
                    } else {
                        error_log('Erreur envoi email reset pour ' . journalMasquerEmail($email) . ' : ' . ($resultat['error'] ?? 'Erreur inconnue'));
                        $error = "L'email n'a pas pu être envoyé. Réessayez dans un instant.";
                    }
                } else {
                    // Compte inexistant, inactif ou bloqué : même message que le cas normal
                    $success = "Si un compte existe avec cette adresse, un code de réinitialisation vient d'y être envoyé. Pensez à regarder dans les courriers indésirables.";
                }
            } catch (\Throwable $e) {
                $error = messageIncident(incidentEnregistrer($pdo, $e, 'mot_de_passe_oublie'),
                    "La demande a échoué pour une raison technique. Réessayez dans un instant.");
            }
        }
    }
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
$titre_page = 'Mot de passe oublié';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_debut.php';
?>
    <h1 class="text-2xl font-semibold">Mot de passe oublié</h1>
    <p class="mt-2 text-text-2">Indiquez l'adresse email de votre compte. Nous vous envoyons un code à 6 chiffres, valable 10 minutes, pour choisir un nouveau mot de passe.</p>

    <?php if ($error): ?>
      <p class="alerte alerte-danger mt-5" role="alert"><?= ico('circle-alert') ?><span><?= e($error) ?></span></p>
    <?php endif; ?>
    <?php if ($success): ?>
      <p class="alerte alerte-succes mt-5" role="status"><?= ico('mail') ?><span><?= e($success) ?></span></p>
    <?php endif; ?>

    <?php if (!$success): ?>
      <form method="POST" action="" class="mt-6 flex flex-col gap-4">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <div class="champ">
          <label class="champ-label" for="email-reset">Adresse email</label>
          <input class="champ-saisie" type="email" id="email-reset" name="email" required autofocus autocomplete="email" inputmode="email" placeholder="nom@exemple.com" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" name="action_demander_reset" class="btn btn-primaire btn-bloc"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Recevoir le code</span></button>
      </form>
    <?php endif; ?>

    <p class="mt-6 text-center text-sm"><a class="lien" href="/index.php#connexion">Retour à la connexion</a></p>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_fin.php'; ?>
