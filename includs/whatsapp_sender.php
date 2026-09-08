<?php
/**
 * whatsapp_sender.php — Envoi de codes de vérification via WhatsApp
 * ============================================================
 * Utilise l'API officielle WhatsApp Cloud (Meta). Aucun abonnement mensuel :
 * facturation à l'usage, avec un quota gratuit mensuel pour les messages
 * d'authentification dans la plupart des pays.
 *
 * ⚠️ CONFIGURATION REQUISE (à faire une seule fois, sur developers.facebook.com) :
 *
 * 1. Créer un compte développeur Meta : https://developers.facebook.com
 * 2. Créer une "App" de type Business, puis ajouter le produit "WhatsApp"
 * 3. Dans WhatsApp > Configuration de l'API, récupérer :
 *    - Le "Phone Number ID" (identifiant du numéro d'expéditeur)
 *    - Un "Access Token" (jeton temporaire pour tester, puis un jeton
 *      permanent une fois l'app passée en mode production)
 * 4. Créer un modèle de message ("Message Templates") de catégorie
 *    "Authentication" avec un code à variable, ex: "{{1}} est votre
 *    code de vérification MonRevenu." Attendre son approbation par Meta
 *    (généralement quelques minutes à quelques heures).
 * 5. Remplacer les 3 constantes ci-dessous par vos vraies valeurs.
 */

define('WHATSAPP_PHONE_NUMBER_ID', 'REMPLACER_PAR_VOTRE_PHONE_NUMBER_ID');
define('WHATSAPP_ACCESS_TOKEN', 'REMPLACER_PAR_VOTRE_ACCESS_TOKEN');
define('WHATSAPP_TEMPLATE_NAME', 'REMPLACER_PAR_LE_NOM_DE_VOTRE_MODELE'); // ex: 'code_verification'
define('WHATSAPP_TEMPLATE_LANGUE', 'fr'); // doit correspondre à la langue du modèle approuvé

/**
 * Envoie un code de vérification par WhatsApp via l'API Cloud de Meta.
 *
 * @param string $telephone Numéro au format international sans "+" (ex: 2693212345)
 * @param string $code      Code à 6 chiffres à transmettre
 * @return array ['ok' => bool, 'erreur' => string|null]
 */
function envoyerCodeWhatsApp(string $telephone, string $code): array
{
    // Garde-fou : configuration non renseignée
    if (WHATSAPP_PHONE_NUMBER_ID === '1266202099909859') {
        error_log("[whatsapp_sender] Configuration manquante — code non envoyé (mode test). Code pour {$telephone} : {$code}");
        return ['ok' => false, 'erreur' => 'Service WhatsApp non configuré.'];
    }

    $url = "https://graph.facebook.com/v20.0/" . WHATSAPP_PHONE_NUMBER_ID . "/messages";

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $telephone,
        'type' => 'template',
        'template' => [
            'name' => WHATSAPP_TEMPLATE_NAME,
            'language' => ['code' => WHATSAPP_TEMPLATE_LANGUE],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $code]
                    ]
                ],
                // Bouton "Copier le code" si le modèle en contient un (optionnel selon le modèle)
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $code]
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $reponse = curl_exec($ch);
    $code_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur_curl = curl_error($ch);
    curl_close($ch);

    if ($erreur_curl) {
        error_log("[whatsapp_sender] Erreur cURL : {$erreur_curl}");
        return ['ok' => false, 'erreur' => 'Impossible de joindre WhatsApp pour le moment.'];
    }

    if ($code_http !== 200) {
        error_log("[whatsapp_sender] Réponse API WhatsApp (HTTP {$code_http}) : {$reponse}");
        return ['ok' => false, 'erreur' => 'Échec de l\'envoi du code via WhatsApp.'];
    }

    return ['ok' => true, 'erreur' => null];
}