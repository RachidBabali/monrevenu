-- 010 : devise explicite sur les lignes qui portent un montant.
--
-- MonRevenu sert deux marches avec deux monnaies : le Senegal en franc CFA (XOF, affiche FCFA)
-- et les Comores en franc comorien (KMF). Les deux sont arrimees a l'euro a taux fixe, mais rien
-- n'est jamais converti : chaque montant reste dans la devise du marche de son proprietaire.
--
-- La colonne est NULL par defaut et l'application la renseigne pour toute nouvelle ligne.
-- Les lignes anterieures restent a NULL et sont lues avec la devise du marche de leur
-- proprietaire (pays du compte, sinon indicatif de son numero, sinon marche par defaut).
-- Aucune donnee existante n'est modifiee ici : voir la note sur l'hypothese FCFA dans
-- dev/lot3/DEPLOIEMENT_G.md, a appliquer separement et seulement si le controle est vert.
--
-- Idempotente (ADD COLUMN IF NOT EXISTS). Retour arriere : 010_devise_par_ligne_retour.sql

ALTER TABLE vendeur_produits       ADD COLUMN IF NOT EXISTS devise CHAR(3) DEFAULT NULL COMMENT 'XOF ou KMF, renseignee par l application';
ALTER TABLE vendeur_ventes         ADD COLUMN IF NOT EXISTS devise CHAR(3) DEFAULT NULL COMMENT 'XOF ou KMF, renseignee par l application';
ALTER TABLE transactions_monrevenu ADD COLUMN IF NOT EXISTS devise CHAR(3) DEFAULT NULL COMMENT 'XOF ou KMF, renseignee par l application';
ALTER TABLE withdrawals            ADD COLUMN IF NOT EXISTS devise CHAR(3) DEFAULT NULL COMMENT 'XOF ou KMF, renseignee par l application';
