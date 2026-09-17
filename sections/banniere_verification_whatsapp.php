<?php
/**
 * À inclure en haut de dashboard.php (et idéalement partout où l'utilisateur peut
 * naviguer), UNIQUEMENT si $_SESSION['phone_verified'] != 1 (ou récupéré depuis $pdo).
 *
 * Contrairement à l'ancien flux, cette bannière NE bloque PAS l'accès au dashboard :
 * elle informe. Le blocage réel se fait sur les pages/actions d'affiliation
 * (boutique.php, mon-stock.php, validation de vente...) via une fonction dédiée,
 * ex. exigerAffiliationDebloquee(), à créer sur le même modèle que exigerTelephoneVerifie()
 * mais qui n'affecte plus dashboard.php lui-même.
 *
 * Attendu disponible dans le scope : $pdo (PDO), $_SESSION['user_id'].
 */

require_once __DIR__ . '/../includs/whatsapp_verif_helpers.php';

$verif = genererOuRecupererCodeVerificationWhatsapp($pdo, $_SESSION['user_id']);
$code = $verif['code'];
$expireAtTimestamp = strtotime($verif['expire_at']); // pour le compte à rebours JS

// Numéro WhatsApp business à afficher — mettre le vrai numéro dans .env
$numeroBusinessAffiche = $_ENV['WHATSAPP_BUSINESS_DISPLAY_NUMBER'] ?? '+269 XX XX XXX';
$numeroBusinessWaMe = preg_replace('/\D/', '', $numeroBusinessAffiche); // format wa.me : chiffres seuls

$lienWaMe = 'https://wa.me/' . $numeroBusinessWaMe . '?text=' . urlencode($code);
?>
<div class="mr-verif-banner" style="background:#fff7e6;border:1px solid #f0c36d;border-radius:10px;padding:16px 20px;margin-bottom:20px;">
  <p style="margin:0 0 8px;font-weight:600;color:#7a4b00;">
    ⚠️ Compte non vérifié — les fonctionnalités d'affiliation (boutique, ventes, stock) sont bloquées.
  </p>
  <p style="margin:0 0 10px;color:#5c3b00;">
    Envoyez ce code sur WhatsApp au <strong><?= htmlspecialchars($numeroBusinessAffiche) ?></strong> pour débloquer votre compte&nbsp;:
  </p>
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span id="mr-code-verif" style="font-size:1.4em;font-weight:700;letter-spacing:3px;background:#fff;border:1px dashed #f0c36d;border-radius:6px;padding:6px 14px;">
      <?= htmlspecialchars($code) ?>
    </span>
    <button type="button" id="mr-btn-copier" style="padding:6px 12px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer;">
      Copier
    </button>
    <a id="mr-lien-wame" href="<?= htmlspecialchars($lienWaMe) ?>" target="_blank" rel="noopener" style="padding:6px 14px;border-radius:6px;background:#25D366;color:#fff;text-decoration:none;font-weight:600;">
      Ouvrir WhatsApp
    </a>
    <button type="button" id="mr-btn-regenerer" style="padding:6px 12px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer;">
      🔄 Régénérer le code
    </button>
  </div>
  <p style="margin:10px 0 0;font-size:0.85em;color:#8a6d00;">
    Ce code expire dans <strong><span id="mr-compte-a-rebours"><?= WHATSAPP_VERIF_DUREE_MINUTES ?>:00</span></strong>.
    Vous serez notifié automatiquement dès validation.
  </p>
</div>

<script>
(function () {
  const numeroBusiness = <?= json_encode($numeroBusinessWaMe) ?>;
  let expireAtMs = <?= json_encode($expireAtTimestamp * 1000) ?>;

  const elCode = document.getElementById('mr-code-verif');
  const elCompteARebours = document.getElementById('mr-compte-a-rebours');
  const elLienWame = document.getElementById('mr-lien-wame');
  const btnCopier = document.getElementById('mr-btn-copier');
  const btnRegenerer = document.getElementById('mr-btn-regenerer');

  function majLienWame(code) {
    elLienWame.href = 'https://wa.me/' + numeroBusiness + '?text=' + encodeURIComponent(code);
  }

  function tickCompteARebours() {
    const restantSec = Math.max(0, Math.round((expireAtMs - Date.now()) / 1000));
    const m = String(Math.floor(restantSec / 60)).padStart(2, '0');
    const s = String(restantSec % 60).padStart(2, '0');
    elCompteARebours.textContent = m + ':' + s;

    if (restantSec === 0) {
      elCompteARebours.textContent = 'expiré — cliquez sur régénérer';
      clearInterval(intervalId);
    }
  }
  const intervalId = setInterval(tickCompteARebours, 1000);
  tickCompteARebours();

  btnCopier.addEventListener('click', function () {
    navigator.clipboard.writeText(elCode.textContent.trim());
    btnCopier.textContent = 'Copié !';
    setTimeout(() => { btnCopier.textContent = 'Copier'; }, 1500);
  });

  btnRegenerer.addEventListener('click', function () {
    btnRegenerer.disabled = true;
    btnRegenerer.textContent = '...';

    fetch('/includs/regenerer_code_whatsapp.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // Adapter au mécanisme CSRF existant du projet (meta csrf-token présent sur dashboard.php)
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
      }
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          elCode.textContent = data.code;
          expireAtMs = data.expire_at_ms;
          majLienWame(data.code);
          tickCompteARebours();
        } else {
          alert(data.message || "Impossible de régénérer le code pour le moment, réessayez dans quelques secondes.");
        }
      })
      .catch(() => alert("Erreur réseau, réessayez."))
      .finally(() => {
        btnRegenerer.disabled = false;
        btnRegenerer.textContent = '🔄 Régénérer le code';
      });
  });
})();
</script>