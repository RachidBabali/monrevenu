-- Retour arriere de 002 : supprime le journal et son contenu.
-- Exporter la table avant si elle contient des lignes a conserver :
--   SELECT * FROM journal_suppressions_compte;
-- Idempotent.

DROP TABLE IF EXISTS journal_suppressions_compte;
