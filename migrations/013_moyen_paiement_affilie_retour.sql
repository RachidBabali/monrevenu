-- Retour arriere de 013 (supprime les moyens de paiement enregistres et les copies portees par les retraits).
ALTER TABLE withdrawals DROP COLUMN IF EXISTS numero_paiement, DROP COLUMN IF EXISTS operateur;
DROP TABLE IF EXISTS affilie_moyens_paiement;
