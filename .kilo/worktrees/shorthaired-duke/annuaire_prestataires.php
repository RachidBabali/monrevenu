<?php
session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');

// require_once '../basse_de_donner/monrevenu_bd.php';
// $prestataires = $pdo->query("SELECT * FROM prestataires WHERE statut='actif' ORDER BY created_at DESC")->fetchAll();

$prestataires = [
  ['id'=>1,'nom'=>'Ahmed Abdallah',  'metier'=>'Développeur Fullstack','cat'=>'tech',     'emoji'=>'👨‍💻','note'=>4.9,'avis'=>24,'tarif'=>'25 €/h',         'dispo'=>true, 'tel'=>'2693330001','ville'=>'Moroni'],
  ['id'=>2,'nom'=>'Moussa Ibrahim',  'metier'=>'Électricien Bâtiment', 'cat'=>'maison',  'emoji'=>'⚡', 'note'=>4.7,'avis'=>12,'tarif'=>'Sur devis',       'dispo'=>false,'tel'=>'2693330002','ville'=>'Mutsamudu'],
  ['id'=>3,'nom'=>'Fatima Said',     'metier'=>'Couturière',            'cat'=>'artisan', 'emoji'=>'🪡', 'note'=>5.0,'avis'=>8, 'tarif'=>'3 000 KMF/pièce','dispo'=>true, 'tel'=>'2693330003','ville'=>'Moroni'],
  ['id'=>4,'nom'=>'Youssouf Ali',    'metier'=>'Community Manager',     'cat'=>'tech',    'emoji'=>'📱', 'note'=>4.8,'avis'=>17,'tarif'=>'15 €/h',          'dispo'=>true, 'tel'=>'2693330004','ville'=>'Fomboni'],
  ['id'=>5,'nom'=>'Raïna Combo',     'metier'=>'Prof de maths',         'cat'=>'education','emoji'=>'📐','note'=>4.9,'avis'=>31,'tarif'=>'2 000 KMF/h',    'dispo'=>true, 'tel'=>'2693330005','ville'=>'Moroni'],
  ['id'=>6,'nom'=>'Said Mohamed',    'metier'=>'Mécanicien auto',       'cat'=>'auto',    'emoji'=>'🔧', 'note'=>4.6,'avis'=>19,'tarif'=>'Sur devis',       'dispo'=>true, 'tel'=>'2693330006','ville'=>'Mutsamudu'],
  ['id'=>7,'nom'=>'Houmadi Bacar',   'metier'=>'Maçon / Carreleur',     'cat'=>'maison',  'emoji'=>'🧱', 'note'=>4.5,'avis'=>9, 'tarif'=>'5 000 KMF/j',    'dispo'=>false,'tel'=>'2693330007','ville'=>'Domoni'],
  ['id'=>8,'nom'=>'Zainaba Ali',     'metier'=>'Coiffeuse',             'cat'=>'beaute',  'emoji'=>'✂️', 'note'=>4.8,'avis'=>22,'tarif'=>'1 500 KMF',      'dispo'=>true, 'tel'=>'2693330008','ville'=>'Moroni'],
  ['id'=>9,'nom'=>'Ibrahim Soilihi', 'metier'=>'Photographe',           'cat'=>'tech',    'emoji'=>'📸', 'note'=>4.7,'avis'=>14,'tarif'=>'Sur devis',       'dispo'=>true, 'tel'=>'2693330009','ville'=>'Moroni'],
  ['id'=>10,'nom'=>'Mariama Hassane','metier'=>'Aide ménagère',          'cat'=>'service', 'emoji'=>'🧹', 'note'=>4.9,'avis'=>28,'tarif'=>'2 500 KMF/j',   'dispo'=>true, 'tel'=>'2693330010','ville'=>'Moroni'],
  ['id'=>11,'nom'=>'Ali Mze',        'metier'=>'Plombier',               'cat'=>'maison',  'emoji'=>'🔩', 'note'=>4.4,'avis'=>7, 'tarif'=>'Sur devis',      'dispo'=>false,'tel'=>'2693330011','ville'=>'Fomboni'],
  ['id'=>12,'nom'=>'Noura Said',     'metier'=>'Baby-sitter',            'cat'=>'service', 'emoji'=>'👶', 'note'=>5.0,'avis'=>11,'tarif'=>'1 000 KMF/h',   'dispo'=>true, 'tel'=>'2693330012','ville'=>'Moroni'],
];

$categories = [
  'all'      => ['label'=>'Tous',        'icon'=>'ti-layout-grid'],
  'tech'     => ['label'=>'Numérique',   'icon'=>'ti-device-desktop'],
  'maison'   => ['label'=>'Maison',      'icon'=>'ti-home-2'],
  'artisan'  => ['label'=>'Artisanat',   'icon'=>'ti-needle-thread'],
  'education'=> ['label'=>'Éducation',   'icon'=>'ti-book'],
  'auto'     => ['label'=>'Auto/Moto',   'icon'=>'ti-car'],
  'beaute'   => ['label'=>'Beauté',      'icon'=>'ti-sparkles'],
  'service'  => ['label'=>'Services',    'icon'=>'ti-heart-handshake'],
];

// Traitement commande
$cmd_ok = false; $cmd_err = ''; $wa_url = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='send_order') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']??'')) {
    $cmd_err = 'Session expirée.';
  } else {
    $prest_id   = (int)($_POST['prest_id']??0);
    $client_nom = htmlspecialchars(trim($_POST['client_nom']??''), ENT_QUOTES, 'UTF-8');
    $client_tel = htmlspecialchars(trim($_POST['client_tel']??''), ENT_QUOTES, 'UTF-8');
    $client_msg = htmlspecialchars(trim($_POST['client_msg']??''), ENT_QUOTES, 'UTF-8');
    if (!$prest_id || !$client_nom || !$client_tel || !$client_msg) {
      $cmd_err = 'Tous les champs sont requis.';
    } else {
      // INSERT INTO commandes ...
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      $csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
      $cmd_ok = true;
      $prest = null;
      foreach ($prestataires as $p) { if ($p['id']===$prest_id) { $prest=$p; break; } }
      if ($prest) {
        $wa_msg = "Bonjour {$prest['nom']}, je suis {$client_nom}. {$client_msg}";
        $wa_url = "https://wa.me/{$prest['tel']}?text=".rawurlencode($wa_msg);
      }
    }
  }
  $_SESSION['cmd_feedback'] = ['ok'=>$cmd_ok,'err'=>$cmd_err,'wa'=>$wa_url,'prest_id'=>$prest_id??0];
  header("Location: ".$_SERVER['PHP_SELF']."?ordered=1");
  exit();
}

$fb = null;
if (isset($_SESSION['cmd_feedback'])) {
  $fb = $_SESSION['cmd_feedback'];
  unset($_SESSION['cmd_feedback']);
}
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#0b1e3d;--blue:#1d60e0;--blue-d:#1549c4;
  --blue-soft:#ddeaff;--blue-ghost:#f0f6ff;
  --white:#fff;--g50:#f8fafc;--g100:#f0f4f8;--g200:#e2e8f0;
  --g300:#cbd5e1;--g400:#94a3b8;--g500:#64748b;--g600:#475569;
  --border:#e8edf5;--success:#059669;--danger:#dc2626;
  --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
  --sh-sm:0 1px 4px rgba(29,96,224,.07),0 1px 2px rgba(0,0,0,.04);
  --sh-md:0 4px 18px rgba(29,96,224,.10),0 2px 6px rgba(0,0,0,.05);
  --sh-lg:0 14px 44px rgba(29,96,224,.15),0 4px 14px rgba(0,0,0,.07);
}
html{scroll-behavior:smooth}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--g50);color:var(--navy);overflow-x:hidden;min-height:100vh}
body::before{content:'';position:fixed;top:-180px;right:-180px;width:580px;height:580px;border-radius:50%;background:radial-gradient(circle,rgba(29,96,224,.07) 0%,transparent 70%);pointer-events:none;z-index:0}

.page{max-width:1200px;margin:0 auto;padding:88px 16px 64px;position:relative;z-index:1}

/* HERO */
.hero{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:32px;flex-wrap:wrap}
.badge-pill{display:inline-flex;align-items:center;gap:6px;background:var(--blue-ghost);border:1px solid rgba(29,96,224,.18);border-radius:50px;padding:5px 14px;font-family:'DM Mono',monospace;font-size:10px;font-weight:500;letter-spacing:.12em;text-transform:uppercase;color:var(--blue);margin-bottom:12px}
.page-title{font-family:'Sora',sans-serif;font-size:clamp(22px,4vw,32px);font-weight:800;color:var(--navy);letter-spacing:-.5px;line-height:1.2;margin-bottom:6px}
.page-title span{color:var(--blue)}
.page-sub{color:var(--g500);font-size:14px;line-height:1.5;max-width:460px}

.btn-proposer{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:var(--r-md);background:var(--blue);color:var(--white);font-family:'Sora',sans-serif;font-size:13px;font-weight:700;border:none;cursor:pointer;box-shadow:0 6px 20px rgba(29,96,224,.28);transition:all .2s;white-space:nowrap;flex-shrink:0;align-self:flex-start;text-decoration:none}
.btn-proposer:hover{background:var(--blue-d);transform:translateY(-2px);box-shadow:0 10px 28px rgba(29,96,224,.38)}
.btn-proposer i{font-size:15px}

/* STATS */
.stats-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}
.stat-pill{background:var(--white);border:1px solid var(--border);border-radius:50px;padding:6px 14px;font-size:12px;font-weight:600;color:var(--g600);display:flex;align-items:center;gap:5px}
.stat-pill strong{color:var(--navy);font-weight:800}
.stat-pill i{font-size:14px;color:var(--blue)}

/* FILTERS */
.filters{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:22px}
.filter-btn{display:flex;align-items:center;gap:5px;padding:7px 14px;border-radius:50px;background:var(--white);border:1.5px solid var(--border);color:var(--g500);font-size:12px;font-weight:700;cursor:pointer;transition:all .16s;white-space:nowrap}
.filter-btn:hover{border-color:rgba(29,96,224,.3);color:var(--blue);background:var(--blue-ghost)}
.filter-btn.active{background:var(--blue);border-color:var(--blue);color:var(--white);box-shadow:0 4px 14px rgba(29,96,224,.25)}
.filter-btn i{font-size:13px}

/* SEARCH */
.search-wrap{position:relative;margin-bottom:24px;max-width:400px}
.search-input{width:100%;padding:10px 15px 10px 40px;border:1.5px solid var(--border);border-radius:50px;background:var(--white);color:var(--navy);font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;outline:none;transition:all .18s}
.search-input:focus{border-color:rgba(29,96,224,.4);box-shadow:0 0 0 3px rgba(29,96,224,.08)}
.search-input::placeholder{color:var(--g400)}
.search-ico{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--g400);font-size:15px;pointer-events:none}

/* GRID */
.grid-prest{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}
@media(max-width:480px){.grid-prest{grid-template-columns:repeat(2,1fr);gap:9px}}

/* CARD */
.prest-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);overflow:hidden;display:flex;flex-direction:column;transition:transform .22s,box-shadow .22s,border-color .22s}
.prest-card:hover{transform:translateY(-5px);border-color:rgba(29,96,224,.25);box-shadow:var(--sh-lg)}

.card-top{position:relative;padding:18px 14px 14px;background:linear-gradient(145deg,var(--blue-ghost) 0%,var(--white) 100%);border-bottom:1px solid var(--border);display:flex;flex-direction:column;align-items:center;text-align:center}
.card-emoji{font-size:40px;margin-bottom:8px;line-height:1}
.dispo-badge{position:absolute;top:9px;right:9px;padding:3px 8px;border-radius:50px;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.dispo-badge.on{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.dispo-badge.off{background:var(--g100);color:var(--g400);border:1px solid var(--g200)}
.card-metier{font-family:'DM Mono',monospace;font-size:9px;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--blue);margin-bottom:3px}
.card-nom{font-family:'Sora',sans-serif;font-size:13px;font-weight:800;color:var(--navy);line-height:1.2}
.card-ville{font-size:11px;color:var(--g400);margin-top:2px}
.stars{display:flex;align-items:center;gap:3px;margin-top:5px;justify-content:center}
.stars span{color:#f59e0b;font-size:12px}
.stars-val{font-size:11px;font-weight:700;color:var(--navy)}
.stars-count{font-size:10px;color:var(--g400)}

/* CARD BOTTOM & BUTTONS */
.card-bottom{padding:11px 13px;display:flex;align-items:center;justify-content:space-between;gap:7px;margin-top:auto}
.card-tarif{font-family:'Sora',sans-serif;font-size:12px;font-weight:800;color:var(--navy);white-space:nowrap}
.btn-commander{padding:7px 13px;border-radius:var(--r-sm);background:var(--blue);color:var(--white);font-size:11px;font-weight:700;cursor:pointer;border:none;transition:all .16s;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;display:flex;align-items:center;gap:4px}
.btn-commander:hover{background:var(--blue-d);transform:scale(1.04)}

/* MODAL & SUCCESS */
.modal-bg{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(11,30,61,0.4);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:100}
.modal-bg.open{display:flex}
.modal-box{background:var(--white);border-radius:var(--r-xl);width:100%;max-width:500px;margin:16px;box-shadow:var(--sh-lg);border:1px solid var(--border);position:relative;overflow:hidden}
.modal-head{padding:20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:between}
.modal-prest-info{display:flex;align-items:center;gap:12px}
.modal-emoji{font-size:32px}
.modal-prest-name{font-family:'Sora',sans-serif;font-size:15px;font-weight:800;color:var(--navy)}
.modal-prest-metier{font-size:11px;color:var(--blue);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-top:1px}
.modal-close{background:none;border:none;font-size:18px;color:var(--g400);cursor:pointer}
.modal-body{padding:20px}
.form-gap{display:flex;flex-direction:column;gap:14px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:400px){.field-row{grid-template-columns:1fr}}
.flabel{display:block;font-size:11px;font-weight:700;color:var(--g600);margin-bottom:5px}
.field{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:inherit;font-size:13px;outline:none;color:var(--navy)}
.field:focus{border-color:var(--blue)}
.btn-send{width:100%;padding:12px;background:var(--blue);color:var(--white);border:none;border-radius:var(--r-md);font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-send:disabled{opacity:0.6;cursor:not-allowed}
.success-overlay{padding:40px 24px;display:flex;flex-direction:column;align-items:center;text-align:center}
.success-ico{font-size:48px;margin-bottom:12px}
.success-title{font-family:'Sora',sans-serif;font-size:20px;font-weight:800;color:var(--navy);margin-bottom:6px}
.success-sub{font-size:13px;color:var(--g500);line-height:1.5;margin-bottom:20px}
.btn-wa{display:flex;align-items:center;gap:8px;padding:12px 24px;background:#25D366;color:white;border-radius:50px;text-decoration:none;font-weight:700;font-size:13px;box-shadow:0 4px 14px rgba(37,211,102,0.3)}
.btn-close-success{background:none;border:none;color:var(--g400);font-size:12px;font-weight:600;margin-top:16px;cursor:pointer;text-decoration:underline}
.fb-box{padding:10px 14px;border-radius:var(--r-sm);font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px}
.fb-box.err{background:#fee2e2;color:var(--danger)}
.empty{text-align:center;padding:64px 16px;color:var(--g400)}
.empty i{font-size:36px;margin-bottom:8px;display:block}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{animation:spin 0.8s linear infinite}
</style>

<div class="page">

  <div class="hero">
    <div>
      <span class="badge-pill">Annuaire Officiel</span>
      <h1 class="page-title">Trouvez un <span>Prestataire</span></h1>
      <p class="page-sub">Commandez des services de qualité près de chez vous en quelques clics.</p>
    </div>
    <a href="#" class="btn-proposer"><i class="ti ti-plus"></i> Devenir prestataire</a>
  </div>

  <div class="stats-row">
    <div class="stat-pill"><i class="ti ti-users"></i> <strong><?= count($prestataires) ?></strong> Actifs</div>
    <div class="stat-pill"><i class="ti ti-star"></i> <strong>4.8</strong> Note moyenne</div>
  </div>

  <div class="search-wrap">
    <i class="ti ti-search search-ico"></i>
    <input type="text" id="searchInput" class="search-input" placeholder="Rechercher un métier, un nom..." oninput="filterCards()">
  </div>

  <div class="filters">
    <?php foreach ($categories as $key => $cat): ?>
      <button class="filter-btn <?= $key === 'all' ? 'active' : '' ?>" data-cat="<?= $key ?>" onclick="setFilter(this)">
        <i class="<?= $cat['icon'] ?>"></i> <?= htmlspecialchars($cat['label']) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="grid-prest">
    <?php foreach ($prestataires as $p): ?>
    <div class="prest-card" data-cat="<?= $p['cat'] ?>" data-nom="<?= strtolower(htmlspecialchars($p['nom'], ENT_QUOTES)) ?>" data-metier="<?= strtolower(htmlspecialchars($p['metier'], ENT_QUOTES)) ?>">
      
      <div class="card-top">
        <span class="dispo-badge <?= $p['dispo'] ? 'on' : 'off' ?>">
          <?= $p['dispo'] ? 'Disponible' : 'Indisponible' ?>
        </span>
        <div class="card-emoji"><?= $p['emoji'] ?></div>
        <div class="card-metier"><?= htmlspecialchars($p['metier']) ?></div>
        <div class="card-nom"><?= htmlspecialchars($p['nom']) ?></div>
        <div class="card-ville"><i class="ti ti-map-pin"></i> <?= htmlspecialchars($p['ville']) ?></div>
        
        <div class="stars">
          <span>★</span>
          <span class="stars-val"><?= number_format($p['note'], 1) ?></span>
          <span class="stars-count">(<?= $p['avis'] ?>)</span>
        </div>
      </div>

      <div class="card-bottom">
        <span class="card-tarif"><?= htmlspecialchars($p['tarif']) ?></span>
        <button class="btn-commander"
          onclick="openOrder(<?= $p['id'] ?>,'<?= addslashes($p['nom']) ?>','<?= $p['emoji'] ?>','<?= addslashes($p['metier']) ?>','<?= $p['tel'] ?>')">
          <i class="ti ti-send"></i> Commander
        </button>
      </div>

    </div>
    <?php endforeach; ?>
  </div>

  <div class="empty" id="emptyState" style="display:none">
    <i class="ti ti-search-off"></i>
    <p style="font-size:15px;font-weight:600">Aucun prestataire trouvé</p>
    <small style="font-size:13px;margin-top:4px;display:block">Essayez un autre mot-clé</small>
  </div>

</div>

<div class="modal-bg" id="orderModal" onclick="handleBgClick(event)">
  <div class="modal-box" id="orderModalBox">

    <div id="orderForm">
      <div class="modal-head">
        <div class="modal-prest-info">
          <div class="modal-emoji" id="mEmoji"></div>
          <div>
            <div class="modal-prest-name" id="mNom"></div>
            <div class="modal-prest-metier" id="mMetier"></div>
          </div>
        </div>
        <button class="modal-close" onclick="closeOrder()"><i class="ti ti-x"></i></button>
      </div>
      <div class="modal-body">
        <p style="font-size:13px;color:var(--g500);margin-bottom:16px;line-height:1.5">
          Remplissez ce formulaire. Le prestataire recevra votre demande et vous contactera.
        </p>
        <?php if($fb && !$fb['ok']): ?>
        <div class="fb-box err" style="margin-bottom:14px">
          <i class="ti ti-alert-circle"></i> <?= htmlspecialchars($fb['err']) ?>
        </div>
        <?php endif; ?>
        <form method="POST" id="cmdForm" class="form-gap">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="send_order">
          <input type="hidden" name="prest_id" id="fPrestId">
          <div class="field-row">
            <div>
              <label class="flabel">Votre nom *</label>
              <input type="text" name="client_nom" class="field" placeholder="Ahmed Ali" required>
            </div>
            <div>
              <label class="flabel">Téléphone *</label>
              <input type="tel" name="client_tel" class="field" placeholder="+269 333 0000" required>
            </div>
          </div>
          <div>
            <label class="flabel">Votre demande *</label>
            <textarea name="client_msg" class="field" rows="4" placeholder="Décrivez ce dont vous avez besoin..." required></textarea>
          </div>
          <button type="submit" class="btn-send" id="btnSend">
            <i class="ti ti-send"></i> Envoyer la commande
          </button>
        </form>
      </div>
    </div>

    <div class="success-overlay" id="orderSuccess" style="display:<?= ($fb && $fb['ok']) ? 'flex' : 'none' ?>">
      <div class="success-ico">🎉</div>
      <div class="success-title">Commande envoyée !</div>
      <p class="success-sub">Le prestataire a reçu votre demande. Vous pouvez aussi le contacter directement sur WhatsApp.</p>
      <?php if($fb && !empty($fb['wa'])): ?>
      <a href="<?= htmlspecialchars($fb['wa']) ?>" target="_blank" class="btn-wa">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Contacter sur WhatsApp
      </a>
      <?php endif; ?>
      <button class="btn-close-success" onclick="closeOrder()">Fermer</button>
    </div>

  </div>
</div>

<?php if($fb && $fb['ok']): ?>
<script>document.getElementById('orderModal').classList.add('open');</script>
<?php endif; ?>

<script>
var currentTel = '';
var catActive  = 'all';

function openOrder(id, nom, emoji, metier, tel) {
  document.getElementById('fPrestId').value    = id;
  document.getElementById('mNom').textContent   = nom;
  document.getElementById('mEmoji').textContent = emoji;
  document.getElementById('mMetier').textContent= metier;
  currentTel = tel;
  
  // Si le feedback précédent était positif, l'overlay succès s'affiche, sinon le formulaire normal
  <?php if ($fb && $fb['ok']): ?>
    document.getElementById('orderForm').style.display    = 'none';
    document.getElementById('orderSuccess').style.display = 'flex';
  <?php else: ?>
    document.getElementById('orderForm').style.display    = 'block';
    document.getElementById('orderSuccess').style.display = 'none';
  <?php endif; ?>
  
  document.getElementById('orderModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeOrder() {
  document.getElementById('orderModal').classList.remove('open');
  document.body.style.overflow = '';
}

function handleBgClick(e) {
  if (e.target === document.getElementById('orderModal')) closeOrder();
}

document.addEventListener('keydown', function(e) { 
  if (e.key === 'Escape') closeOrder(); 
});

function setFilter(btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  catActive = btn.dataset.cat;
  filterCards();
}

function filterCards() {
  var q = document.getElementById('searchInput').value.toLowerCase().trim();
  var visible = 0;
  document.querySelectorAll('.prest-card').forEach(function(c) {
    var catOk = catActive === 'all' || c.dataset.cat === catActive;
    var qOk   = !q || c.dataset.nom.includes(q) || c.dataset.metier.includes(q);
    c.style.display = (catOk && qOk) ? '' : 'none';
    if (catOk && qOk) visible++;
  });
  document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
}

document.getElementById('cmdForm').addEventListener('submit', function() {
  var btn = document.getElementById('btnSend');
  btn.disabled = true;
  btn.innerHTML = '<svg class="spin" width="16" height="16" fill="none" viewBox="0 0 24 24"><circle opacity=".25" cx="12" cy="12" r="10" stroke="white" stroke-width="4"/><path opacity=".75" fill="white" d="M4 12a8 8 0 018-8v8z"/></svg> Envoi…';
});
</script>