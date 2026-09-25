-- 015 VERIF : controles apres application de la migration 015.

-- 1. La table existe et a la bonne structure.
SHOW CREATE TABLE reset_password_attempts;

-- 2. Etat courant des compteurs (vide juste apres la migration).
SELECT ip, attempts, last_attempt FROM reset_password_attempts ORDER BY last_attempt DESC LIMIT 20;

-- 3. IP actuellement plafonnees (>= 5 envois dans les 30 dernieres minutes).
SELECT ip, attempts, last_attempt
FROM reset_password_attempts
WHERE attempts >= 5 AND last_attempt > (NOW() - INTERVAL 30 MINUTE);
