-- 015 RETOUR : supprime la table d'anti-abus de reinitialisation de mot de passe.
-- Le code PHP (includs/reset_throttle.php) est fail-open : une fois la table supprimee,
-- la reinitialisation continue de fonctionner, simplement sans plafond d'envois.
-- A executer a la main, apres sauvegarde de la base.

DROP TABLE IF EXISTS reset_password_attempts;
