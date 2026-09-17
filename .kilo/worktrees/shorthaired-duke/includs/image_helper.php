<?php
/**
 * image_helper.php — Image par défaut générée en code, sans fichier externe
 * À placer dans : includs/image_helper.php
 *
 * Évite toute dépendance à un fichier "default.jpg" stocké localement ou
 * sur R2 : l'image est un SVG généré à la volée, encodé en data: URI.
 * Fonctionne avec la CSP actuelle car "data:" est déjà autorisé dans img-src.
 */

if (!function_exists('imageProduitParDefaut')) {
    /**
     * Retourne une image placeholder (icône produit générique) en data: URI,
     * prête à être utilisée directement comme valeur de colonne `image`
     * ou comme attribut src d'une balise <img>.
     */
    function imageProduitParDefaut(): string
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
             . '<rect width="40" height="40" rx="10" fill="#E2E8F0"/>'
             . '<path d="M10 28l6.5-8 5 5 6-9 6.5 12H10z" fill="#94A3B8"/>'
             . '<circle cx="15" cy="14" r="3" fill="#94A3B8"/>'
             . '</svg>';

        $cache = 'data:image/svg+xml;base64,' . base64_encode($svg);

        return $cache;
    }
}