-- Retour arriere de 010 : retire les quatre colonnes. L'information de devise est perdue ;
-- les montants restent inchanges et sont relus avec le marche du proprietaire.
-- Idempotent (DROP COLUMN IF EXISTS).

ALTER TABLE vendeur_produits       DROP COLUMN IF EXISTS devise;
ALTER TABLE vendeur_ventes         DROP COLUMN IF EXISTS devise;
ALTER TABLE transactions_monrevenu DROP COLUMN IF EXISTS devise;
ALTER TABLE withdrawals            DROP COLUMN IF EXISTS devise;
