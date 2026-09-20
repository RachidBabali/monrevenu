-- Verification de 007. Resultat attendu : restant = 0.

SELECT COUNT(*) AS restant FROM whatsapp_webhook_log WHERE message_body IS NOT NULL;

-- Pour memoire, la colonne existe toujours et reste vide (aucun code ne l'ecrit) :
SHOW COLUMNS FROM whatsapp_webhook_log LIKE 'message_body';
