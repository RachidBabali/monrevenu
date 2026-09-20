-- 007 : vide les corps de messages WhatsApp deja enregistres.
-- includs/whatsapp_verif_helpers.php n'ecrit plus whatsapp_webhook_log.message_body depuis le lot 3.
-- Les lignes plus anciennes contiennent le texte complet du message recu, qui n'a aucune utilite
-- (seuls le code extrait et les metadonnees servent) et qui peut contenir des donnees personnelles.
-- Idempotente : relancee, elle ne trouve plus rien a vider.
-- A executer a la main, apres sauvegarde de la base. Aucun retour arriere possible : le texte
-- efface n'est recuperable que depuis une sauvegarde (voir 007_..._retour.sql).

UPDATE whatsapp_webhook_log SET message_body = NULL WHERE message_body IS NOT NULL;
