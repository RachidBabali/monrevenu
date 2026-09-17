-- ============================================================
-- Migration à exécuter manuellement avant la mise en production
-- du webhook WhatsApp entrant (webhook_whatsapp.php,
-- includs/whatsapp_verif_helpers.php, includs/regenerer_code_whatsapp.php).
-- Ne rien exécuter automatiquement : à relire et lancer vous-même.
--
-- Avant de lancer : exécuter `DESCRIBE users_monrevenu;` et `SHOW TABLES
-- LIKE 'whatsapp_webhook_log';` pour voir ce qui existe déjà. `phone_verified`
-- est déjà utilisé par login_handler.php / register_handler.php / verification.php
-- et existe donc très probablement déjà — retirer sa ligne ci-dessous si c'est
-- le cas (ALTER TABLE ... ADD COLUMN échoue si la colonne existe déjà).
-- ============================================================

ALTER TABLE users_monrevenu
    ADD COLUMN phone_verified TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN whatsapp_verif_code VARCHAR(6) DEFAULT NULL,
    ADD COLUMN whatsapp_verif_expire_at DATETIME DEFAULT NULL,
    ADD COLUMN whatsapp_verif_numero VARCHAR(20) DEFAULT NULL;

-- Journal des messages entrants du webhook (audit + anti-abus, voir
-- validerCodeWhatsapp() dans includs/whatsapp_verif_helpers.php).
CREATE TABLE IF NOT EXISTS whatsapp_webhook_log (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    wa_from           VARCHAR(20) NOT NULL,
    message_body      TEXT,
    code_extrait      VARCHAR(6) DEFAULT NULL,
    matched_user_id   INT DEFAULT NULL,
    statut            VARCHAR(20) NOT NULL, -- 'valide' | 'code_inconnu' | 'code_expire' | 'ignore'
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wa_from_created (wa_from, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
