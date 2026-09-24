-- Retour arriere de 014.
ALTER TABLE users_monrevenu DROP COLUMN IF EXISTS geo_evalue_le, DROP COLUMN IF EXISTS geo_raisons, DROP COLUMN IF EXISTS geo_pays_ip, DROP COLUMN IF EXISTS geo_score;
