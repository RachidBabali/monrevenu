-- Verification de 010 : les quatre tables ont la colonne devise, en CHAR(3) et acceptant NULL.

SHOW COLUMNS FROM vendeur_produits LIKE 'devise';
SHOW COLUMNS FROM vendeur_ventes LIKE 'devise';
SHOW COLUMNS FROM transactions_monrevenu LIKE 'devise';
SHOW COLUMNS FROM withdrawals LIKE 'devise';

-- Repartition apres quelques jours d'utilisation : les nouvelles lignes doivent etre renseignees.
SELECT devise, COUNT(*) AS lignes FROM vendeur_produits GROUP BY devise;
SELECT devise, COUNT(*) AS lignes FROM withdrawals GROUP BY devise;

-- Controle de coherence : aucune ligne ne doit porter une devise differente du marche de son proprietaire.
SELECT p.id, p.devise, u.pays_code
FROM vendeur_produits p JOIN users_monrevenu u ON u.id = p.vendeur_id
WHERE p.devise IS NOT NULL
  AND ((u.pays_code = 'KM' AND p.devise <> 'KMF') OR (u.pays_code = 'SN' AND p.devise <> 'XOF'));
