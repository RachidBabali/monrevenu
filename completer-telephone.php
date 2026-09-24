<?php
require_once __DIR__ . '/includs/session.php';
/**
 * completer-telephone.php
 * Saisie manuelle du numéro pour les comptes créés via Google Sign-In sans téléphone vérifié.
 * Plus imposée par redirection : le compte navigue, ses actions sont bloquées côté serveur
 * (auth_middleware.php > etatVerification) et la bannière du tableau de bord propose la
 * vérification par WhatsApp, qui enregistre aussi le numéro du compte.
 */

demarrerSession();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/includs/audit.php';
require_once __DIR__ . '/includs/incident.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/auth_middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/whatsapp_sender.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/config_marche.php';

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
        $phone_country = marcheValide($_POST['phone_country'] ?? '') ?? MARCHE_DEFAUT;

        // Meme normalisation que l'inscription classique (includs/config_marche.php) : valide
        // par longueur nationale, l'indicatif tape par l'utilisateur l'emporte sur le menu.
        $phone_normalise = normaliserNumero($phone_brut, $phone_country) ?? normaliserNumero($phone_brut);
        if ($phone_normalise === null) {
            $erreur = 'Numéro invalide pour le marché choisi (' . marche($phone_country)['longueur_nationale'] . ' chiffres, exemple : ' . marche($phone_country)['exemple_numero'] . ').';
        } else {
            $phone_country = marcheDeNumero($phone_normalise) ?? $phone_country;
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
                // Le numero ajoute devient la source du marche du compte (devise, commissions,
                // moyens de retrait) : voir includs/config_marche.php.
                $pdo->prepare(
                    "UPDATE users_monrevenu
                     SET phone = ?, pays_code = ?, pays_nom = ?, verification_method = 'whatsapp',
                         verification_code = ?, code_expires_at = ?, code_sent_at = NOW()
                     WHERE id = ?"
                )->execute([$phone_normalise, $phone_country, marche($phone_country)['nom'], $code_verification_hash, $code_expiration, $user_id]);
                auditInfo($pdo, ['category' => 'compte', 'action' => 'telephone_ajout', 'entity_type' => 'utilisateur', 'entity_id' => $user_id,
                    'after' => ['phone' => $phone_normalise, 'pays_code' => $phone_country, 'verification_method' => 'whatsapp']]);

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
                $erreur = messageIncident(incidentEnregistrer($pdo, $e, 'completer-telephone'),
                    "L'envoi a échoué pour une raison technique. Réessayez dans un instant.");
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
            <?php foreach (marches() as $codeMarcheOption => $configMarcheOption): ?>
              <option value="<?= e($codeMarcheOption) ?>"<?= ($_POST['phone_country'] ?? MARCHE_DEFAUT) === $codeMarcheOption ? ' selected' : '' ?>><?= e($codeMarcheOption) ?> +<?= e($configMarcheOption['indicatif']) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="champ-saisie" type="tel" id="tel-complet" name="phone" required inputmode="numeric"
                 maxlength="<?= (int) max(array_column(marches(), 'longueur_nationale')) ?>" autocomplete="tel-national"
                 placeholder="<?= e(marche(MARCHE_DEFAUT)['exemple_numero']) ?>" aria-describedby="aide-tel-complet">
        </div>
        <p class="champ-aide" id="aide-tel-complet"><?php foreach (marches() as $configMarcheAide): ?><?= e($configMarcheAide['nom']) ?> : <?= (int) $configMarcheAide['longueur_nationale'] ?> chiffres. <?php endforeach; ?></p>
      </div>
      <button type="submit" class="btn btn-primaire btn-bloc"><?= ico('loader-circle', 'ico-charge') ?><span data-libelle>Recevoir le code sur WhatsApp</span></button>
    </form>
    <p class="mt-4 text-sm text-text-3">Le numéro doit être le vôtre : il sert à vérifier votre compte et à vous contacter.</p>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_public_fin.php'; ?>
