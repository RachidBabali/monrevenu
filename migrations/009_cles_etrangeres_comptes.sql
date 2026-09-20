-- 009 (facultative, a appliquer apres 008) : empeche qu'une ligne financiere redevienne orpheline.
--
-- Les suppressions de compte du site anonymisent la ligne au lieu de l'effacer. Le seul moyen de
-- recreer des orphelins est une suppression a la main (phpMyAdmin, script exterieur). Ces trois
-- cles etrangeres en ON DELETE RESTRICT refusent alors la suppression au lieu de la laisser passer.
--
-- Prerequis : plus aucun orphelin (migration 008 appliquee, verif a 0). Sinon l'ALTER echoue,
-- sans rien changer. Idempotente : FOREIGN KEY IF NOT EXISTS ne fait rien la deuxieme fois.
-- Retour arriere : 009_cles_etrangeres_comptes_retour.sql

ALTER TABLE withdrawals
  ADD INDEX IF NOT EXISTS idx_user (user_id),
  ADD CONSTRAINT fk_withdrawals_user
      FOREIGN KEY IF NOT EXISTS (user_id) REFERENCES users_monrevenu (id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE transactions_monrevenu
  ADD INDEX IF NOT EXISTS idx_user (user_id),
  ADD CONSTRAINT fk_transactions_user
      FOREIGN KEY IF NOT EXISTS (user_id) REFERENCES users_monrevenu (id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE vendeur_ventes
  ADD INDEX IF NOT EXISTS idx_vendeur (vendeur_id),
  ADD CONSTRAINT fk_ventes_affilie
      FOREIGN KEY IF NOT EXISTS (vendeur_id) REFERENCES users_monrevenu (id) ON DELETE RESTRICT ON UPDATE CASCADE;
