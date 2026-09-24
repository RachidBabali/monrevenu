-- Verification de 010 : les quatre tables ont la colonne devise, en CHAR(3) et acceptant NULL.
-- Sans risque a tout moment : si la colonne manque, un message l'indique au lieu de l'erreur #1054.

SELECT t.nom_table, IF(c.column_name IS NULL, 'ABSENTE : appliquer 010_devise_par_ligne.sql', 'presente') AS colonne_devise,
       c.column_type, c.is_nullable
FROM (SELECT 'vendeur_produits' AS nom_table UNION ALL SELECT 'vendeur_ventes' AS nom_table UNION ALL SELECT 'transactions_monrevenu' AS nom_table UNION ALL SELECT 'withdrawals' AS nom_table) t
LEFT JOIN information_schema.columns c
  ON c.table_schema = DATABASE() AND c.table_name = t.nom_table AND c.column_name = 'devise';

-- Repartition apres quelques jours d'utilisation : les nouvelles lignes doivent etre renseignees.
SET @q = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'vendeur_produits' AND column_name = 'devise') = 1,
  'SELECT devise, COUNT(*) AS lignes FROM vendeur_produits GROUP BY devise',
  'SELECT ''vendeur_produits.devise absente'' AS avertissement');
PREPARE controle FROM @q; EXECUTE controle; DEALLOCATE PREPARE controle;
SET @q = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'withdrawals' AND column_name = 'devise') = 1,
  'SELECT devise, COUNT(*) AS lignes FROM withdrawals GROUP BY devise',
  'SELECT ''withdrawals.devise absente'' AS avertissement');
PREPARE controle FROM @q; EXECUTE controle; DEALLOCATE PREPARE controle;

-- Controle de coherence : aucune ligne ne doit porter une devise differente du marche de son proprietaire.
SET @q = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'vendeur_produits' AND column_name = 'devise') = 1,
  'SELECT p.id, p.devise, u.pays_code FROM vendeur_produits p JOIN users_monrevenu u ON u.id = p.vendeur_id WHERE p.devise IS NOT NULL AND ((u.pays_code = ''KM'' AND p.devise <> ''KMF'') OR (u.pays_code = ''SN'' AND p.devise <> ''XOF''))',
  'SELECT ''vendeur_produits.devise absente'' AS avertissement');
PREPARE controle FROM @q; EXECUTE controle; DEALLOCATE PREPARE controle;
