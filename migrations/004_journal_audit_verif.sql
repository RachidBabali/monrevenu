-- Controles de 004, resultats attendus en commentaire.

-- 1 ligne : audit_log
SHOW TABLES LIKE 'audit_log';
-- 1 ligne : audit_chain_head
SHOW TABLES LIKE 'audit_chain_head';
-- 1 ligne : health_snapshots
SHOW TABLES LIKE 'health_snapshots';
-- 1 ligne : id 1, last_id 0 (ou plus si le journal a deja servi), last_hash de 64 caracteres
SELECT id, last_id, CHAR_LENGTH(last_hash) AS longueur FROM audit_chain_head;
-- 4 lignes : balance_before, balance_after, actor_id, audit_id
SHOW COLUMNS FROM transactions_monrevenu WHERE Field IN ('balance_before', 'balance_after', 'actor_id', 'audit_id');
-- 6 index (hors PRIMARY) : idx_time, idx_actor, idx_cat, idx_entity, idx_action, idx_ip
SHOW INDEX FROM audit_log WHERE Key_name <> 'PRIMARY' AND Seq_in_index = 1;
