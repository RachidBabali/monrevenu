-- INUTILE depuis le lot 2 (19/09/2026) : le credit manuel admin n'ecrit plus dans agent_commissions.
-- Ne pas appliquer. Conserve pour l'historique.
-- 003 : "Crediter le solde d'un membre" (admin/dashboard_admin.php, action_transfert_commission).
-- A appliquer SEULEMENT si l'option "correctif en base" est retenue (voir dev/CORRECTIFS_PROPOSES.md, 2.2).
-- L'INSERT du code omet operation_id (NOT NULL sans defaut) et ecrit operation_type = 'ajustement_admin',
-- absent de l'enum : en mode strict, l'ajustement echoue et le membre n'est pas credite.
-- Idempotente. Retour arriere : 003_agent_commissions_ajustement_admin_retour.sql

ALTER TABLE agent_commissions
  MODIFY COLUMN IF EXISTS operation_id INT(11) NOT NULL DEFAULT 0 COMMENT 'ID depot ou retrait, 0 pour un ajustement admin',
  MODIFY COLUMN IF EXISTS operation_type ENUM('depot','retrait','ajustement_admin') NOT NULL;

-- Controle
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agent_commissions' AND COLUMN_NAME IN ('operation_id', 'operation_type');
