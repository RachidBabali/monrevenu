-- 014 : score de coherence du pays (indicatif du numero, pays de l'IP, fuseau du navigateur).
-- Signal de segmentation et de support, PAS une preuve de localisation. Seul le resultat derive est conserve :
-- ni IP brute, ni coordonnees, ni fuseau brut.
-- Idempotente. Retour arriere : 014_coherence_pays_retour.sql
ALTER TABLE users_monrevenu
  ADD COLUMN IF NOT EXISTS geo_score ENUM('ELEVE','MOYEN','FAIBLE') NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS geo_pays_ip CHAR(2) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS geo_raisons VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS geo_evalue_le DATETIME NULL DEFAULT NULL;
