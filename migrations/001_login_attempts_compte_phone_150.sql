-- 001 : connexion par email de plus de 20 caracteres.
-- includs/login_handler.php enregistre les echecs par compte dans login_attempts_compte.phone,
-- avec l'email en minuscules quand on se connecte par email. La colonne fait varchar(20) en production :
-- au-dela, l'INSERT echoue (mode strict) et la connexion aboutit a index.php?error=serveur.
-- 150 caracteres, comme users_monrevenu.email. Collation de la table conservee.
-- Idempotente : peut etre relancee sans effet. Retour arriere : 001_login_attempts_compte_phone_150_retour.sql
-- A executer a la main, apres sauvegarde de la base. Ne rien executer automatiquement.

ALTER TABLE login_attempts_compte
  MODIFY phone VARCHAR(150) COLLATE utf8mb4_general_ci NOT NULL;

-- Controle : la colonne Type doit afficher varchar(150)
SHOW COLUMNS FROM login_attempts_compte LIKE 'phone';
