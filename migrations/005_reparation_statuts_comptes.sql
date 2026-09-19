-- 005 : realigne users_monrevenu.status sur is_active pour les comptes touches par l'ancien
-- bouton Bloquer / Debloquer de l'administration (corrige dans le code du lot 2).
--   is_active = 1 et status = 'suspended'                      -> status = 'active'
--   is_active = 0, status = 'active' et telephone verifie      -> status = 'suspended'
-- Les comptes jamais verifies (is_active = 0, phone_verified = 0) ne sont pas touches :
-- c'est l'etat normal d'une inscription en attente. Les comptes 'deleted' ne sont pas touches.
-- L'etat d'avant est copie dans reparation_statuts_005 (retour arriere possible).
-- Idempotente : un second passage ne trouve plus rien a modifier.
-- A appliquer APRES le deploiement du nouveau admin/dashboard_admin.php.

CREATE TABLE IF NOT EXISTS reparation_statuts_005 (
  user_id INT NOT NULL PRIMARY KEY,
  is_active_avant TINYINT(1) NULL,
  status_avant VARCHAR(20) NULL,
  repare_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO reparation_statuts_005 (user_id, is_active_avant, status_avant)
  SELECT id, is_active, status FROM users_monrevenu
  WHERE (is_active = 1 AND status = 'suspended')
     OR (is_active = 0 AND status = 'active' AND phone_verified = 1);

UPDATE users_monrevenu SET status = 'active'
  WHERE is_active = 1 AND status = 'suspended';

UPDATE users_monrevenu SET status = 'suspended'
  WHERE is_active = 0 AND status = 'active' AND phone_verified = 1;
