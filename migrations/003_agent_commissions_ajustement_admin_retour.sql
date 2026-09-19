-- Retour arriere de 003. Les lignes 'ajustement_admin' doivent disparaitre avant de retirer la valeur
-- de l'enum (sinon echec en mode strict). Ces lignes ne sont relues par aucun code : les ajustements
-- restent traces dans transactions_monrevenu (type commission) et dans les messages.
-- Exporter avant si besoin : SELECT * FROM agent_commissions WHERE operation_type = 'ajustement_admin';
-- Apres ce retour, "Crediter le solde" echoue de nouveau. Idempotent.

DELETE FROM agent_commissions WHERE operation_type = 'ajustement_admin';

ALTER TABLE agent_commissions
  MODIFY COLUMN IF EXISTS operation_id INT(11) NOT NULL COMMENT 'ID depot ou retrait',
  MODIFY COLUMN IF EXISTS operation_type ENUM('depot','retrait') NOT NULL;
