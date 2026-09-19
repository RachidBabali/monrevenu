<?php
/**
 * completer-telephone.php
 * Page obligatoire pour les comptes créés/connectés via Google Sign-In
 * qui n'ont pas encore de téléphone vérifié (phone_verified = 0).
 * Tant que ce n'est pas fait, exigerTelephoneVerifie() (auth_middleware.php)
 * redirige systématiquement ici depuis les autres pages protégées.
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/includs/audit.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/whatsapp_sender.php';

exigerConnexion();

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT fullname, phone, phone_verified FROM users_monrevenu WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /index.php'); exit();
}

// Déjà vérifié : rien à faire ici.
if ((int) $user['phone_verified'] === 1) {
    header('Location: /dashboard.php'); exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreur = 'Votre session a expiré. Rechargez la page puis recommencez.';
        auditCsrf($pdo, 'completer_telephone');
    } else {
        $phone_brut    = trim($_POST['phone'] ?? '');
        $phone_country = trim($_POST['phone_country'] ?? 'KM');
        $phone_nettoye = preg_replace('/[^\d]/', '', $phone_brut);

        // Même logique de normalisation que l'inscription classique (register_handler.php)
        if (str_starts_with($phone_nettoye, '00269')) {
            $phone_local = substr($phone_nettoye, 5); $phone_country = 'KM';
        } elseif (str_starts_with($phone_nettoye, '00221')) {
            $phone_local = substr($phone_nettoye, 5); $phone_country = 'SN';
        } elseif (str_starts_with($phone_nettoye, '269') && strlen($phone_nettoye) === 10) {
            $phone_local = substr($phone_nettoye, 3); $phone_country = 'KM';
        } elseif (str_starts_with($phone_nettoye, '221') && strlen($phone_nettoye) === 12) {
            $phone_local = substr($phone_nettoye, 3); $phone_country = 'SN';
        } else {
            $phone_local = $phone_nettoye;
        }

        if ($phone_country === 'SN') {
            if (!preg_match('/^7\d{8}$/', $phone_local)) {
                $erreur = 'Numéro sénégalais invalide (ex: 771234567).';
            } else {
                $phone_normalise = '221' . $phone_local;
            }
        } else {
            if (!preg_match('/^[34]\d{6}$/', $phone_local)) {
                $erreur = 'Numéro comorien invalide (ex: 3212345 ou 4212345).';
            } else {
                $phone_normalise = '269' . $phone_local;
            }
        }

        if (!$erreur) {
            // Le numéro ne doit appartenir à aucun autre compte.
            $stmtExiste = $pdo->prepare("SELECT id FROM users_monrevenu WHERE phone = ? AND id != ? AND status != 'deleted'");
            $stmtExiste->execute([$phone_normalise, $user_id]);
            if ($stmtExiste->fetch()) {
                $erreur = 'Ce numéro est déjà associé à un autre compte MonRevenu.';
            }
        }

        if (!$erreur) {
            $code_verification      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $code_verification_hash = password_hash($code_verification, PASSWORD_BCRYPT);
            $code_expiration        = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            try {
                $pdo->prepare(
                    "UPDATE users_monrevenu
                     SET phone = ?, verification_method = 'whatsapp',
                         verification_code = ?, code_expires_at = ?, code_sent_at = NOW()
                     WHERE id = ?"
                )->execute([$phone_normalise, $code_verification_hash, $code_expiration, $user_id]);
                auditInfo($pdo, ['category' => 'compte', 'action' => 'telephone_ajout', 'entity_type' => 'utilisateur', 'entity_id' => $user_id,
                    'after' => ['phone' => $phone_normalise, 'verification_method' => 'whatsapp']]);

                $resultatEnvoi = envoyerCodeWhatsApp($phone_normalise, $code_verification);

                if (!$resultatEnvoi['ok']) {
                    error_log("completer-telephone.php : échec envoi WhatsApp pour user {$user_id} : " . ($resultatEnvoi['erreur'] ?? ''));
                    $erreur = "Impossible d'envoyer le code par WhatsApp pour le moment. Réessayez dans quelques instants ou contactez le support.";
                } else {
                    $_SESSION['pending_verification_user_id'] = $user_id;
                    header('Location: /verification.php?nouveau=1');
                    exit();
                }
            } catch (PDOException $e) {
                error_log('completer-telephone.php : ' . $e->getMessage());
                $erreur = "L'envoi a échoué pour une raison technique. Réessayez dans un instant.";
            }
        }
    }
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';
$titre_page = 'Numéro WhatsApp';
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_debut.php';
?>
    <h1 class="text-2xl font-semibold">Bonjour, <?= e(explode(' ', $user['fullname'])[0]) ?></h1>
    <p class="mt-2 text-text-2">Votre compte a été créé avec Google. Pour accéder au catalogue et à vos liens d'affiliation, indiquez un numéro WhatsApp à votre nom. Nous y envoyons un code de vérification.</p>

    <?php if ($erreur): ?>
      <p class="alerte alerte-danger mt-5" role="alert"><?= ico('circle-alert') ?><span><?= e($erreur) ?></span></p>
    <?php endif; ?>

    <form method="POST" class="mt-6 flex flex-col gap-4">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="champ">
        <label class="champ-label" for="tel-complet">Numéro WhatsApp</label>
        <div class="champ-groupe">
          <label class="sr-only" for="pays-complet">Pays</label>
          <select class="champ-saisie w-[118px] shrink-0 rounded-r-none border-r-0 pr-8" id="pays-complet" name="phone_country" autocomplete="tel-country-code">
            <option value="SN">SN +221</option>
            <option value="KM">KM +269</option>
          </select>
          <input class="champ-saisie" type="tel" id="tel-complet" name="phone" required inputmode="numeric" maxlength="12" autocomplete="tel-national" placeholder="77 123 45 67" aria-describedby="aide-tel-complet">
        </div>
        <p class="champ-aide" id="aide-tel-complet">Sénégal : 9 chiffres commençant par 7. Comores : 7 chiffres commençant par 3 ou 4.</p>
      </div>
      <button type="submit" class="btn btn-primaire btn-bloc"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Recevoir le code sur WhatsApp</span></button>
    </form>
    <p class="mt-4 text-sm text-text-3">Le numéro doit être le vôtre : il sert à vérifier votre compte et à vous contacter.</p>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_fin.php'; ?>
