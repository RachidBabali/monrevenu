<?php
/**
 * includs/signalement.php : signalement d'un produit par un affilie (bloc H3).
 * Un affilie ne peut signaler qu'une fois le meme produit (uniq_signalement). Au-dela du
 * seuil configure (reglages_publication.seuil_signalements), le produit est suspendu
 * automatiquement en attendant une decision administrateur ; il reste dans le catalogue
 * du commercant (moderation inchangee) mais disparait du catalogue public (statut).
 */

require_once __DIR__ . '/reglages_publication.php';

if (!function_exists('signalerProduit')) {

    /** @return array{ok: bool, deja_signale: bool, suspendu: bool} */
    function signalerProduit(PDO $pdo, int $produitId, int $affilieId, string $motif = ''): array
    {
        $motif = mb_substr(trim($motif), 0, 255);
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("SELECT id, vendeur_id, nom_produit, statut, nb_signalements FROM vendeur_produits WHERE id = ? FOR UPDATE");
            $st->execute([$produitId]);
            $produit = $st->fetch(PDO::FETCH_ASSOC);
            if (!$produit) { $pdo->rollBack(); return ['ok' => false, 'deja_signale' => false, 'suspendu' => false]; }

            try {
                $pdo->prepare("INSERT INTO produit_signalements (produit_id, signale_par, motif) VALUES (?, ?, ?)")
                    ->execute([$produitId, $affilieId, $motif !== '' ? $motif : null]);
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) { $pdo->rollBack(); return ['ok' => true, 'deja_signale' => true, 'suspendu' => false]; }
                throw $e;
            }

            $pdo->prepare("UPDATE vendeur_produits SET nb_signalements = nb_signalements + 1 WHERE id = ?")->execute([$produitId]);
            $nbSignalements = (int) $produit['nb_signalements'] + 1;
            $reglages = reglagesPublication($pdo);
            $suspendu = false;

            auditInfo($pdo, ['category' => 'produit', 'action' => 'produit_signalement', 'entity_type' => 'produit', 'entity_id' => $produitId,
                'actor_id' => $affilieId, 'meta' => ['nb_signalements' => $nbSignalements]]);

            if ($nbSignalements >= (int) $reglages['seuil_signalements'] && $produit['statut'] === 'actif') {
                $pdo->prepare("UPDATE vendeur_produits SET statut = 'suspendu', moderation_note = ? WHERE id = ?")
                    ->execute(['Suspendu automatiquement : ' . $nbSignalements . ' signalements, en attente de décision.', $produitId]);
                auditInfo($pdo, ['category' => 'produit', 'action' => 'produit_suspension_automatique', 'entity_type' => 'produit',
                    'entity_id' => $produitId, 'actor_id' => null, 'actor_role' => 'systeme',
                    'before' => ['statut' => 'actif'], 'after' => ['statut' => 'suspendu'], 'meta' => ['nb_signalements' => $nbSignalements]]);
                $suspendu = true;
            }

            $pdo->commit();

            if ($suspendu) {
                try {
                    require_once __DIR__ . '/notifications.php';
                    envoyerNotification($pdo, (int) $produit['vendeur_id'],
                        "Votre produit « " . $produit['nom_produit'] . " » a été suspendu automatiquement après plusieurs signalements. Il est en attente de décision.",
                        'Produit suspendu', '/commercant/produits.php');
                } catch (Throwable $t) {
                    error_log('[signalement] notification : ' . get_class($t));
                }
            }
            return ['ok' => true, 'deja_signale' => false, 'suspendu' => $suspendu];
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $t;
        }
    }
}
