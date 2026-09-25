<?php
/**
 * includs/tolerance_sql.php : lecture de listes qui ne fait jamais tomber la page.
 *
 * Une requete en erreur (table ou colonne pas encore migree, base momentanement indisponible) est journalisee
 * (includs/journal_erreurs.php) et donne une liste vide : le bloc concerne s'affiche vide, le reste de la page reste
 * utilisable. Cas particulier de la colonne `devise` (migration 010) : si elle manque, la requete est rejouee avec
 * NULL a sa place, et chaque ligne est alors lue avec la devise du marche de son proprietaire (XOF par defaut),
 * comme pour les lignes anterieures a la migration.
 */
require_once __DIR__ . '/journal_erreurs.php';

if (!function_exists('colonneExiste')) {
    /**
     * La colonne existe-t-elle sur cette table ? Sert a degrader une requete quand une migration
     * n'est pas encore appliquee (cas de `devise`, migration 010), au lieu de tomber sur une
     * erreur #1054. Resultat mis en cache pour la duree de la requete HTTP. En cas d'erreur de
     * lecture du schema : false, c'est-a-dire la variante la plus prudente.
     */
    function colonneExiste(PDO $pdo, string $table, string $colonne): bool
    {
        static $cache = [];
        $cle = $table . '.' . $colonne;
        if (isset($cache[$cle])) return $cache[$cle];
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $st->execute([$table, $colonne]);
            return $cache[$cle] = ((int) $st->fetchColumn() === 1);
        } catch (Throwable $e) {
            return $cache[$cle] = false;
        }
    }
}

if (!function_exists('lignesTolerantes')) {
    /** @return list<array<string,mixed>> */
    function lignesTolerantes(PDO $pdo, string $etiquette, string $sql, array $params = []): array
    {
        $executer = static function (string $requete) use ($pdo, $params): array {
            $st = $pdo->prepare($requete);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        };
        try {
            return $executer($sql);
        } catch (Throwable $e) {
            journaliserErreur($e->getFile(), $e->getLine(), "[$etiquette] " . $e->getMessage(), 'BLOC');
            if (stripos($e->getMessage(), 'devise') !== false && preg_match('/^(.*?\bFROM\b)(.*)$/si', $sql, $m)
                && preg_match('/\b\w+\.devise\b/', $m[1])) {
                try {
                    return $executer(preg_replace('/\b\w+\.devise\b/', 'NULL AS devise', $m[1]) . $m[2]);
                } catch (Throwable $e2) {
                    journaliserErreur($e2->getFile(), $e2->getLine(), "[$etiquette, sans devise] " . $e2->getMessage(), 'BLOC');
                }
            }
            return [];
        }
    }
}
