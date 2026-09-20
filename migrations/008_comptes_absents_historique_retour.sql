-- Retour arriere de 008 : retire les comptes anonymes recrees et la table de trace.
-- Les lignes financieres redeviennent orphelines, comme avant. Aucune donnee reelle n'est perdue.
-- Idempotent : relance sans effet (le motif d'e-mail ne correspond a aucun compte reel).

DELETE FROM users_monrevenu
WHERE status = 'deleted'
  AND email = CONCAT('compte-absent-', id, '@monrevenu.invalid')
  AND password = 'connexion-impossible';

DROP TABLE IF EXISTS comptes_reconstruits;
