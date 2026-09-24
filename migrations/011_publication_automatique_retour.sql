-- Retour arriere de 011 : retire les tables et colonnes ajoutees. Idempotent.
DROP TABLE IF EXISTS produit_signalements;
ALTER TABLE vendeur_produits
  DROP COLUMN IF EXISTS publication_type,
  DROP COLUMN IF EXISTS publie_automatiquement_le,
  DROP COLUMN IF EXISTS nb_signalements;
ALTER TABLE commercants_profils
  DROP COLUMN IF EXISTS surveillance;
DROP TABLE IF EXISTS reglages_publication;
