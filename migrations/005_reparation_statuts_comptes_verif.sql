-- Controles de 005.

-- 0 attendu : aucun compte actif marque suspendu
SELECT COUNT(*) AS actifs_suspendus FROM users_monrevenu WHERE is_active = 1 AND status = 'suspended';
-- 0 attendu : aucun compte verifie desactive mais marque actif
SELECT COUNT(*) AS bloques_actifs FROM users_monrevenu WHERE is_active = 0 AND status = 'active' AND phone_verified = 1;
-- Liste des comptes repares (peut etre vide)
SELECT user_id, is_active_avant, status_avant, repare_le FROM reparation_statuts_005;
-- Pour information : inscriptions en attente de verification (non modifiees)
SELECT COUNT(*) AS en_attente_verification FROM users_monrevenu WHERE is_active = 0 AND phone_verified = 0 AND status <> 'deleted';
