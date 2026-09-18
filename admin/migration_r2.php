<?php
/**
 * migration_r2.php, Migre les images déjà présentes sur le serveur
 * (admin/uploads/produits et admin/uploads/produits_stock) vers Cloudflare R2,
 * et met à jour les lignes correspondantes dans vendeur_produits et produits_stock.
 *
 * À placer dans admin/ et exécuter UNE SEULE FOIS en ligne de commande (SSH) :
 *      php migration_r2.php
 *
 * Ne pas déposer ce fichier accessible publiquement en HTTP, il n'a pas
 * de vérification de droits, seulement un garde-fou "CLI uniquement".
 * Supprime-le du serveur une fois la migration terminée.
 */

if (PHP_SAPI !== 'cli') {
    die("Ce script doit être exécuté en ligne de commande (SSH), pas via le navigateur.\n");
}

require_once __DIR__ . '/../basse_de_donner/monrevenu_bd.php';
require_once __DIR__ . '/../includs/env_loader.php';
require_once __DIR__ . '/../includs/r2_uploader.php';

$mappings = [
    [
        'dossier_local'   => __DIR__ . '/uploads/produits/',
        'prefixe_r2'      => 'produits/',
        'table'           => 'vendeur_produits',
        'colonne'         => 'image',
        'chemin_relatif'  => 'uploads/produits/',
    ],
    [
        'dossier_local'   => __DIR__ . '/uploads/produits_stock/',
        'prefixe_r2'      => 'produits-stock/',
        'table'           => 'produits_stock',
        'colonne'         => 'image',
        'chemin_relatif'  => 'uploads/produits_stock/',
    ],
];

$mimeParExtension = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

$totalMigres  = 0;
$totalErreurs = 0;

foreach ($mappings as $m) {
    echo "\n=== Migration : {$m['table']}.{$m['colonne']} ===\n";

    if (!is_dir($m['dossier_local'])) {
        echo "Dossier introuvable, on saute : {$m['dossier_local']}\n";
        continue;
    }

    $fichiers = array_diff(scandir($m['dossier_local']), ['.', '..']);

    if (empty($fichiers)) {
        echo "Aucun fichier à migrer dans ce dossier.\n";
        continue;
    }

    foreach ($fichiers as $nomFichier) {
        $cheminLocal = $m['dossier_local'] . $nomFichier;
        if (!is_file($cheminLocal)) {
            continue;
        }

        $extension = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));
        $mime = $mimeParExtension[$extension] ?? 'application/octet-stream';

        $cleR2 = $m['prefixe_r2'] . $nomFichier;

        echo "Upload de {$nomFichier} ... ";
        $resultat = uploaderVersR2($cheminLocal, $cleR2, $mime);

        if (!$resultat['ok']) {
            echo "ÉCHEC ({$resultat['error']})\n";
            $totalErreurs++;
            continue;
        }

        echo "OK -> {$resultat['url']}\n";

        // Met à jour toutes les lignes qui référencent encore l'ancien chemin local
        $ancienChemin = $m['chemin_relatif'] . $nomFichier;
        $stmt = $pdo->prepare("UPDATE {$m['table']} SET {$m['colonne']} = ? WHERE {$m['colonne']} = ?");
        $stmt->execute([$resultat['url'], $ancienChemin]);

        if ($stmt->rowCount() > 0) {
            echo "  -> {$stmt->rowCount()} ligne(s) mise(s) à jour en base.\n";
        }

        $totalMigres++;
    }
}

echo "\n=== Terminé : {$totalMigres} fichier(s) migré(s), {$totalErreurs} erreur(s). ===\n";
echo "Vérifie que toutes les images s'affichent bien avant de supprimer admin/uploads/produits* et admin/uploads/produits_stock du serveur, et supprime ce script.\n";