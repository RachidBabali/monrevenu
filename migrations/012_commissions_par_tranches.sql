-- 012 : commissions par tranches (supplement progressif sur le prix net du commercant).
--
-- commission_brackets : bareme progressif par marche (SN = FCFA, KM = KMF). Chaque tranche est
-- taxee a son propre taux ; borne_max NULL = derniere tranche ouverte. Seed : bareme FCFA, et
-- son equivalent KMF au taux fixe 100 FCFA = 75 KMF.
-- commission_settings : repartition affilie / plateforme, plancher, plafond et arrondi du supplement.
-- commission_history : qui a modifie quoi, quand, valeur avant / apres (audit interne).
-- vendeur_produits.prix_net : prix fixe par le commercant (NULL = ancien produit, ancien calcul).
-- vendeur_ventes.calcul_snapshot : copie figee du bareme et de la repartition utilises pour la
-- commande. Jamais recalculee.
--
-- Idempotente. Retour arriere : 012_commissions_par_tranches_retour.sql

CREATE TABLE IF NOT EXISTS commission_brackets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  marche CHAR(2) NOT NULL,
  ordre TINYINT UNSIGNED NOT NULL,
  borne_min DECIMAL(15,2) NOT NULL,
  borne_max DECIMAL(15,2) NULL,
  taux DECIMAL(5,2) NOT NULL,
  UNIQUE KEY uniq_marche_ordre (marche, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commission_settings (
  marche CHAR(2) NOT NULL PRIMARY KEY,
  part_affilie DECIMAL(5,2) NOT NULL DEFAULT 30.00,
  part_plateforme DECIMAL(5,2) NOT NULL DEFAULT 70.00,
  supplement_min DECIMAL(15,2) NOT NULL,
  supplement_max DECIMAL(15,2) NOT NULL,
  arrondi SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commission_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  marche CHAR(2) NOT NULL,
  cible ENUM('bareme','repartition','regles') NOT NULL,
  ancienne_valeur JSON NULL,
  nouvelle_valeur JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_marche_date (marche, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE vendeur_produits ADD COLUMN IF NOT EXISTS prix_net DECIMAL(15,2) NULL DEFAULT NULL;
ALTER TABLE vendeur_ventes   ADD COLUMN IF NOT EXISTS calcul_snapshot JSON NULL DEFAULT NULL;

INSERT IGNORE INTO commission_brackets (marche, ordre, borne_min, borne_max, taux) VALUES
 ('SN',1,0,5000,12),('SN',2,5000,25000,10),('SN',3,25000,100000,8),
 ('SN',4,100000,500000,6),('SN',5,500000,1000000,4),('SN',6,1000000,NULL,2),
 ('KM',1,0,3750,12),('KM',2,3750,18750,10),('KM',3,18750,75000,8),
 ('KM',4,75000,375000,6),('KM',5,375000,750000,4),('KM',6,750000,NULL,2);

INSERT IGNORE INTO commission_settings (marche, part_affilie, part_plateforme, supplement_min, supplement_max, arrondi) VALUES
 ('SN',30,70,100,50000,50),('KM',30,70,75,37500,50);
