-- 006 : espace commercants. Role commercant, profils de boutique, moderation des produits,
-- reglements manuels de la dette de commission, motif d'annulation d'une commande.
-- Les produits existants restent visibles : moderation = 'approuve' par defaut.
-- Idempotente. Controle : 006_espace_commercants_verif.sql. Retour : 006_espace_commercants_retour.sql

ALTER TABLE users_monrevenu
  MODIFY role ENUM('admin','agent','client','affilie','commercant') NOT NULL DEFAULT 'affilie';

CREATE TABLE IF NOT EXISTS commercants_profils (
  user_id INT NOT NULL PRIMARY KEY,
  nom_boutique VARCHAR(120) NOT NULL,
  ville VARCHAR(100) NULL,
  description TEXT NULL,
  statut ENUM('en_attente','valide','suspendu','refuse') NOT NULL DEFAULT 'en_attente',
  motif VARCHAR(255) NULL COMMENT 'Motif de refus ou de suspension, lisible par le commercant',
  confiance TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = produits publies sans validation',
  valide_par INT NULL,
  valide_le DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_statut (statut),
  CONSTRAINT fk_commercant_user FOREIGN KEY (user_id) REFERENCES users_monrevenu (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE vendeur_produits
  ADD COLUMN IF NOT EXISTS moderation ENUM('brouillon','en_attente','approuve','refuse') NOT NULL DEFAULT 'approuve',
  ADD COLUMN IF NOT EXISTS moderation_note VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS created_by INT NULL,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  ADD INDEX IF NOT EXISTS idx_catalogue (moderation, statut);

ALTER TABLE vendeur_ventes
  ADD COLUMN IF NOT EXISTS motif_annulation VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS commercant_reglements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  commercant_id INT NOT NULL,
  montant DECIMAL(15,2) NOT NULL,
  reference VARCHAR(100) NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_reference (reference),
  KEY idx_commercant (commercant_id),
  CONSTRAINT fk_reglement_commercant FOREIGN KEY (commercant_id) REFERENCES users_monrevenu (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
