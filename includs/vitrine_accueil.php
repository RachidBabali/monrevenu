<?php
/**
 * includs/vitrine_accueil.php : produit reel presente sur la page d'accueil.
 * Le produit actif le plus recent qui a une image, lu en lecture seule et garde
 * en cache 10 minutes (APCu si disponible, sinon un fichier dans storage/cache/).
 * Retourne null s'il n'existe aucun produit avec image : la page garde alors
 * l'apercu d'exemple.
 */

require_once __DIR__ . '/affiliation_helpers.php';

if (!defined('VITRINE_CACHE_SECONDES')) {
    define('VITRINE_CACHE_SECONDES', 600);
}

if (!function_exists('urlImageProduit')) {
    // Meme regle que services/boutique.php : chemin relatif = fichier dans admin/
    function urlImageProduit(string $brute): string
    {
        if ($brute === '') return '';
        if (preg_match('#^(https?:)?//#', $brute) || str_starts_with($brute, 'data:')) return $brute;
        return '/admin/' . ltrim($brute, '/');
    }
}

if (!function_exists('produitVitrineAccueil')) {
    function produitVitrineAccueil(PDO $pdo): ?array
    {
        $cle     = 'monrevenu_vitrine_accueil';
        $fichier = dirname(__DIR__) . '/storage/cache/vitrine_accueil.json';
        $apcu    = function_exists('apcu_fetch') && (bool) ini_get('apc.enabled');

        if ($apcu) {
            $ok = false;
            $valeur = apcu_fetch($cle, $ok);
            if ($ok && is_array($valeur)) return $valeur['produit'];
        } elseif (is_file($fichier) && filemtime($fichier) > time() - VITRINE_CACHE_SECONDES) {
            $valeur = json_decode((string) @file_get_contents($fichier), true);
            if (is_array($valeur) && array_key_exists('produit', $valeur)) return $valeur['produit'];
        }

        $produit = null;
        try {
            $stmt = $pdo->prepare(
                "SELECT id, nom_produit, image, prix_vente
                 FROM vendeur_produits
                 WHERE statut = 'actif' AND image <> '' AND image NOT LIKE 'data:%'
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1"
            );
            $stmt->execute();
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[vitrine_accueil] lecture impossible : ' . $e->getMessage());
            return null; // pas de mise en cache d'une erreur
        }

        if ($ligne) {
            $url = urlImageProduit((string) $ligne['image']);
            $largeur = 400;
            $hauteur = 400;
            $octets  = null;
            if (str_starts_with($url, '/admin/')) {
                $chemin = dirname(__DIR__) . $url;
                if (!is_file($chemin)) {
                    $url = '';
                } else {
                    $taille = @getimagesize($chemin);
                    if ($taille) { $largeur = (int) $taille[0]; $hauteur = (int) $taille[1]; }
                    $octets = filesize($chemin) ?: null;
                }
            }
            if ($url !== '') {
                $prix = (float) $ligne['prix_vente'];
                $produit = [
                    'id'         => (int) $ligne['id'],
                    'nom'        => (string) $ligne['nom_produit'],
                    'image'      => $url,
                    'largeur'    => $largeur,
                    'hauteur'    => $hauteur,
                    'octets'     => $octets,
                    'prix'       => $prix,
                    'commission' => calculerCommission($prix),
                ];
            }
        }

        $valeur = ['produit' => $produit];
        if ($apcu) {
            apcu_store($cle, $valeur, VITRINE_CACHE_SECONDES);
        } else {
            $dossier = dirname($fichier);
            if (is_dir($dossier) || @mkdir($dossier, 0755, true)) {
                $tmp = $fichier . '.' . bin2hex(random_bytes(4));
                if (@file_put_contents($tmp, json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
                    @rename($tmp, $fichier);
                }
            }
        }
        return $produit;
    }
}
