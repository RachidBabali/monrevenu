-- 008 : lignes financieres qui pointent vers un compte absent.
--
-- Constat en production : des retraits (withdrawals) portent un user_id qui n'existe plus dans
-- users_monrevenu. Aucun code du depot ne supprime une ligne de users_monrevenu : la suppression
-- de compte (page/profil.php) et la suppression par l'administration anonymisent sur place
-- (status = 'deleted', is_active = 0). Ces lignes viennent donc d'effacements faits a la main
-- ou d'une reprise de donnees anterieure.
--
-- Regle voulue : l'historique financier est conserve, anonymise, jamais orphelin sans trace.
-- Cette migration recree un compte anonyme pour chaque identifiant encore reference par une
-- ligne financiere. Rien n'est invente : seuls l'identifiant, un nom neutre et un etat supprime
-- sont poses ; le mot de passe est une valeur qui ne peut correspondre a aucun mot de passe
-- (password_verify echoue toujours), donc aucune connexion n'est possible.
--
-- Idempotente : relancee, INSERT ... SELECT ne trouve plus aucun identifiant manquant.
-- Retour arriere : 008_comptes_absents_historique_retour.sql
-- A executer a la main, apres sauvegarde de la base. Verification : 008_..._verif.sql

-- 1. Trace permanente : quels identifiants ont ete reconstruits, et d'ou ils venaient.
CREATE TABLE IF NOT EXISTS comptes_reconstruits (
    compte_id   INT NOT NULL PRIMARY KEY,
    origine     VARCHAR(40) NOT NULL COMMENT 'table qui referencait encore ce compte',
    lignes      INT NOT NULL DEFAULT 0 COMMENT 'nombre de lignes concernees a la reconstruction',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO comptes_reconstruits (compte_id, origine, lignes)
SELECT w.user_id, 'withdrawals', COUNT(*)
FROM withdrawals w
LEFT JOIN users_monrevenu u ON u.id = w.user_id
WHERE u.id IS NULL
GROUP BY w.user_id;

INSERT IGNORE INTO comptes_reconstruits (compte_id, origine, lignes)
SELECT t.user_id, 'transactions_monrevenu', COUNT(*)
FROM transactions_monrevenu t
LEFT JOIN users_monrevenu u ON u.id = t.user_id
WHERE u.id IS NULL
GROUP BY t.user_id;

INSERT IGNORE INTO comptes_reconstruits (compte_id, origine, lignes)
SELECT v.vendeur_id, 'vendeur_ventes', COUNT(*)
FROM vendeur_ventes v
LEFT JOIN users_monrevenu u ON u.id = v.vendeur_id
WHERE u.id IS NULL
GROUP BY v.vendeur_id;

-- 2. Comptes anonymes, un par identifiant manquant.
INSERT IGNORE INTO users_monrevenu
    (id, fullname, email, phone, password, role, balance, is_active, status, phone_verified, verification_method)
SELECT c.compte_id,
       'Compte supprimé',
       CONCAT('compte-absent-', c.compte_id, '@monrevenu.invalid'),
       CONCAT('absent-', c.compte_id),
       'connexion-impossible',
       'affilie', 0.00, 0, 'deleted', 0, 'email'
FROM comptes_reconstruits c
LEFT JOIN users_monrevenu u ON u.id = c.compte_id
WHERE u.id IS NULL;
