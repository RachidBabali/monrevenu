<?php
/**
 * tools/generer-miniatures-webp.php : re-encode en WebP les images de produits envoyees avant
 * ce correctif (JPEG/PNG bruts, sans redimensionnement ni retrait des metadonnees, voir
 * dev/lot3/RESULTATS_I.md > I4).
 *
 * Usage (en ligne de commande seulement) :
 *   php tools/generer-miniatures-webp.php               simulation : liste, ne modifie rien
 *   php tools/generer-miniatures-webp.php --appliquer   convertit, remplace l'image, journalise
 *   Options : --limite=100 (nombre de lignes par table)
 *
 * Pour chaque ligne dont l'image n'est pas deja en .webp ni une donnee "data:" (icone par
 * defaut) : telecharge l'image actuelle (fichier local en developpement, ou URL R2 publique en
 * production), la fait passer par la meme fonction de controle et de re-encodage que les envois
 * (includs/image_produit.php > reencoderImageEnWebp), envoie le resultat a cote de l'original
 * sous un nouveau nom, met a jour la colonne image, puis supprime l'ancien fichier. Une image
 * deja trop degradee pour etre relue (fichier manquant, format non reconnu) est signalee et
 * laissee inchangee : le script ne supprime jamais une ligne ni une image sans remplacement pret.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$racine = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $racine;
require_once $racine . '/basse_de_donner/monrevenu_bd.php';
require_once $racine . '/includs/audit.php';
require_once $racine . '/includs/env_loader.php';
require_once $racine . '/includs/r2_uploader.php';
require_once $racine . '/includs/image_produit.php';

$appliquer = in_array('--appliquer', $argv, true);
$limite = 100;
foreach ($argv as $a) {
    if (str_starts_with($a, '--limite=')) $limite = max(1, (int) substr($a, 9));
}

/** Chemin ou URL de l'image -> fichier temporaire local, ou null si introuvable. */
function telechargerImage(string $image): ?string
{
    global $racine;
    if (str_starts_with($image, 'uploads/')) {
        $chemin = $racine . '/admin/' . $image;
        return is_file($chemin) ? $chemin : null;
    }
    if (!preg_match('#^https?://#', $image)) return null;
    $ch = curl_init($image);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true]);
    $donnees = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($donnees === false || $code !== 200) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'mrsrc');
    file_put_contents($tmp, $donnees);
    return $tmp;
}

/** Remplace l'image d'une ligne : nouveau fichier envoye, ancien retire, colonne mise a jour. */
function remplacerImage(PDO $pdo, string $table, string $colonneId, int $id, string $ancienneImage, string $prefixeCle, int $dossierId, array $reencodage): bool
{
    global $racine;
    $cle = $prefixeCle . '/' . $dossierId . '/' . bin2hex(random_bytes(12)) . '.' . $reencodage['ext'];
    $type = $reencodage['ext'] === 'webp' ? 'image/webp' : 'image/jpeg';

    $r2Configure = env('R2_ACCOUNT_ID') && env('R2_ACCESS_KEY_ID') && env('R2_SECRET_ACCESS_KEY') && env('R2_BUCKET_NAME') && env('R2_PUBLIC_URL');
    if (!$r2Configure && env('APP_ENV') === 'local') {
        $dossier = $racine . '/admin/uploads/' . dirname($cle);
        if (!is_dir($dossier)) mkdir($dossier, 0755, true);
        if (!rename($reencodage['tmp'], $racine . '/admin/uploads/' . $cle)) return false;
        $nouvelleImage = 'uploads/' . $cle;
    } else {
        $resultat = uploaderVersR2($reencodage['tmp'], $cle, $type);
        @unlink($reencodage['tmp']);
        if (!$resultat['ok']) { fwrite(STDERR, "  envoi echoue : " . ($resultat['error'] ?? '?') . "\n"); return false; }
        $nouvelleImage = $resultat['url'];
    }

    $pdo->prepare("UPDATE {$table} SET image = ? WHERE {$colonneId} = ?")->execute([$nouvelleImage, $id]);
    auditInfo($pdo, ['category' => 'systeme', 'action' => 'image_conversion_webp', 'entity_type' => $table, 'entity_id' => $id,
        'before' => ['image' => $ancienneImage], 'after' => ['image' => $nouvelleImage]]);

    // Ancien fichier retire seulement apres l'ecriture reussie en base (jamais avant).
    if (str_starts_with($ancienneImage, 'uploads/')) {
        @unlink($racine . '/admin/' . $ancienneImage);
    } else {
        $public = rtrim((string) env('R2_PUBLIC_URL'), '/');
        if ($public !== '' && str_starts_with($ancienneImage, $public . '/')) supprimerDeR2($ancienneImage);
    }
    return true;
}

$cibles = [
    // [table, colonne id, colonne image, dossier R2, colonne dossier (vendeur_id) ou null]
    ['vendeur_produits', 'id', 'image', 'produits', null],
    ['produits_stock', 'id', 'image', 'produits-stock', null],
];

$totalVues = 0; $totalConverties = 0; $totalEchecs = 0;

foreach ($cibles as [$table, $colId, $colImage, $prefixeCle, $colDossier]) {
    $st = $pdo->prepare(
        "SELECT {$colId} AS id, {$colImage} AS image" . ($colDossier ? ", {$colDossier} AS dossier" : "") . "
         FROM {$table}
         WHERE {$colImage} <> '' AND {$colImage} NOT LIKE 'data:%' AND {$colImage} NOT LIKE '%.webp'
         ORDER BY {$colId} DESC LIMIT {$limite}"
    );
    $st->execute();
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);

    echo "=== {$table} : " . count($lignes) . " image(s) a convertir (limite {$limite}) ===\n";

    foreach ($lignes as $ligne) {
        $totalVues++;
        $id = (int) $ligne['id'];
        $image = (string) $ligne['image'];
        $dossierId = $colDossier ? (int) $ligne['dossier'] : 0;

        $fichierSource = telechargerImage($image);
        if ($fichierSource === null) {
            echo "  #{$id} : introuvable ({$image}), ignore\n";
            $totalEchecs++;
            continue;
        }

        $reencodage = reencoderImageEnWebp($fichierSource);
        if (str_starts_with($image, 'http')) @unlink($fichierSource); // fichier local jamais supprime, telecharge seulement

        if (!$reencodage['ok']) {
            echo "  #{$id} : {$reencodage['erreur']} ({$image}), ignore\n";
            $totalEchecs++;
            continue;
        }

        if (!$appliquer) {
            echo "  #{$id} : {$image} -> WebP {$reencodage['largeur']}x{$reencodage['hauteur']}, {$reencodage['octets']} octets (simulation)\n";
            @unlink($reencodage['tmp']);
            $totalConverties++;
            continue;
        }

        if (remplacerImage($pdo, $table, $colId, $id, $image, $prefixeCle, $dossierId, $reencodage)) {
            echo "  #{$id} : converti ({$reencodage['octets']} octets)\n";
            $totalConverties++;
        } else {
            $totalEchecs++;
        }
    }
}

echo "\n" . ($appliquer ? "Applique" : "Simulation") . " : {$totalVues} vue(s), {$totalConverties} converti(s), {$totalEchecs} echec(s)/ignore(s).\n";
if (!$appliquer) echo "Relancer avec --appliquer pour ecrire.\n";
