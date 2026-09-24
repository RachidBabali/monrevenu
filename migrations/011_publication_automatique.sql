-- 011 : publication automatique des commercants (bloc H).
--
-- reglages_publication : ligne unique (id = 1), le mode et les seuils lus par
-- includs/reglages_publication.php. Idempotent : la ligne par defaut n'est inseree
-- que si elle n'existe pas encore.
--
-- commercants_profils.surveillance : "a surveiller", force la validation manuelle des
-- produits de ce commercant quel que soit le mode global (distinct de "confiance", qui
-- publie sans controle).
--
-- vendeur_produits.publication_type / publie_automatiquement_le : d'ou vient l'etat
-- "approuve" actuel (une decision humaine ou automatique), pour la file "a revoir".
-- vendeur_produits.nb_signalements : compteur denormalise, mis a jour par
-- includs/signalement.php, jamais recalcule a la volee dans les pages de liste.
--
-- produit_signalements : un signalement par affilie et par produit (un affilie ne peut
-- signaler deux fois le meme produit).
--
-- Idempotente (ADD COLUMN IF NOT EXISTS, CREATE TABLE IF NOT EXISTS).
-- Retour arriere : 011_publication_automatique_retour.sql

CREATE TABLE IF NOT EXISTS reglages_publication (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  mode ENUM('manuelle','automatique_controles','automatique_confiance') NOT NULL DEFAULT 'automatique_controles',
  premiers_produits_a_valider TINYINT UNSIGNED NOT NULL DEFAULT 0,
  prix_min_xof DECIMAL(15,2) NOT NULL DEFAULT 100.00,
  prix_max_xof DECIMAL(15,2) NOT NULL DEFAULT 500000.00,
  prix_min_kmf DECIMAL(15,2) NOT NULL DEFAULT 100.00,
  prix_max_kmf DECIMAL(15,2) NOT NULL DEFAULT 375000.00,
  seuil_signalements TINYINT UNSIGNED NOT NULL DEFAULT 3,
  mots_interdits TEXT NULL COMMENT 'Un mot ou une expression par ligne, en minuscules',
  updated_by INT NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_reglages_publication_admin FOREIGN KEY (updated_by) REFERENCES users_monrevenu (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO reglages_publication (id, mode, premiers_produits_a_valider, mots_interdits)
SELECT 1, 'automatique_controles', 0,
  'arme\nmunition\ndrogue\nstupefiant\ncontrefacon\nfaux document\ncontenu adulte\npornograph\nespece protegee\nivoire\nperime\nvole\nvolee\nalcool\nvin\nbiere\nwhisky\ntabac\ncigarette\nchicha'
WHERE NOT EXISTS (SELECT 1 FROM reglages_publication WHERE id = 1);

ALTER TABLE commercants_profils
  ADD COLUMN IF NOT EXISTS surveillance TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = validation manuelle systematique de ses produits';

ALTER TABLE vendeur_produits
  ADD COLUMN IF NOT EXISTS publication_type ENUM('manuel','automatique') NOT NULL DEFAULT 'manuel',
  ADD COLUMN IF NOT EXISTS publie_automatiquement_le DATETIME NULL,
  ADD COLUMN IF NOT EXISTS nb_signalements INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS produit_signalements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  produit_id INT NOT NULL,
  signale_par INT NOT NULL,
  motif VARCHAR(255) NULL,
  statut ENUM('ouvert','traite') NOT NULL DEFAULT 'ouvert',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  traite_par INT NULL,
  traite_le DATETIME NULL,
  UNIQUE KEY uniq_signalement (produit_id, signale_par),
  KEY idx_produit (produit_id),
  CONSTRAINT fk_signalement_produit FOREIGN KEY (produit_id) REFERENCES vendeur_produits (id) ON DELETE CASCADE,
  CONSTRAINT fk_signalement_affilie FOREIGN KEY (signale_par) REFERENCES users_monrevenu (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
