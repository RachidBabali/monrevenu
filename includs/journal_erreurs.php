<?php
/**
 * includs/journal_erreurs.php : filet de securite global (erreurs, exceptions non rattrapees, fatals).
 *
 * Chaque incident est ecrit dans app_logs/php_errors.log, HORS de la racine web quand elle s'appelle public_html
 * (production : /home/.../domains/monrevenu.xyz/app_logs/), sinon dans storage/logs/ (developpement, bloque par
 * .htaccess). Une ligne : date | URI | utilisateur | fichier:ligne | message. Aucun detail technique n'est
 * jamais envoye au navigateur : une exception ou un fatal affiche une page 500 neutre.
 * Charge une seule fois, par includs/session.php et basse_de_donner/monrevenu_bd.php.
 */
if (defined('JOURNAL_ERREURS_ACTIF')) return;
define('JOURNAL_ERREURS_ACTIF', true);

if (!function_exists('cheminJournalErreurs')) {
    /** Dossier des journaux, cree au besoin ; repli sur storage/logs si le dossier voulu n'est pas ecrivable. */
    function cheminJournalErreurs(): string
    {
        static $chemin = null;
        if ($chemin !== null) return $chemin;
        $racine = dirname(__DIR__);
        $candidats = [];
        if (basename($racine) === 'public_html') $candidats[] = dirname($racine) . '/app_logs';
        $candidats[] = $racine . '/storage/logs';
        foreach ($candidats as $dossier) {
            if ((is_dir($dossier) || @mkdir($dossier, 0750, true)) && is_writable($dossier)) {
                return $chemin = $dossier . '/php_errors.log';
            }
        }
        return $chemin = '';
    }
}

if (!function_exists('journaliserErreur')) {
    function journaliserErreur(string $fichier, int $ligne, string $message, string $niveau = 'ERREUR'): void
    {
        $uri = ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . substr((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ($_SERVER['argv'][0] ?? '')), 0, 200); // chemin seul : jamais de query string (jetons)
        $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $ligneLog = sprintf("[%s] %s | %s | user=%d | %s:%d | %s\n", date('Y-m-d H:i:s'), $niveau, $uri, $uid,
            str_replace(dirname(__DIR__) . '/', '', $fichier), $ligne, str_replace(["\r", "\n"], ' ', substr($message, 0, 1000)));
        $cible = cheminJournalErreurs();
        if ($cible === '' || @file_put_contents($cible, $ligneLog, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($ligneLog)); // dernier recours : journal PHP du serveur
        }
    }
}

if (!function_exists('pageErreur500')) {
    function pageErreur500(): void
    {
        if (PHP_SAPI === 'cli') return;
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Erreur temporaire</title>'
            . '<style>:root{color-scheme:light dark}body{font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem;background:#fff;color:#1f2937}a{color:#1e3a8a}'
            . '@media(prefers-color-scheme:dark){body{background:#0f141c;color:#e6eaf0}a{color:#8fb0f0}}</style></head><body>'
            . '<h1 style="font-size:1.25rem">Une erreur est survenue</h1>'
            . '<p>Nous n\'avons pas pu afficher cette page. Réessayez dans un instant ; si le problème continue, contactez le support.</p>'
            . '<p><a href="/">Retour à l\'accueil</a></p></body></html>';
    }
}

// Avertissements et notices : journalises ici ; PHP poursuit ensuite son traitement habituel.
set_error_handler(static function (int $no, string $msg, string $fichier, int $ligne): bool {
    if (!(error_reporting() & $no)) return false;
    journaliserErreur($fichier, $ligne, $msg, 'PHP-' . $no);
    return false; // le traitement PHP habituel continue (affichage en dev, journal serveur)
});

// Exception non rattrapee : journal + page 500 propre.
set_exception_handler(static function (Throwable $e): void {
    journaliserErreur($e->getFile(), $e->getLine(), get_class($e) . ' : ' . $e->getMessage(), 'EXCEPTION');
    pageErreur500();
});

// Erreurs fatales (memoire, compilation, type) : invisibles pour les handlers ci-dessus.
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        journaliserErreur($e['file'], $e['line'], $e['message'], 'FATAL');
        pageErreur500();
    }
});
