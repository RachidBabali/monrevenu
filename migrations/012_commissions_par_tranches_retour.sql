-- Retour arriere de 012 (supprime aussi les snapshots des commandes : a n'utiliser qu'avant mise en service).
ALTER TABLE vendeur_ventes DROP COLUMN IF EXISTS calcul_snapshot;
ALTER TABLE vendeur_produits DROP COLUMN IF EXISTS prix_net;
DROP TABLE IF EXISTS commission_history;
DROP TABLE IF EXISTS commission_settings;
DROP TABLE IF EXISTS commission_brackets;
