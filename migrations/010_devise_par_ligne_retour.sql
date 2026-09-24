-- ATTENTION : ce fichier SUPPRIME la colonne devise (et ses donnees). Ne le lancer que pour annuler la migration 010,
-- jamais avec 010_devise_par_ligne.sql ni _verif.sql dans le meme envoi.
-- Retour arriere de 010 : retire les quatre colonnes. L'information de devise est perdue ;
-- les montants restent inchanges et sont relus avec le marche du proprietaire.
-- Idempotent (DROP COLUMN IF EXISTS).

ALTER TABLE vendeur_produits       DROP COLUMN IF EXISTS devise;
ALTER TABLE vendeur_ventes         DROP COLUMN IF EXISTS devise;
ALTER TABLE transactions_monrevenu DROP COLUMN IF EXISTS devise;
ALTER TABLE withdrawals            DROP COLUMN IF EXISTS devise;
