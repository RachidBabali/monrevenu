-- 002 : journal des suppressions de compte (page/profil.php, action_supprimer_compte).
-- La table est absente de la production : la suppression de compte fonctionne, mais l'INSERT
-- dans le journal echoue (erreur interceptee) et aucune trace n'est conservee.
-- Meme structure que MIGRATION_suppression_compte.sql (jamais appliquee), collation alignee
-- sur users_monrevenu. Aucune donnee personnelle : seulement l'identifiant interne.
-- Idempotente. Retour arriere : 002_journal_suppressions_compte_retour.sql

CREATE TABLE IF NOT EXISTS journal_suppressions_compte (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    compte_id         INT NOT NULL,
    type_suppression  VARCHAR(30) NOT NULL COMMENT 'mot_de_passe ou mot_cle_google',
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Controle : doit afficher une ligne journal_suppressions_compte
SHOW TABLES LIKE 'journal_suppressions_compte';
