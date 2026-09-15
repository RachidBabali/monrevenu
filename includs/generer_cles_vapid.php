<?php
/**
 * À exécuter UNE SEULE FOIS, en local, après `composer install` :
 *   php includs/generer_cles_vapid.php
 *
 * Copie ensuite les deux clés affichées dans le .env du serveur
 * (VAPID_PUBLIC_KEY et VAPID_PRIVATE_KEY), puis supprime ce fichier ou
 * ne le laisse jamais accessible publiquement sur le serveur web.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$cles = VAPID::createVapidKeys();

echo "Ajoute ces lignes à ton .env (serveur ET local) :\n\n";
echo "VAPID_PUBLIC_KEY=" . $cles['publicKey'] . "\n";
echo "VAPID_PRIVATE_KEY=" . $cles['privateKey'] . "\n";
echo "VAPID_SUBJECT=mailto:contact@monrevenu.xyz\n\n";
echo "La clé publique doit aussi être collée dans js/app.js (VAPID_PUBLIC_KEY côté client).\n";