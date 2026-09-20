-- Retour arriere de 009 : retire les trois cles etrangeres, garde les index.
-- Idempotent (DROP FOREIGN KEY IF EXISTS).

ALTER TABLE withdrawals DROP FOREIGN KEY IF EXISTS fk_withdrawals_user;
ALTER TABLE transactions_monrevenu DROP FOREIGN KEY IF EXISTS fk_transactions_user;
ALTER TABLE vendeur_ventes DROP FOREIGN KEY IF EXISTS fk_ventes_affilie;
