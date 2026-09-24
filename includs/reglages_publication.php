<?php
/**
 * includs/reglages_publication.php : reglage "Publication" (bloc H) et controles automatiques
 * appliques a un produit de commercant avant de decider s'il est publie sans intervention.
 *
 * Trois modes (reglages_publication.mode) :
 *   manuelle              : comportement historique, inchange. Seul le flag confiance d'un
 *                           commercant (commercants_profils.confiance) publie sans validation.
 *   automatique_controles : par defaut. Le compte devient valide des la verification (voir
 *                           verification.php), et chaque produit passe les controles de
 *                           evaluerPublicationProduit() avant publication automatique.
 *   automatique_confiance : le compte devient valide des la verification, et les produits sont
 *                           publies immediatement (les controles de contenu ne sont pas
 *                           appliques), sauf boutique a surveiller ou premiers produits.
 *
 * Deux garde-fous s'appliquent quel que soit le mode automatique choisi :
 *   - une boutique marquee "a surveiller" (commercants_profils.surveillance) repasse toujours
 *     par une validation manuelle ;
 *   - les N premiers produits d'une boutique (reglages_publication.premiers_produits_a_valider,
 *     defaut 0) passent toujours par une validation manuelle, le temps de juger la boutique.
 */

require_once __DIR__ . '/config_marche.php';

if (!function_exists('reglagesPublication')) {

    /** Reglages courants, valeurs par defaut si la ligne est absente (migration pas encore jouee). */
    function reglagesPublication(PDO $pdo): array
    {
        static $reglages = null;
        if ($reglages !== null) return $reglages;
        $defaut = [
            'mode' => 'automatique_controles',
            'premiers_produits_a_valider' => 0,
            'prix_min_xof' => 100.0, 'prix_max_xof' => 500000.0,
            'prix_min_kmf' => 100.0, 'prix_max_kmf' => 375000.0,
            'seuil_signalements' => 3,
            'mots_interdits' => '',
        ];
        try {
            $ligne = $pdo->query("SELECT * FROM reglages_publication WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $ligne = false; // table pas encore creee : migration 011 pas jouee
        }
        $reglages = $ligne ? array_merge($defaut, $ligne) : $defaut;
        return $reglages;
    }

    /** Liste des mots interdits, un par ligne dans la colonne, filtree des lignes vides. */
    function motsInterditsListe(array $reglages): array
    {
        $lignes = preg_split('/\r\n|\r|\n/', (string) ($reglages['mots_interdits'] ?? ''));
        return array_values(array_filter(array_map('trim', $lignes), fn($m) => $m !== ''));
    }

    /** Met a jour les reglages (page admin/reglages.php). Valide et journalise. */
    function sauvegarderReglagesPublication(PDO $pdo, array $valeurs, int $adminId): array
    {
        $modesValides = ['manuelle', 'automatique_controles', 'automatique_confiance'];
        $mode = in_array($valeurs['mode'] ?? '', $modesValides, true) ? $valeurs['mode'] : 'automatique_controles';
        $premiers = max(0, min(20, (int) ($valeurs['premiers_produits_a_valider'] ?? 0)));
        $seuil = max(1, min(50, (int) ($valeurs['seuil_signalements'] ?? 3)));
        $prixMinXof = max(0, (float) ($valeurs['prix_min_xof'] ?? 0));
        $prixMaxXof = max($prixMinXof, (float) ($valeurs['prix_max_xof'] ?? 0));
        $prixMinKmf = max(0, (float) ($valeurs['prix_min_kmf'] ?? 0));
        $prixMaxKmf = max($prixMinKmf, (float) ($valeurs['prix_max_kmf'] ?? 0));
        $motsInterdits = mb_substr(trim((string) ($valeurs['mots_interdits'] ?? '')), 0, 5000);

        $avant = reglagesPublication($pdo);
        $pdo->prepare(
            "UPDATE reglages_publication SET mode = ?, premiers_produits_a_valider = ?, prix_min_xof = ?, prix_max_xof = ?,
                prix_min_kmf = ?, prix_max_kmf = ?, seuil_signalements = ?, mots_interdits = ?, updated_by = ?, updated_at = NOW()
             WHERE id = 1"
        )->execute([$mode, $premiers, $prixMinXof, $prixMaxXof, $prixMinKmf, $prixMaxKmf, $seuil, $motsInterdits, $adminId]);

        $apres = ['mode' => $mode, 'premiers_produits_a_valider' => $premiers, 'prix_min_xof' => $prixMinXof, 'prix_max_xof' => $prixMaxXof,
            'prix_min_kmf' => $prixMinKmf, 'prix_max_kmf' => $prixMaxKmf, 'seuil_signalements' => $seuil];
        [$b, $a] = auditDiff(
            ['mode' => $avant['mode'], 'premiers_produits_a_valider' => (int) $avant['premiers_produits_a_valider'],
                'prix_min_xof' => (float) $avant['prix_min_xof'], 'prix_max_xof' => (float) $avant['prix_max_xof'],
                'prix_min_kmf' => (float) $avant['prix_min_kmf'], 'prix_max_kmf' => (float) $avant['prix_max_kmf'],
                'seuil_signalements' => (int) $avant['seuil_signalements']],
            $apres
        );
        auditCritique($pdo, ['category' => 'admin', 'action' => 'reglages_publication', 'entity_type' => 'reglages',
            'entity_id' => 'publication', 'before' => $b, 'after' => $a]);

        return $apres;
    }

    /** Prix hors de la plage configuree pour ce marche. */
    function prixHorsPlage(float $prix, string $marche, array $reglages): bool
    {
        $iso = deviseIso($marche);
        [$min, $max] = $iso === 'KMF'
            ? [(float) $reglages['prix_min_kmf'], (float) $reglages['prix_max_kmf']]
            : [(float) $reglages['prix_min_xof'], (float) $reglages['prix_max_xof']];
        return $prix < $min || $prix > $max;
    }

    /** Premier mot interdit trouve dans le texte (nom + description), insensible a la casse et aux accents simples. */
    function motInterditTrouve(string $texte, array $reglages): ?string
    {
        $normalise = mb_strtolower($texte);
        foreach (motsInterditsListe($reglages) as $mot) {
            if ($mot !== '' && mb_strpos($normalise, mb_strtolower($mot)) !== false) return $mot;
        }
        return null;
    }

    /**
     * Coordonnees qui permettraient de contourner la commande sur la plateforme : numero de
     * telephone (6 chiffres consecutifs ou plus), adresse e-mail, ou site web.
     */
    function contientCoordonnees(string $texte): bool
    {
        if (preg_match('/\d[\d\s.\-]{5,}\d/', $texte)) return true; // suite de chiffres, separateurs courants inclus
        if (preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/', $texte)) return true;
        if (preg_match('/\b(https?:\/\/|www\.)\S+/i', $texte)) return true;
        if (preg_match('/\b[a-z0-9-]+\.(com|net|org|xyz|shop|store|sn|km)\b/i', $texte)) return true;
        return false;
    }

    /** Meme nom (normalise) ou meme image deja utilisee par ce commercant. */
    function produitEstDoublon(PDO $pdo, int $vendeurId, string $nom, string $image, ?int $exclureProduitId = null): bool
    {
        $sql = "SELECT id FROM vendeur_produits WHERE vendeur_id = ? AND (LOWER(TRIM(nom_produit)) = ? OR (image <> '' AND image = ?))";
        $params = [$vendeurId, mb_strtolower(trim($nom)), $image];
        if ($exclureProduitId !== null) { $sql .= ' AND id <> ?'; $params[] = $exclureProduitId; }
        $sql .= ' LIMIT 1';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /**
     * Regle H2 (dans l'ordre de la consigne) qui bloque la publication automatique, ou null si
     * le produit passe tous les controles.
     */
    function controleAutomatiqueEchoue(PDO $pdo, array $reglages, int $vendeurId, array $donnees, string $marche, ?int $exclureProduitId = null): ?string
    {
        if (prixHorsPlage((float) $donnees['prix'], $marche, $reglages)) {
            return 'Prix hors de la plage autorisée pour ce marché.';
        }
        $texte = $donnees['nom'] . ' ' . $donnees['description'];
        $mot = motInterditTrouve($texte, $reglages);
        if ($mot !== null) {
            return 'Mot ou expression non autorisé dans la fiche.';
        }
        if (contientCoordonnees($texte)) {
            return 'Numéro de téléphone, e-mail ou site web détecté dans la fiche.';
        }
        if (produitEstDoublon($pdo, $vendeurId, $donnees['nom'], $donnees['image'] ?? '', $exclureProduitId)) {
            return 'Produit très proche (même nom ou même photo) déjà publié par cette boutique.';
        }
        return null;
    }

    /**
     * Decision de publication d'un produit envoye par un commercant (creation ou nouvel envoi
     * pour validation). Ne s'applique jamais aux produits crees par l'administration.
     *
     * @param array $donnees ['nom' => string, 'description' => string, 'prix' => float, 'image' => string]
     * @return array{moderation: string, publication_type: string, motif: ?string}
     */
    function evaluerPublicationProduit(PDO $pdo, array $profil, array $donnees, string $marche, ?int $exclureProduitId = null): array
    {
        $reglages = reglagesPublication($pdo);
        $vendeurId = (int) $profil['user_id'];

        if ($reglages['mode'] === 'manuelle') {
            $approuve = (int) ($profil['confiance'] ?? 0) === 1;
            return ['moderation' => $approuve ? 'approuve' : 'en_attente', 'publication_type' => 'manuel', 'motif' => null];
        }

        if ((int) ($profil['surveillance'] ?? 0) === 1) {
            return ['moderation' => 'en_attente', 'publication_type' => 'manuel',
                'motif' => 'Boutique à surveiller : chaque produit passe par une validation manuelle.'];
        }

        $st = $pdo->prepare('SELECT COUNT(*) FROM vendeur_produits WHERE vendeur_id = ?' . ($exclureProduitId !== null ? ' AND id <> ?' : ''));
        $st->execute($exclureProduitId !== null ? [$vendeurId, $exclureProduitId] : [$vendeurId]);
        $nbProduitsExistants = (int) $st->fetchColumn();
        $premiers = (int) $reglages['premiers_produits_a_valider'];
        if ($nbProduitsExistants < $premiers) {
            return ['moderation' => 'en_attente', 'publication_type' => 'manuel',
                'motif' => 'Un des ' . $premiers . ' premiers produits de cette boutique : validation manuelle.'];
        }

        if ($reglages['mode'] === 'automatique_confiance') {
            return ['moderation' => 'approuve', 'publication_type' => 'automatique', 'motif' => null];
        }

        $motif = controleAutomatiqueEchoue($pdo, $reglages, $vendeurId, $donnees, $marche, $exclureProduitId);
        if ($motif !== null) {
            return ['moderation' => 'en_attente', 'publication_type' => 'automatique', 'motif' => $motif];
        }
        return ['moderation' => 'approuve', 'publication_type' => 'automatique', 'motif' => null];
    }
}
