-- Retour arriere de 005 : remet le statut d'avant la reparation, puis supprime la table de sauvegarde.
-- Attention : un compte bloque ou debloque par l'administration depuis 005 reprendrait son ancien statut.
-- Idempotent : la table est recreee vide si elle n'existe plus, la mise a jour ne touche alors rien.
CREATE TABLE IF NOT EXISTS reparation_statuts_005 (
  user_id INT NOT NULL PRIMARY KEY,
  is_active_avant TINYINT(1) NULL,
  status_avant VARCHAR(20) NULL,
  repare_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE users_monrevenu u
  JOIN reparation_statuts_005 r ON r.user_id = u.id
  SET u.status = r.status_avant;

DROP TABLE IF EXISTS reparation_statuts_005;
