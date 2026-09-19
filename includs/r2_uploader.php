<?php
/**
 * r2_uploader.php, Upload de fichiers vers Cloudflare R2 (compatible S3)
 * À placer dans : includs/r2_uploader.php
 *
 * Aucune dépendance Composer : implémente la signature AWS Signature V4
 * "à la main" via cURL. Nécessite les variables d'environnement suivantes
 * (voir .env) : R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY,
 * R2_BUCKET_NAME, R2_PUBLIC_URL.
 *
 * Usage :
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/env_loader.php';
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/r2_uploader.php';
 *
 *   $resultat = uploaderVersR2($fichier['tmp_name'], 'produits/' . $nomFichier, $mimeReel);
 *   if ($resultat['ok']) {
 *       $image = $resultat['url']; // URL publique complète à stocker en base
 *   } else {
 *       $error = $resultat['error'];
 *   }
 */

/**
 * Upload un fichier local vers le bucket R2 configuré.
 *
 * @param string $cheminLocal   Chemin du fichier temporaire local (ex: $_FILES[...]['tmp_name'])
 * @param string $cleDistante   Chemin/clé dans le bucket (ex: 'produits/produit_xxx.jpg')
 * @param string $contentType   Type MIME du fichier (ex: 'image/jpeg')
 * @return array{ok: bool, url?: string, error?: string}
 */
function uploaderVersR2(string $cheminLocal, string $cleDistante, string $contentType): array
{
    $accountId  = env('R2_ACCOUNT_ID');
    $accessKey  = env('R2_ACCESS_KEY_ID');
    $secretKey  = env('R2_SECRET_ACCESS_KEY');
    $bucket     = env('R2_BUCKET_NAME');
    $urlPublique = rtrim((string) env('R2_PUBLIC_URL'), '/');

    if (!$accountId || !$accessKey || !$secretKey || !$bucket || !$urlPublique) {
        return ['ok' => false, 'error' => 'Configuration R2 incomplète (variables .env manquantes).'];
    }

    if (!is_readable($cheminLocal)) {
        return ['ok' => false, 'error' => 'Fichier local introuvable ou illisible.'];
    }

    $contenu = file_get_contents($cheminLocal);
    if ($contenu === false) {
        return ['ok' => false, 'error' => 'Impossible de lire le fichier à uploader.'];
    }

    $endpointHost = "{$accountId}.r2.cloudflarestorage.com";
    $region       = 'auto';
    $service      = 's3';
    $cleDistante  = ltrim($cleDistante, '/');

    $maintenant   = new DateTime('now', new DateTimeZone('UTC'));
    $dateAmz      = $maintenant->format('Ymd\THis\Z');
    $dateCourte   = $maintenant->format('Ymd');

    $payloadHash = hash('sha256', $contenu);

    //  Requête canonique 
    $uriCanonique = '/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $cleDistante)));

    $headersCanoniques =
        "content-type:{$contentType}\n" .
        "host:{$endpointHost}\n" .
        "x-amz-content-sha256:{$payloadHash}\n" .
        "x-amz-date:{$dateAmz}\n";

    $headersSignes = 'content-type;host;x-amz-content-sha256;x-amz-date';

    $requeteCanonique = implode("\n", [
        'PUT',
        $uriCanonique,
        '', // pas de query string
        $headersCanoniques,
        $headersSignes,
        $payloadHash,
    ]);

    //  Chaîne à signer 
    $scope = "{$dateCourte}/{$region}/{$service}/aws4_request";
    $chaineASigner = implode("\n", [
        'AWS4-HMAC-SHA256',
        $dateAmz,
        $scope,
        hash('sha256', $requeteCanonique),
    ]);

    //  Clé de signature dérivée 
    $kDate    = hash_hmac('sha256', $dateCourte, 'AWS4' . $secretKey, true);
    $kRegion  = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

    $signature = hash_hmac('sha256', $chaineASigner, $kSigning);

    $autorisation = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$scope}, SignedHeaders={$headersSignes}, Signature={$signature}";

    //  Requête PUT 
    $url = "https://{$endpointHost}{$uriCanonique}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $contenu,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: {$contentType}",
            "x-amz-content-sha256: {$payloadHash}",
            "x-amz-date: {$dateAmz}",
            "Authorization: {$autorisation}",
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $reponse   = curl_exec($ch);
    $codeHttp  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreurCurl = curl_error($ch);
    curl_close($ch);

    if ($erreurCurl) {
        return ['ok' => false, 'error' => "Erreur réseau vers R2 : {$erreurCurl}"];
    }

    if ($codeHttp < 200 || $codeHttp >= 300) {
        error_log("Erreur upload R2 (HTTP {$codeHttp}) : " . substr((string) $reponse, 0, 500));
        return ['ok' => false, 'error' => "R2 a refusé l'upload (code {$codeHttp})."];
    }

    return ['ok' => true, 'url' => "{$urlPublique}/{$cleDistante}"];
}

/**
 * Requete signee (SigV4) vers R2 pour les operations autres que l'envoi : suppression d'un objet,
 * liste paginee d'un prefixe. Meme configuration que uploaderVersR2().
 *
 * @param array<string,string> $query parametres de la requete (tries et encodes pour la signature)
 * @return array{ok: bool, code: int, corps: string, error?: string, duree_ms: int}
 */
function r2Requete(string $methode, string $cleDistante = '', array $query = []): array
{
    $accountId = env('R2_ACCOUNT_ID');
    $accessKey = env('R2_ACCESS_KEY_ID');
    $secretKey = env('R2_SECRET_ACCESS_KEY');
    $bucket    = env('R2_BUCKET_NAME');
    if (!$accountId || !$accessKey || !$secretKey || !$bucket) {
        return ['ok' => false, 'code' => 0, 'corps' => '', 'error' => 'Configuration R2 incomplète.', 'duree_ms' => 0];
    }

    $hote   = "{$accountId}.r2.cloudflarestorage.com";
    $cle    = ltrim($cleDistante, '/');
    $uri    = '/' . rawurlencode($bucket) . ($cle !== '' ? '/' . implode('/', array_map('rawurlencode', explode('/', $cle))) : '');
    ksort($query);
    $qs     = implode('&', array_map(fn($k, $v) => rawurlencode($k) . '=' . rawurlencode((string) $v), array_keys($query), $query));
    $maint  = new DateTime('now', new DateTimeZone('UTC'));
    $dateAmz = $maint->format('Ymd\THis\Z');
    $jour    = $maint->format('Ymd');
    $hashVide = hash('sha256', '');

    $canonique = implode("\n", [$methode, $uri, $qs, "host:{$hote}\nx-amz-content-sha256:{$hashVide}\nx-amz-date:{$dateAmz}\n",
        'host;x-amz-content-sha256;x-amz-date', $hashVide]);
    $scope = "{$jour}/auto/s3/aws4_request";
    $aSigner = implode("\n", ['AWS4-HMAC-SHA256', $dateAmz, $scope, hash('sha256', $canonique)]);
    $k = hash_hmac('sha256', $jour, 'AWS4' . $secretKey, true);
    $k = hash_hmac('sha256', 'auto', $k, true);
    $k = hash_hmac('sha256', 's3', $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);
    $signature = hash_hmac('sha256', $aSigner, $k);

    $debut = microtime(true);
    $ch = curl_init("https://{$hote}{$uri}" . ($qs !== '' ? '?' . $qs : ''));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $methode,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "x-amz-content-sha256: {$hashVide}",
            "x-amz-date: {$dateAmz}",
            "Authorization: AWS4-HMAC-SHA256 Credential={$accessKey}/{$scope}, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature={$signature}",
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $corps = (string) curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    $duree = (int) round((microtime(true) - $debut) * 1000);
    if ($err) return ['ok' => false, 'code' => 0, 'corps' => '', 'error' => 'Erreur réseau vers R2.', 'duree_ms' => $duree];
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'corps' => $corps, 'duree_ms' => $duree];
}

/** Supprime un objet a partir de sa cle ou de son URL publique. Un objet deja absent compte comme supprime. */
function supprimerDeR2(string $cleOuUrl): array
{
    $public = rtrim((string) env('R2_PUBLIC_URL'), '/');
    $cle = ($public !== '' && str_starts_with($cleOuUrl, $public . '/')) ? substr($cleOuUrl, strlen($public) + 1) : $cleOuUrl;
    if ($cle === '' || preg_match('#^(https?:|data:)#', $cle)) return ['ok' => false, 'error' => 'Clé R2 invalide.'];
    $r = r2Requete('DELETE', $cle);
    return ['ok' => $r['ok'] || $r['code'] === 404, 'cle' => $cle, 'error' => $r['error'] ?? null];
}
