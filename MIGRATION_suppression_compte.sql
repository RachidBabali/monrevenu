-- ============================================================
-- Migration à exécuter manuellement avant la mise en production
-- de la suppression de compte (page/profil.php > action_supprimer_compte).
-- Ne rien exécuter automatiquement : à relire et lancer vous-même.
-- ============================================================

-- Journal des suppressions de compte : ne contient volontairement AUCUNE
-- donnée personnelle (pas de nom, email ou téléphone), seulement l'id
-- numérique interne (déjà utilisé partout dans le projet comme référence
-- technique, ce n'est pas en soi une donnée personnelle) et le type de
-- confirmation utilisé.
CREATE TABLE IF NOT EXISTS journal_suppressions_compte (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    compte_id         INT NOT NULL,
    type_suppression  VARCHAR(30) NOT NULL, -- 'mot_de_passe' ou 'mot_cle_google'
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Aucune autre colonne/table n'est nécessaire : la suppression de compte
-- réutilise entièrement les colonnes déjà présentes sur users_monrevenu
-- (fullname, email, phone, password, google_id, verification_code,
-- code_expires_at, code_sent_at, whatsapp_verif_code, whatsapp_verif_expire_at,
-- whatsapp_verif_numero, reset_password_code, reset_password_expires_at,
-- status, is_active) — confirmées présentes par lecture du code existant.
