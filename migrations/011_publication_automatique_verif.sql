-- Verification de 011.
SELECT * FROM reglages_publication WHERE id = 1;
SHOW COLUMNS FROM commercants_profils LIKE 'surveillance';
SHOW COLUMNS FROM vendeur_produits LIKE 'publication_type';
SHOW COLUMNS FROM vendeur_produits LIKE 'publie_automatiquement_le';
SHOW COLUMNS FROM vendeur_produits LIKE 'nb_signalements';
SHOW CREATE TABLE produit_signalements;

-- Repartition apres quelques jours d'utilisation : combien de produits publies automatiquement.
SELECT publication_type, COUNT(*) AS n FROM vendeur_produits GROUP BY publication_type;
