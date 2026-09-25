-- 015 : anti-abus sur l'envoi de code de reinitialisation de mot de passe.
--
-- mot_de_passe_oublie.php (demande initiale) et reinitialiser_mot_de_passe.php (bouton
-- "renvoyer le code") envoient un code a 6 chiffres par email. Sans plafond, un attaquant
-- qui connait une adresse peut inonder la victime de mails et saturer le journal d'audit.
-- Cette table sert de compteur d'envois PAR IP, lu/ecrit par includs/reset_throttle.php
-- (5 envois maximum par fenetre de 30 minutes ; voir les constantes du helper).
--
-- Le code PHP est "fail-open" si cette table n'existe pas : tant que la migration n'est
-- pas passee, la reinitialisation fonctionne comme avant (sans plafond). Le plafond
-- s'active des que la table est creee.
--
-- Idempotente (CREATE TABLE IF NOT EXISTS) : peut etre relancee sans effet.
-- A executer a la main, apres sauvegarde de la base. Ne rien executer automatiquement.
-- Retour arriere : 015_reset_password_anti_abus_retour.sql

CREATE TABLE IF NOT EXISTS reset_password_attempts (
  ip           VARCHAR(45) NOT NULL PRIMARY KEY COMMENT 'IP du demandeur (ipClient()), IPv4 ou IPv6',
  attempts     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'nombre d envois dans la fenetre courante',
  last_attempt DATETIME NOT NULL COMMENT 'horodatage du dernier envoi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Controle : la table doit exister
SHOW TABLES LIKE 'reset_password_attempts';
