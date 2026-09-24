<?php
/**
 * includs/image_produit.php : controle et re-encodage d'une image de produit envoyee par un commercant.
 *
 * 1. Type reel du contenu (finfo + getimagesize), jamais l'extension : JPEG, PNG ou WebP seulement.
 * 2. Taille maximale IMAGE_PRODUIT_MAX_OCTETS (2 Mo), dimensions entre 200 px et 8000 px, 40 megapixels au plus
 *    (protection contre les images qui explosent en memoire une fois decodees).
 * 3. Re-encodage par GD en WebP (JPEG si WebP indisponible), 1200 px au plus sur le grand cote : les metadonnees
 *    (EXIF, position GPS, commentaires) et tout contenu cache disparaissent avec le re-encodage.
 * 4. Nom aleatoire, cle R2 merchants/<id>/<aleatoire>.webp, envoi par uploaderVersR2().
 * En local sans R2 (APP_ENV=local), le fichier va dans admin/uploads/merchants/<id>/ pour les tests.
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/r2_uploader.php';

const IMAGE_PRODUIT_MAX_OCTETS = 2 * 1024 * 1024;
const IMAGE_PRODUIT_COTE_MAX = 1200;
const IMAGE_PRODUIT_COTE_MIN = 200;
const IMAGE_PRODUIT_SOURCE_MAX = 8000;
const IMAGE_PRODUIT_PIXELS_MAX = 40000000;

/**
 * Controle et re-encode un fichier image deja sur disque (JPEG/PNG/WebP, 200 a 8000 px, 40 Mpx
 * au plus) en WebP (JPEG si WebP indisponible), 1200 px au plus sur le grand cote, metadonnees
 * retirees. Commun a traiterImageProduit() (upload) et tools/generer-miniatures-webp.php
 * (images deja en ligne).
 *
 * @return array{ok: bool, tmp?: string, ext?: string, octets?: int, largeur?: int, hauteur?: int, erreur?: string}
 */
function reencoderImageEnWebp(string $cheminSource): array
{
    $err = static fn(string $m) => ['ok' => false, 'erreur' => $m];

    if (!is_file($cheminSource) || filesize($cheminSource) > IMAGE_PRODUIT_MAX_OCTETS) {
        return $err("L'image dépasse 2 Mo. Réduisez-la puis envoyez-la de nouveau.");
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($cheminSource);
    $types = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];
    if (!isset($types[$mime])) {
        return $err('Format non accepté. Envoyez une photo JPG, PNG ou WebP.');
    }
    $info = @getimagesize($cheminSource);
    if (!$info || $info[2] !== $types[$mime]) {
        return $err('Ce fichier n\'est pas une image valide. Envoyez une photo JPG, PNG ou WebP.');
    }
    [$l, $h] = $info;
    if ($l < IMAGE_PRODUIT_COTE_MIN || $h < IMAGE_PRODUIT_COTE_MIN) {
        return $err('L\'image est trop petite : 200 × 200 pixels au minimum.');
    }
    if ($l > IMAGE_PRODUIT_SOURCE_MAX || $h > IMAGE_PRODUIT_SOURCE_MAX || $l * $h > IMAGE_PRODUIT_PIXELS_MAX) {
        return $err('L\'image est trop grande : 8000 pixels de côté au maximum.');
    }

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($cheminSource),
        'image/png'  => @imagecreatefrompng($cheminSource),
        'image/webp' => @imagecreatefromwebp($cheminSource),
    };
    if (!$source) {
        return $err('Ce fichier n\'est pas une image valide. Envoyez une photo JPG, PNG ou WebP.');
    }

    // Orientation des photos de telephone : appliquee avant de perdre les metadonnees
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($cheminSource);
        $rotation = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($rotation) { $tourne = imagerotate($source, $rotation, 0); if ($tourne) { imagedestroy($source); $source = $tourne; } }
    }

    $l = imagesx($source); $h = imagesy($source);
    $ratio = min(1, IMAGE_PRODUIT_COTE_MAX / max($l, $h));
    $nl = max(1, (int) round($l * $ratio)); $nh = max(1, (int) round($h * $ratio));
    $cible = imagecreatetruecolor($nl, $nh);
    // Fond blanc sous la transparence (le WebP et le JPEG de sortie n'ont pas besoin d'alpha pour une photo produit)
    imagefill($cible, 0, 0, imagecolorallocate($cible, 255, 255, 255));
    imagecopyresampled($cible, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);
    imagedestroy($source);

    $webp = function_exists('imagewebp');
    $ext  = $webp ? 'webp' : 'jpg';
    $tmp  = tempnam(sys_get_temp_dir(), 'mrimg');
    $ok   = $webp ? imagewebp($cible, $tmp, 82) : imagejpeg($cible, $tmp, 82);
    imagedestroy($cible);
    if (!$ok) { @unlink($tmp); return $err('L\'image n\'a pas pu être traitée. Essayez une autre photo.'); }

    return ['ok' => true, 'tmp' => $tmp, 'ext' => $ext, 'octets' => filesize($tmp), 'largeur' => $nl, 'hauteur' => $nh];
}

/**
 * @param array $fichier entree de $_FILES
 * @param string $prefixeCle Dossier R2 (merchants pour un commercant, produits-admin ou
 *   produits-stock pour un produit cree par l'administration : voir admin/dashboard_admin.php).
 * @return array{ok: bool, url?: string, cle?: string, octets?: int, largeur?: int, hauteur?: int, erreur?: string}
 */
function traiterImageProduit(array $fichier, int $commercantId, string $prefixeCle = 'merchants'): array
{
    $err = static fn(string $m) => ['ok' => false, 'erreur' => $m];

    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE || ($fichier['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
        return $err("L'image dépasse 2 Mo. Réduisez-la puis envoyez-la de nouveau.");
    }
    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file_ou_test($fichier['tmp_name'] ?? '')) {
        return $err("L'image n'a pas été reçue. Réessayez.");
    }

    $reencodage = reencoderImageEnWebp($fichier['tmp_name']);
    if (!$reencodage['ok']) {
        return $err($reencodage['erreur']);
    }
    ['tmp' => $tmp, 'ext' => $ext, 'octets' => $octets, 'largeur' => $nl, 'hauteur' => $nh] = $reencodage;

    $cle = $prefixeCle . '/' . $commercantId . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    $type = $ext === 'webp' ? 'image/webp' : 'image/jpeg';

    $r2Configure = env('R2_ACCOUNT_ID') && env('R2_ACCESS_KEY_ID') && env('R2_SECRET_ACCESS_KEY') && env('R2_BUCKET_NAME') && env('R2_PUBLIC_URL');
    if (!$r2Configure && env('APP_ENV') === 'local') {
        $dossier = dirname(__DIR__) . '/admin/uploads/' . dirname($cle);
        if (!is_dir($dossier)) mkdir($dossier, 0755, true);
        rename($tmp, dirname(__DIR__) . '/admin/uploads/' . $cle);
        return ['ok' => true, 'url' => 'uploads/' . $cle, 'cle' => $cle, 'octets' => $octets, 'largeur' => $nl, 'hauteur' => $nh];
    }

    $resultat = uploaderVersR2($tmp, $cle, $type);
    @unlink($tmp);
    if (!$resultat['ok']) {
        error_log('[image_produit] envoi R2 : ' . ($resultat['error'] ?? '?'));
        return ['ok' => false, 'erreur' => 'L\'image n\'a pas pu être enregistrée. Réessayez dans un instant.', 'cle' => $cle];
    }
    return ['ok' => true, 'url' => $resultat['url'], 'cle' => $cle, 'octets' => $octets, 'largeur' => $nl, 'hauteur' => $nh];
}

/** is_uploaded_file(), sauf en ligne de commande (tests) ou le fichier est cree localement. */
function is_uploaded_file_ou_test(string $chemin): bool
{
    if ($chemin === '' || !is_file($chemin)) return false;
    return PHP_SAPI === 'cli' ? true : is_uploaded_file($chemin);
}

/** Supprime une image de commercant (R2, ou fichier local en developpement). Les autres images ne sont jamais touchees. */
function supprimerImageProduit(string $image, int $commercantId): array
{
    $prefixe = 'merchants/' . $commercantId . '/';
    if (str_starts_with($image, 'uploads/' . $prefixe)) {
        $chemin = dirname(__DIR__) . '/admin/' . $image;
        return ['ok' => !is_file($chemin) || @unlink($chemin), 'cle' => substr($image, 8)];
    }
    $public = rtrim((string) env('R2_PUBLIC_URL'), '/');
    if ($public === '' || !str_starts_with($image, $public . '/' . $prefixe)) return ['ok' => false, 'cle' => null];
    return supprimerDeR2($image);
}
