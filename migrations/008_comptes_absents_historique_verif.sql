-- Verification de 008. Resultats attendus : les trois compteurs a 0,
-- et une ligne par compte reconstruit dans comptes_reconstruits.

SELECT COUNT(*) AS retraits_sans_compte
FROM withdrawals w LEFT JOIN users_monrevenu u ON u.id = w.user_id WHERE u.id IS NULL;

SELECT COUNT(*) AS transactions_sans_compte
FROM transactions_monrevenu t LEFT JOIN users_monrevenu u ON u.id = t.user_id WHERE u.id IS NULL;

SELECT COUNT(*) AS ventes_sans_affilie
FROM vendeur_ventes v LEFT JOIN users_monrevenu u ON u.id = v.vendeur_id WHERE u.id IS NULL;

SELECT * FROM comptes_reconstruits ORDER BY compte_id;

-- Aucun de ces comptes ne doit pouvoir se connecter ni apparaitre dans les listes actives.
SELECT id, fullname, status, is_active, balance FROM users_monrevenu WHERE status = 'deleted'
  AND email LIKE 'compte-absent-%@monrevenu.invalid' ORDER BY id;
