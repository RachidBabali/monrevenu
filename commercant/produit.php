<?php
/**
 * commercant/produit.php : creation et modification d'un produit de commercant.
 * Un produit approuve dont le nom, le prix ou l'image change repasse en attente de validation.
 * L'image est controlee et re-encodee par includs/image_produit.php.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/basse_de_donner/monrevenu_bd.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/audit.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/incident.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/commercant.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/image_produit.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/affiliation_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/reglages_publication.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/ui.php';

$profil = exigerCommercant($pdo);
$id = (int) $profil['user_id'];
$marche_commercant = $profil['marche'];
$cfg_commission = commissionConfigMarche($marche_commercant);
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$produit_id = (int) ($_GET['id'] ?? $_POST['produit_id'] ?? 0);
$produit = null;
if ($produit_id > 0) {
    $st = $pdo->prepare("SELECT * FROM vendeur_produits WHERE id = ? AND vendeur_id = ? LIMIT 1");
    $st->execute([$produit_id, $id]);
    $produit = $st->fetch(PDO::FETCH_ASSOC);
    if (!$produit) {
        auditInfo($pdo, ['category' => 'produit', 'action' => 'produit_acces_refuse', 'result' => 'refus',
            'entity_type' => 'produit', 'entity_id' => $produit_id]);
        $_SESSION['flash_error'] = "Ce produit est introuvable.";
        header('Location: /commercant/produits.php'); exit();
    }
}

$erreur = '';
$saisie = [
    'nom' => $produit['nom_produit'] ?? '',
    'description' => $produit['description'] ?? '',
    'prix' => $produit ? (string) (float) ($produit['prix_net'] ?? $produit['prix_vente']) : '',
    'stock' => $produit && (int) $produit['stock'] > 0 ? (string) (int) $produit['stock'] : '',
    'date_limite' => $produit['date_limite'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'supprimer') {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        auditCsrf($pdo, 'commercant_produit');
        $erreur = 'Votre session a expiré. Rechargez la page puis recommencez.';
    } else {
        $saisie = [
            'nom' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nom'] ?? ''))),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'prix' => trim((string) ($_POST['prix'] ?? '')),
            'stock' => trim((string) ($_POST['stock'] ?? '')),
            'date_limite' => trim((string) ($_POST['date_limite'] ?? '')),
        ];
        $envoyer = ($_POST['action'] ?? '') === 'envoyer';
        $prix = round((float) str_replace([' ', ','], ['', '.'], $saisie['prix']), 2); // prix net du commercant
        $prix_final = $prix >= 100 ? (float) commissionCalculer($prix, $cfg_commission)['prix_final'] : $prix;
        $stock = $saisie['stock'] === '' ? 0 : (int) $saisie['stock'];
        $dateLimite = $saisie['date_limite'] !== '' ? $saisie['date_limite'] : null;

        if (mb_strlen($saisie['nom']) < 3 || mb_strlen($saisie['nom']) > 255) {
            $erreur = 'Indiquez le nom du produit (3 à 255 caractères).';
        } elseif (mb_strlen($saisie['description']) > 2000) {
            $erreur = 'La description ne doit pas dépasser 2000 caractères.';
        } elseif ($prix < 100 || $prix > 10000000) {
            $erreur = 'Indiquez un prix net entre ' . formaterMontant(100) . ' et ' . formaterMontant(10000000) . '.';
        } elseif ($stock < 0 || $stock > 100000) {
            $erreur = 'Le stock doit être un nombre entre 0 et 100 000.';
        } elseif ($dateLimite !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateLimite) || $dateLimite < date('Y-m-d'))) {
            $erreur = 'La date limite doit être au format jour/mois/année et ne pas être passée.';
        } elseif ($envoyer && !commercantPeutPublier($profil)) {
            $erreur = 'Votre boutique doit être validée avant de publier un produit.';
        }

        // Limites de creation (nouveaux produits seulement)
        if ($erreur === '' && !$produit) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM vendeur_produits WHERE vendeur_id = ?");
            $st->execute([$id]);
            if ((int) $st->fetchColumn() >= commercantMaxProduits()) {
                $erreur = 'Vous avez atteint la limite de ' . commercantMaxProduits() . ' produits. Supprimez ou terminez un produit avant d\'en ajouter un nouveau.';
            } else {
                $st = $pdo->prepare("SELECT COUNT(*) FROM vendeur_produits WHERE vendeur_id = ? AND created_at >= CURDATE()");
                $st->execute([$id]);
                if ((int) $st->fetchColumn() >= commercantMaxProduitsJour()) {
                    $erreur = 'Vous avez ajouté ' . commercantMaxProduitsJour() . ' produits aujourd\'hui. Revenez demain pour en ajouter d\'autres.';
                }
            }
        }

        // Image : obligatoire a la premiere publication, facultative en modification
        $image = $produit['image'] ?? '';
        $ancienneImage = null;
        $imageChangee = false;
        if ($erreur === '' && isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $r = traiterImageProduit($_FILES['image'], $id);
            auditInfo($pdo, ['category' => $r['ok'] ? 'produit' : 'systeme', 'action' => $r['ok'] ? 'image_envoi' : 'image_envoi_refuse',
                'result' => $r['ok'] ? 'ok' : 'refus', 'entity_type' => 'produit', 'entity_id' => $produit_id ?: null,
                'meta' => ['octets' => $r['octets'] ?? ($_FILES['image']['size'] ?? null), 'cle' => $r['cle'] ?? null, 'motif' => $r['erreur'] ?? null]]);
            if (!$r['ok']) {
                $erreur = $r['erreur'];
            } else {
                $ancienneImage = $image !== '' ? $image : null;
                $image = $r['url'];
                $imageChangee = true;
            }
        }
        if ($erreur === '' && $image === '') {
            $erreur = 'Ajoutez une photo du produit (JPG, PNG ou WebP, 2 Mo au maximum).';
        }

        if ($erreur === '') {
            try {
                $pdo->beginTransaction();
                if ($produit) {
                    $st = $pdo->prepare("SELECT * FROM vendeur_produits WHERE id = ? AND vendeur_id = ? FOR UPDATE");
                    $st->execute([$produit_id, $id]);
                    $avant = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$avant) throw new RuntimeException('introuvable');

                    // Nom, prix ou image modifies sur un produit deja publie : nouvelle validation
                    $modificationSensible = $imageChangee
                        || $avant['nom_produit'] !== $saisie['nom']
                        || (float) ($avant['prix_net'] ?? $avant['prix_vente']) !== $prix
                        || (float) $avant['prix_vente'] !== $prix_final;
                    $moderation = $avant['moderation'];
                    $publicationType = $avant['publication_type'] ?? 'manuel';
                    $publieAutoLe = $avant['publie_automatiquement_le'] ?? null;
                    $motifModeration = null;
                    if ($envoyer || ($avant['moderation'] === 'approuve' && $modificationSensible)) {
                        $decision = evaluerPublicationProduit($pdo, $profil,
                            ['nom' => $saisie['nom'], 'description' => $saisie['description'], 'prix' => $prix, 'image' => $image],
                            $marche_commercant, $produit_id);
                        $moderation = $decision['moderation'];
                        $motifModeration = $decision['motif'];
                        $publicationType = $moderation === 'approuve' ? $decision['publication_type'] : 'manuel';
                        $publieAutoLe = ($moderation === 'approuve' && $decision['publication_type'] === 'automatique') ? date('Y-m-d H:i:s') : null;
                    }
                    $statut = $envoyer && $avant['statut'] !== 'actif' ? 'actif' : $avant['statut'];

                    $pdo->prepare(
                        "UPDATE vendeur_produits SET nom_produit = ?, description = ?, image = ?, prix_net = ?, prix_vente = ?, stock = ?,
                            date_limite = ?, statut = ?, moderation = ?, moderation_note = ?, publication_type = ?, publie_automatiquement_le = ?
                         WHERE id = ? AND vendeur_id = ?"
                    )->execute([$saisie['nom'], $saisie['description'], $image, $prix, $prix_final, $stock, $dateLimite, $statut, $moderation,
                        $motifModeration, $publicationType, $publieAutoLe, $produit_id, $id]);

                    [$b, $a] = auditDiff(
                        ['nom_produit' => $avant['nom_produit'], 'prix_vente' => $avant['prix_vente'], 'stock' => $avant['stock'],
                         'date_limite' => $avant['date_limite'], 'statut' => $avant['statut'], 'moderation' => $avant['moderation'], 'image' => $avant['image']],
                        ['nom_produit' => $saisie['nom'], 'prix_vente' => number_format($prix_final, 2, '.', ''), 'stock' => $stock,
                         'date_limite' => $dateLimite, 'statut' => $statut, 'moderation' => $moderation, 'image' => $image]
                    );
                    auditCritique($pdo, ['category' => 'produit', 'action' => 'produit_modification', 'entity_type' => 'produit',
                        'entity_id' => $produit_id, 'before' => $b, 'after' => $a,
                        'meta' => ['nouvelle_validation' => $moderation === 'en_attente', 'motif' => $motifModeration]]);
                    $_SESSION['flash_success'] = $moderation === 'en_attente'
                        ? 'Produit enregistré et envoyé pour validation.' . ($motifModeration ? ' Motif : ' . $motifModeration : '')
                        : ($moderation === 'approuve' ? 'Produit enregistré et publié.' : 'Brouillon enregistré.');
                } else {
                    $motifModeration = null;
                    $publicationType = 'manuel';
                    $publieAutoLe = null;
                    if ($envoyer) {
                        $decision = evaluerPublicationProduit($pdo, $profil,
                            ['nom' => $saisie['nom'], 'description' => $saisie['description'], 'prix' => $prix, 'image' => $image],
                            $marche_commercant);
                        $moderation = $decision['moderation'];
                        $motifModeration = $decision['motif'];
                        $publicationType = $decision['publication_type'];
                        $publieAutoLe = ($moderation === 'approuve' && $publicationType === 'automatique') ? date('Y-m-d H:i:s') : null;
                    } else {
                        $moderation = 'brouillon';
                    }
                    $pdo->prepare(
                        "INSERT INTO vendeur_produits (vendeur_id, nom_produit, description, image, prix_net, prix_vente, commission_pct, stock, date_limite, statut, moderation, moderation_note, publication_type, publie_automatiquement_le, created_by, devise)
                         VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 'actif', ?, ?, ?, ?, ?, ?)"
                    )->execute([$id, $saisie['nom'], $saisie['description'], $image, $prix, $prix_final, $stock, $dateLimite, $moderation,
                        $motifModeration, $publicationType, $publieAutoLe, $id, deviseIso($marche_commercant)]);
                    $produit_id = (int) $pdo->lastInsertId();
                    auditCritique($pdo, ['category' => 'produit', 'action' => 'produit_creation', 'entity_type' => 'produit', 'entity_id' => $produit_id,
                        'after' => ['nom_produit' => $saisie['nom'], 'prix_vente' => number_format($prix_final, 2, '.', ''), 'stock' => $stock,
                            'moderation' => $moderation, 'image' => $image], 'meta' => ['motif' => $motifModeration, 'publication_type' => $publicationType]]);
                    $_SESSION['flash_success'] = $moderation === 'brouillon'
                        ? 'Brouillon enregistré. Envoyez-le pour validation quand il est prêt.'
                        : ($moderation === 'approuve' ? 'Produit publié dans le catalogue.' : 'Produit envoyé pour validation.' . ($motifModeration ? ' Motif : ' . $motifModeration : ''));
                }
                $pdo->commit();

                if ($ancienneImage) {
                    $r = supprimerImageProduit($ancienneImage, $id);
                    auditInfo($pdo, ['category' => $r['ok'] ? 'produit' : 'systeme', 'action' => $r['ok'] ? 'image_suppression' : 'image_suppression_echec',
                        'result' => $r['ok'] ? 'ok' : 'echec', 'entity_type' => 'produit', 'entity_id' => $produit_id, 'meta' => ['cle' => $r['cle'] ?? null]]);
                }
                header('Location: /commercant/produits.php'); exit();
            } catch (Throwable $t) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erreur = messageIncident(incidentEnregistrer($pdo, $t, 'commercant/produit'),
                    "Le produit n'a pas pu être enregistré. Réessayez dans un instant.");
            }
        }
    }
}

// La suppression est traitee par commercant/produits.php (meme controle de propriete)
$apercu = $saisie['prix'] !== '' ? commissionCalculer((float) str_replace([' ', ','], ['', '.'], $saisie['prix']), $cfg_commission) : null;
$imageActuelle = (string) ($produit['image'] ?? '');
$srcActuelle = $imageActuelle === '' ? '' : (preg_match('#^(https?:)?//#', $imageActuelle) || str_starts_with($imageActuelle, 'data:') ? $imageActuelle : '/admin/' . ltrim($imageActuelle, '/'));
$aDesCommandes = false;
if ($produit) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM vendeur_ventes WHERE produit_id = ?");
    $st->execute([$produit_id]);
    $aDesCommandes = (int) $st->fetchColumn() > 0;
}

$message_error = $erreur;
$titre_page = $produit ? 'Modifier le produit' : 'Nouveau produit';
$scripts_page = ['/assets/js/commercant.js'];
include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_debut.php';
?>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="page-titre"><?= $produit ? 'Modifier le produit' : 'Nouveau produit' ?></h2>
      <a class="lien cible text-sm" href="/commercant/produits.php"><?= ico('arrow-left', 'ico-16') ?>Retour à mes produits</a>
    </div>

    <form method="POST" action="/commercant/produit.php<?= $produit ? '?id=' . (int) $produit_id : '' ?>" enctype="multipart/form-data"
          class="carte flex flex-col gap-4 p-4" id="form-produit" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <?php if ($produit): ?><input type="hidden" name="produit_id" value="<?= (int) $produit_id ?>"><?php endif; ?>

      <div class="champ">
        <label class="champ-label" for="nom">Nom du produit</label>
        <input class="champ-saisie" type="text" id="nom" name="nom" maxlength="255" required value="<?= e($saisie['nom']) ?>">
        <p class="champ-aide">Ce nom apparaît dans le catalogue et sur la page partagée par les affiliés.</p>
      </div>

      <div class="champ">
        <label class="champ-label" for="description">Description</label>
        <textarea class="champ-saisie" id="description" name="description" rows="4" maxlength="2000"><?= e($saisie['description']) ?></textarea>
        <p class="champ-aide">Matière, taille, couleur, délai de livraison : ce qui évite un appel au client.</p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="champ">
          <label class="champ-label" for="prix">Prix net (ce que vous recevez)</label>
          <div class="champ-groupe">
            <input class="champ-saisie chiffres" type="number" id="prix" name="prix" min="100" step="1" inputmode="numeric" required
                   value="<?= e($saisie['prix']) ?>" data-marche="<?= e($marche_commercant) ?>" data-devise="<?= e(deviseLibelle($marche_commercant)) ?>">
            <span class="champ-prefixe rounded-l-none border-l-0 border-r"><?= e(deviseLibelle($marche_commercant)) ?></span>
          </div>
          <p class="champ-aide" id="aide-prix-final">Prix affiché au client : <strong id="prix-final-calcule"><?= $apercu !== null ? e(formaterMontant($apercu['prix_final'], false, true, $marche_commercant)) : '—' ?></strong> (votre prix net + le supplément MonRevenu, qui rémunère la vente).</p>
        </div>
        <div class="champ">
          <label class="champ-label" for="stock">Stock disponible <span class="font-normal text-text-3">(facultatif)</span></label>
          <input class="champ-saisie chiffres" type="number" id="stock" name="stock" min="0" step="1" inputmode="numeric" value="<?= e($saisie['stock']) ?>">
          <p class="champ-aide">Pour votre suivi. Laissez vide si vous ne comptez pas votre stock.</p>
        </div>
      </div>

      <div class="champ">
        <label class="champ-label" for="date_limite">Date limite de vente <span class="font-normal text-text-3">(facultatif)</span></label>
        <input class="champ-saisie" type="date" id="date_limite" name="date_limite" min="<?= e(date('Y-m-d')) ?>" value="<?= e($saisie['date_limite'] ?? '') ?>">
      </div>

      <div class="champ">
        <label class="champ-label" for="image">Photo du produit</label>
        <?php if ($srcActuelle !== ''): ?>
          <div class="mb-2 flex items-center gap-3">
            <img src="<?= e($srcActuelle) ?>" alt="Photo actuelle du produit" width="80" height="80" class="h-20 w-20 rounded bg-surface-2 object-contain">
            <p class="text-sm text-text-2">Photo actuelle. Choisissez un fichier pour la remplacer.</p>
          </div>
        <?php endif; ?>
        <input class="champ-saisie h-auto py-2" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"<?= $srcActuelle === '' ? ' required' : '' ?>>
        <p class="champ-aide">JPG, PNG ou WebP, 2 Mo au maximum. La photo est redimensionnée à 1200 pixels et ses données de prise de vue sont retirées.</p>
      </div>

      <div class="flex flex-col gap-2 border-t border-line pt-4 sm:flex-row sm:justify-end">
        <button type="submit" name="action" value="brouillon" class="btn btn-secondaire"><?= ico('file-text') ?>Enregistrer le brouillon</button>
        <?php if (commercantPeutPublier($profil)): ?>
          <button type="submit" name="action" value="envoyer" class="btn btn-primaire"><?= ico('upload') ?><?= (int) $profil['confiance'] === 1 ? 'Publier' : 'Envoyer pour validation' ?></button>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($produit): ?>
      <section class="carte flex flex-col gap-3 p-4" id="supprimer" aria-labelledby="t-supprimer">
        <h2 id="t-supprimer" class="section-titre">Supprimer ce produit</h2>
        <?php if ($aDesCommandes): ?>
          <p class="text-sm text-text-2">Ce produit a déjà des commandes : son historique doit être conservé. Vous pouvez le suspendre ou le marquer comme terminé depuis la liste de vos produits.</p>
        <?php else: ?>
          <p class="text-sm text-text-2">La suppression est définitive : la fiche et sa photo sont effacées.</p>
          <form method="POST" action="/commercant/produits.php" class="self-start">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="produit_id" value="<?= (int) $produit_id ?>">
            <input type="hidden" name="action" value="supprimer">
            <button type="submit" class="btn btn-danger"><?= ico('x') ?>Supprimer définitivement</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includs/layout_app_fin.php'; ?>
