<?php
/**
 * includs/incident.php : erreurs techniques montrees a l'utilisateur.
 *
 * Regle : personne ne voit le detail technique d'une exception (message SQL, chemin de fichier,
 * nom de table). L'ecran affiche un message clair suivi d'une reference courte, et le detail part
 * masque dans le journal d'audit (categorie systeme, action incident) et dans le journal PHP.
 * La reference permet de retrouver la ligne du journal a partir de la capture d'ecran d'un
 * utilisateur, sans rien lui reveler.
 *
 * Usage :
 *   } catch (Throwable $t) {
 *       $error = messageIncident(incidentEnregistrer($pdo, $t, 'admin/retraits'),
 *                                "Le retrait n'a pas pu etre enregistre.");
 *   }
 */

require_once __DIR__ . '/audit.php';

if (!function_exists('incidentReference')) {
    /** Reference courte, lisible au telephone et dictable : 8 caracteres, chiffres et lettres sans ambiguite. */
    function incidentReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // sans 0, O, 1, I
        $ref = '';
        for ($i = 0; $i < 8; $i++) {
            $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $ref;
    }

    /**
     * Detail technique reduit a l'essentiel : classe, code, message sans adresse, sans numero
     * long, sans chemin absolu, et fichier reduit a son nom.
     */
    function incidentDetail(Throwable $t): array
    {
        $message = $t->getMessage();
        $message = preg_replace('/\S+@\S+/', '[adresse]', $message);
        $message = preg_replace('/\d{6,}/', '[nombre]', $message);
        $message = preg_replace('#(/[\w.-]+)+/#', '', $message);
        return [
            'classe'  => get_class($t),
            'code_erreur' => (string) $t->getCode(),
            'detail'  => mb_substr(trim((string) $message), 0, 300),
            'fichier' => basename($t->getFile()) . ':' . $t->getLine(),
        ];
    }

    /**
     * Journalise l'incident et retourne sa reference.
     * $contexte : ou l'erreur s'est produite, en clair (admin/retraits, commande, inscription).
     * Ne leve jamais : on est deja dans un chemin d'erreur.
     */
    function incidentEnregistrer(?PDO $pdo, Throwable $t, string $contexte, array $meta = []): string
    {
        $ref = incidentReference();
        $infos = incidentDetail($t);
        error_log('[incident ' . $ref . '] ' . $contexte . ' : ' . $infos['classe'] . ' ' . $infos['fichier'] . ' ' . $t->getMessage());
        try {
            auditInfo($pdo, [
                'category' => 'systeme',
                'action'   => 'incident',
                'result'   => 'echec',
                'meta'     => ['reference' => $ref, 'contexte' => $contexte] + $infos + $meta,
            ]);
        } catch (Throwable $ignore) {
            // Le journal est deja en echec : la reference reste dans le journal PHP.
        }
        return $ref;
    }

    /** Message affiche : phrase claire, puis la reference a communiquer au support. */
    function messageIncident(string $reference, string $message = 'Une erreur technique est survenue. Reessayez dans un instant.'): string
    {
        return $message . ' Référence : ' . $reference;
    }
}
