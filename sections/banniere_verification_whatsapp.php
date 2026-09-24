<?php
/**
 * À inclure en haut de dashboard.php (et idéalement partout où l'utilisateur peut
 * naviguer), UNIQUEMENT si $_SESSION['phone_verified'] != 1 (ou récupéré depuis $pdo).
 *
 * Cette bannière NE bloque PAS la navigation : elle informe. Le blocage réel est côté serveur
 * (includs/auth_middleware.php : etatVerification, compteVerifie, exigerAffiliationDebloquee).
 * Compte sans numéro (inscription Google) : le numéro WhatsApp qui envoie le code devient celui du compte.
 *
 * Attendu disponible dans le scope : $pdo (PDO), $_SESSION['user_id'].
 */

require_once __DIR__ . '/../includs/whatsapp_verif_helpers.php';
require_once __DIR__ . '/../includs/ui.php';

$verif = genererOuRecupererCodeVerificationWhatsapp($pdo, $_SESSION['user_id']);
$code = $verif['code'];
$expireAtTimestamp = strtotime($verif['expire_at']); // pour le compte à rebours JS

// Numéro WhatsApp business à afficher : mettre le vrai numéro dans .env
$numeroBusinessAffiche = $_ENV['WHATSAPP_BUSINESS_DISPLAY_NUMBER'] ?? '+221 77 876 48 19';
$numeroBusinessWaMe = preg_replace('/\D/', '', $numeroBusinessAffiche); // format wa.me : chiffres seuls

$sans_numero = !etatVerification($pdo, $_SESSION['user_id'])['a_telephone'];
$lienWaMe = 'https://wa.me/' . $numeroBusinessWaMe . '?text=' . urlencode($code);
?>
<section class="mr-verif-banner alerte alerte-attention flex-col gap-3" aria-labelledby="mr-verif-titre"
         data-numero="<?= e($numeroBusinessWaMe) ?>" data-expire="<?= (int) ($expireAtTimestamp * 1000) ?>">
  <div class="flex items-start gap-3">
    <?= ico('lock', 'mt-0.5') ?>
    <div class="flex flex-col gap-1">
      <h2 id="mr-verif-titre" class="text-sm font-semibold text-text"><?= $sans_numero ? 'Ajoutez et vérifiez votre numéro WhatsApp' : 'Vérifiez votre numéro WhatsApp' ?></h2>
      <p class="text-sm text-text-2"><?= $sans_numero ? 'Votre compte n\'a pas encore de numéro : les prix et les commissions sont masqués et toutes les actions sont bloquées. Envoyez ce code depuis le numéro WhatsApp que vous voulez utiliser, il sera enregistré sur votre compte.' : 'Tant que votre numéro n\'est pas vérifié, les prix et les liens d\'affiliation restent masqués (la commission reste visible) et les actions sont bloquées. Envoyez ce code par WhatsApp.' ?> Numéro à contacter : <strong class="whitespace-nowrap font-medium text-text"><?= e($numeroBusinessAffiche) ?></strong>.</p>
    </div>
  </div>
  <div class="flex flex-wrap items-center gap-2 sm:pl-8">
    <span id="mr-code-verif" class="rounded border border-line-strong bg-surface px-3 py-2 font-mono text-lg tracking-[.2em] text-text"><?= e($code) ?></span>
    <button type="button" id="mr-btn-copier" class="btn btn-sm btn-secondaire"><?= ico('copy', 'ico-16') ?><span data-libelle>Copier</span></button>
    <a id="mr-lien-wame" href="<?= e($lienWaMe) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primaire"><?= ico('whatsapp', 'ico-16') ?>Envoyer sur WhatsApp</a>
    <button type="button" id="mr-btn-regenerer" class="btn btn-sm btn-discret"><?= ico('refresh-cw', 'ico-16') ?><span data-libelle>Nouveau code</span></button>
  </div>
  <p class="text-xs text-text-2 sm:pl-8">Le code expire dans <strong class="chiffres font-medium text-text" id="mr-compte-a-rebours"><?= WHATSAPP_VERIF_DUREE_MINUTES ?>:00</strong>. Votre compte est débloqué dès la réception du message.</p>
</section>
<script src="<?= e(actif('/assets/js/verif-whatsapp.js')) ?>"></script>
