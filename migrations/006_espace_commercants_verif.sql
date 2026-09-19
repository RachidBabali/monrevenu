-- Controles de 006.

-- Type attendu : enum('admin','agent','client','affilie','commercant')
SHOW COLUMNS FROM users_monrevenu LIKE 'role';
-- 1 ligne chacune
SHOW TABLES LIKE 'commercants_profils';
SHOW TABLES LIKE 'commercant_reglements';
-- 4 lignes : moderation, moderation_note, created_by, updated_at
SHOW COLUMNS FROM vendeur_produits WHERE Field IN ('moderation', 'moderation_note', 'created_by', 'updated_at');
-- 1 ligne : motif_annulation
SHOW COLUMNS FROM vendeur_ventes LIKE 'motif_annulation';
-- Tous les produits existants doivent etre 'approuve' : une seule ligne, moderation = approuve
SELECT moderation, COUNT(*) AS produits FROM vendeur_produits GROUP BY moderation;
