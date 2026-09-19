-- Retour arriere de 006. ATTENTION : supprime les profils de boutique, les reglements et la moderation.
-- Les comptes commercants redeviennent des affilies (le role 'commercant' disparait de l'enum).
-- Les produits des commercants restent dans vendeur_produits : les suspendre avant si besoin :
--   UPDATE vendeur_produits SET statut = 'suspendu' WHERE vendeur_id IN (SELECT user_id FROM commercants_profils);
-- Idempotent.

UPDATE users_monrevenu SET role = 'affilie' WHERE role = 'commercant';
ALTER TABLE users_monrevenu
  MODIFY role ENUM('admin','agent','client','affilie') NOT NULL DEFAULT 'affilie';

DROP TABLE IF EXISTS commercant_reglements;
DROP TABLE IF EXISTS commercants_profils;

ALTER TABLE vendeur_produits
  DROP INDEX IF EXISTS idx_catalogue,
  DROP COLUMN IF EXISTS moderation,
  DROP COLUMN IF EXISTS moderation_note,
  DROP COLUMN IF EXISTS created_by,
  DROP COLUMN IF EXISTS updated_at;

ALTER TABLE vendeur_ventes
  DROP COLUMN IF EXISTS motif_annulation;
