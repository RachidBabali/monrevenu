-- Retour arriere de 004. ATTENTION : supprime le journal d'audit et les captures de sante.
-- Exporter audit_log avant (phpMyAdmin, Exporter) si le journal a deja servi.
-- Les colonnes ajoutees a transactions_monrevenu sont retirees : les soldes avant et apres
-- des mouvements enregistres depuis 004 sont perdus (les montants restent). Idempotent.

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS audit_chain_head;
DROP TABLE IF EXISTS health_snapshots;

ALTER TABLE transactions_monrevenu
  DROP COLUMN IF EXISTS balance_before,
  DROP COLUMN IF EXISTS balance_after,
  DROP COLUMN IF EXISTS actor_id,
  DROP COLUMN IF EXISTS audit_id;
