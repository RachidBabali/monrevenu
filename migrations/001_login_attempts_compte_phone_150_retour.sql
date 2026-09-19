-- Retour arriere de 001. Les compteurs d'echecs dont la cle depasse 20 caracteres (emails)
-- sont supprimes : ce ne sont que des compteurs temporaires de tentatives de connexion.
-- Apres ce retour, la connexion par email de plus de 20 caracteres echoue de nouveau.
-- Idempotent.

DELETE FROM login_attempts_compte WHERE CHAR_LENGTH(phone) > 20;

ALTER TABLE login_attempts_compte
  MODIFY phone VARCHAR(20) COLLATE utf8mb4_general_ci NOT NULL;

-- Controle : la colonne Type doit afficher varchar(20)
SHOW COLUMNS FROM login_attempts_compte LIKE 'phone';
