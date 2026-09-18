<?php
/**
 * env_loader.php, Charge les variables du fichier .env dans getenv()/$_ENV
 * À placer dans : includs/env_loader.php
 *
 * Usage (en tout début de fichier, avant toute autre config) :
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/includs/env_loader.php';
 *   $dbHost = env('DB_HOST', 'localhost'); // 2e argument = valeur par défaut
 */

if (!function_exists('chargerEnv')) {
    function chargerEnv(string $cheminFichier): void
    {
        if (!is_file($cheminFichier) || !is_readable($cheminFichier)) {
            return; // pas de .env (ex: certains environnements de test), on continue silencieusement
        }

        $lignes = file($cheminFichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lignes as $ligne) {
            $ligne = trim($ligne);

            // Ignore les commentaires et lignes vides
            if ($ligne === '' || str_starts_with($ligne, '#') || str_starts_with($ligne, '//')) {
                continue;
            }

            if (!str_contains($ligne, '=')) {
                continue;
            }

            [$cle, $valeur] = explode('=', $ligne, 2);
            $cle    = trim($cle);
            $valeur = trim($valeur);

            // Retire les guillemets englobants s'il y en a
            if (strlen($valeur) >= 2) {
                $premier = $valeur[0];
                $dernier = $valeur[strlen($valeur) - 1];
                if (($premier === '"' && $dernier === '"') || ($premier === "'" && $dernier === "'")) {
                    $valeur = substr($valeur, 1, -1);
                }
            }

            if ($cle === '') {
                continue;
            }

            // Ne écrase pas une variable déjà définie au niveau serveur (ex: config Docker/Hostinger)
            if (getenv($cle) === false) {
                putenv("{$cle}={$valeur}");
                $_ENV[$cle]    = $valeur;
                $_SERVER[$cle] = $valeur;
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $cle, $defaut = null)
    {
        $valeur = getenv($cle);
        return $valeur === false ? $defaut : $valeur;
    }
}

// Charge automatiquement le .env situé à la racine du projet
chargerEnv($_SERVER['DOCUMENT_ROOT'] . '/.env');