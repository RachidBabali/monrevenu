-- 004 : journal d'audit en ajout seulement, chaine de hachage, captures de sante,
-- colonnes de tracabilite des mouvements de solde. Idempotente.
-- Controle : 004_journal_audit_verif.sql. Retour arriere : 004_journal_audit_retour.sql

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  occurred_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  request_id CHAR(16) NOT NULL,
  actor_id INT NULL,
  actor_role VARCHAR(20) NULL,
  category VARCHAR(20) NOT NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id VARCHAR(64) NULL,
  result VARCHAR(10) NOT NULL DEFAULT 'ok',
  route VARCHAR(160) NULL,
  ip_prefixe VARCHAR(45) NULL COMMENT 'IP tronquee (/24 ou /48), comprise dans le hachage',
  ip VARCHAR(45) NULL COMMENT 'IP complete, hors hachage, effacee apres 90 jours',
  user_agent VARCHAR(255) NULL COMMENT 'Hors hachage',
  before_json MEDIUMTEXT NULL,
  after_json MEDIUMTEXT NULL,
  meta_json MEDIUMTEXT NULL,
  prev_hash CHAR(64) NOT NULL,
  row_hash CHAR(64) NOT NULL,
  KEY idx_time (occurred_at),
  KEY idx_actor (actor_id, occurred_at),
  KEY idx_cat (category, occurred_at),
  KEY idx_entity (entity_type, entity_id),
  KEY idx_action (action, occurred_at),
  KEY idx_ip (ip_prefixe, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_chain_head (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  last_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_hash CHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO audit_chain_head (id, last_id, last_hash) VALUES (1, 0, REPEAT('0', 64));

CREATE TABLE IF NOT EXISTS health_snapshots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  taken_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  kind VARCHAR(20) NOT NULL,
  status VARCHAR(10) NOT NULL,
  payload_json MEDIUMTEXT NULL,
  KEY idx_kind_time (kind, taken_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transactions_monrevenu
  ADD COLUMN IF NOT EXISTS balance_before DECIMAL(15,2) NULL,
  ADD COLUMN IF NOT EXISTS balance_after DECIMAL(15,2) NULL,
  ADD COLUMN IF NOT EXISTS actor_id INT NULL,
  ADD COLUMN IF NOT EXISTS audit_id BIGINT UNSIGNED NULL;
