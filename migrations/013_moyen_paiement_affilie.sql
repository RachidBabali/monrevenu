-- 013 : moyen de paiement des commissions de l'affilie (paiement MANUEL : information pour l'administration).
--
-- affilie_moyens_paiement : l'operateur mobile money et le numero qui recoit les commissions. Une ligne par
-- compte pour l'instant (UNIQUE user_id) ; lever cette contrainte suffira pour plusieurs moyens par compte.
-- fournisseur_api et reference_externe restent NULL : reserves a une integration API future (aucune
-- integration n'est developpee ici).
-- withdrawals.operateur / numero_paiement : copie de l'operateur et du numero AU MOMENT de la demande,
-- pour que le paiement suive ce que l'affilie avait enregistre meme s'il change son profil ensuite.
--
-- Idempotente. Retour arriere : 013_moyen_paiement_affilie_retour.sql

CREATE TABLE IF NOT EXISTS affilie_moyens_paiement (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  marche CHAR(2) NOT NULL,
  operateur VARCHAR(40) NOT NULL,
  numero VARCHAR(20) NOT NULL,
  statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  fournisseur_api VARCHAR(40) NULL DEFAULT NULL,
  reference_externe VARCHAR(120) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user (user_id),
  CONSTRAINT fk_moyen_paiement_user FOREIGN KEY (user_id) REFERENCES users_monrevenu (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE withdrawals
  ADD COLUMN IF NOT EXISTS operateur VARCHAR(40) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS numero_paiement VARCHAR(20) NULL DEFAULT NULL;
