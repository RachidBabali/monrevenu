<?php
/**
 * includs/argent.php : tout mouvement de solde passe par mouvementSolde().
 *
 * A appeler DANS une transaction ouverte par l'appelant. La fonction :
 *   1. verrouille l'utilisateur (SELECT ... FOR UPDATE) et lit son solde ;
 *   2. refuse un solde final negatif ;
 *   3. met a jour le solde ;
 *   4. ecrit la ligne transactions_monrevenu (reference UNIQUE : un second appel avec la
 *      meme reference echoue, ce qui empeche tout double credit) avec solde avant et apres ;
 *   5. ecrit la ligne du journal d'audit (fail closed) et la relie a la transaction.
 * Toute erreur leve une exception : l'appelant annule sa transaction.
 */

require_once __DIR__ . '/audit.php';

if (!function_exists('mouvementSolde')) {
    class SoldeInsuffisant extends RuntimeException {}
    class ReferenceDejaUtilisee extends RuntimeException {}

    /**
     * $montant : positif = credit, negatif = debit (chaine decimale ou nombre, arrondi a 2 decimales).
     * $type : valeur de transactions_monrevenu.type (depot, retrait, commission).
     * Retourne ['transaction_id', 'audit_id', 'avant', 'apres'].
     */
    function mouvementSolde(PDO $pdo, int $userId, $montant, string $type, string $reference, string $statut,
                            string $description, string $actionAudit, array $meta = [], ?int $acteurId = null): array
    {
        if (!$pdo->inTransaction()) throw new LogicException('mouvementSolde exige une transaction ouverte');
        $centimes = (int) round(((float) $montant) * 100);
        if ($centimes === 0) throw new InvalidArgumentException('Montant nul');

        $st = $pdo->prepare("SELECT balance FROM users_monrevenu WHERE id = ? FOR UPDATE");
        $st->execute([$userId]);
        $solde = $st->fetchColumn();
        if ($solde === false) throw new RuntimeException('Utilisateur introuvable');
        $avantC = (int) round(((float) $solde) * 100);
        $apresC = $avantC + $centimes;
        if ($apresC < 0) throw new SoldeInsuffisant('Solde insuffisant');

        $avant = number_format($avantC / 100, 2, '.', '');
        $apres = number_format($apresC / 100, 2, '.', '');
        $abs   = number_format(abs($centimes) / 100, 2, '.', '');

        $pdo->prepare("UPDATE users_monrevenu SET balance = ? WHERE id = ?")->execute([$apres, $userId]);

        $acteur = $acteurId ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
        try {
            $pdo->prepare(
                "INSERT INTO transactions_monrevenu (user_id, type, amount, reference, status, description, balance_before, balance_after, actor_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$userId, $type, $abs, $reference, $statut, $description, $avant, $apres, $acteur]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) throw new ReferenceDejaUtilisee('Reference deja utilisee');
            throw $e;
        }
        $txId = (int) $pdo->lastInsertId();

        $auditId = auditCritique($pdo, [
            'category'    => 'argent',
            'action'      => $actionAudit,
            'entity_type' => 'transaction',
            'entity_id'   => $txId,
            'actor_id'    => $acteur,
            'before'      => ['balance' => $avant],
            'after'       => ['balance' => $apres],
            'meta'        => array_merge(['user_id' => $userId, 'montant' => ($centimes < 0 ? '-' : '') . $abs, 'type' => $type, 'reference' => $reference], $meta),
        ]);
        $pdo->prepare("UPDATE transactions_monrevenu SET audit_id = ? WHERE id = ?")->execute([$auditId, $txId]);

        return ['transaction_id' => $txId, 'audit_id' => $auditId, 'avant' => $avant, 'apres' => $apres];
    }
}
