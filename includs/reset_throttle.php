<?php
/**
 * includs/reset_throttle.php : anti-abus sur l'ENVOI de code de reinitialisation de mot de passe.
 *
 * Plafonne le nombre d'envois par IP (demande initiale + renvois confondus), pour empecher
 * l'inondation d'emails d'une victime et la saturation du journal. La verification du code lui-meme
 * (5 essais puis destruction) reste geree ailleurs, dans reinitialiser_mot_de_passe.php.
 *
 * Table : reset_password_attempts (migration 015). FAIL-OPEN : si la table n'existe pas encore
 * (migration non passee) ou en cas d'erreur DB, on n'empeche jamais l'envoi. Le plafond ne
 * s'active donc qu'une fois la migration 015 appliquee.
 *
 * Ne revele jamais l'existence d'un compte : le compteur est purement par IP et le message
 * "trop de demandes" est identique quelle que soit l'adresse email saisie.
 */

if (!defined('RESET_MAX_ENVOIS')) {
    define('RESET_MAX_ENVOIS', 5);           // envois maximum par fenetre
    define('RESET_FENETRE_SECONDES', 1800);  // 30 minutes
}

if (!function_exists('resetThrottleEtat')) {
    /**
     * Etat du plafond pour une IP.
     * @return array{bloque: bool, reste_min: int} reste_min = minutes avant deblocage
     */
    function resetThrottleEtat(PDO $pdo, string $ip): array
    {
        try {
            $st = $pdo->prepare("SELECT attempts, last_attempt FROM reset_password_attempts WHERE ip = ?");
            $st->execute([$ip]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['bloque' => false, 'reste_min' => 0];
            }
            $diff = time() - strtotime((string) $row['last_attempt']);
            // Fenetre expiree : le prochain enregistrement repartira de zero.
            if ($diff >= RESET_FENETRE_SECONDES) {
                return ['bloque' => false, 'reste_min' => 0];
            }
            if ((int) $row['attempts'] >= RESET_MAX_ENVOIS) {
                return ['bloque' => true, 'reste_min' => (int) ceil((RESET_FENETRE_SECONDES - $diff) / 60)];
            }
            return ['bloque' => false, 'reste_min' => 0];
        } catch (PDOException $e) {
            // Table absente (migration non passee) ou erreur : fail-open, on laisse passer.
            error_log('resetThrottleEtat (fail-open) : ' . $e->getMessage());
            return ['bloque' => false, 'reste_min' => 0];
        }
    }
}

if (!function_exists('resetThrottleEnregistrer')) {
    /** Comptabilise un envoi pour cette IP (a appeler quand un code est effectivement envoye/tente). */
    function resetThrottleEnregistrer(PDO $pdo, string $ip): void
    {
        try {
            $st = $pdo->prepare("SELECT last_attempt FROM reset_password_attempts WHERE ip = ?");
            $st->execute([$ip]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if ($row && (time() - strtotime((string) $row['last_attempt'])) < RESET_FENETRE_SECONDES) {
                // Fenetre en cours : on incremente.
                $pdo->prepare("UPDATE reset_password_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE ip = ?")
                    ->execute([$ip]);
            } else {
                // Aucune ligne ou fenetre expiree : on (re)part a 1.
                $pdo->prepare(
                    "INSERT INTO reset_password_attempts (ip, attempts, last_attempt) VALUES (?, 1, NOW())
                     ON DUPLICATE KEY UPDATE attempts = 1, last_attempt = NOW()"
                )->execute([$ip]);
            }
        } catch (PDOException $e) {
            // Fail-open : ne jamais bloquer l'envoi a cause du compteur.
            error_log('resetThrottleEnregistrer (fail-open) : ' . $e->getMessage());
        }
    }
}
