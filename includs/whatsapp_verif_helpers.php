<?php
/**
 * Helpers pour la vérification téléphone "inversée" : l'utilisateur envoie un
 * code sur WhatsApp au numéro business, un webhook le reçoit et débloque le compte.
 *
 * Hypothèses (à adapter si différent chez vous) :
 * - $pdo est une connexion PDO déjà ouverte (comme dans le reste du projet).
 * - La table users_monrevenu a les colonnes ajoutées par migration_whatsapp_verif_inversee.sql.
 * - Les préfixes autorisés sont Comores (269) et Sénégal (221), à ajuster si besoin.
 */

const WHATSAPP_VERIF_DUREE_MINUTES = 5;
const WHATSAPP_VERIF_PREFIXES_AUTORISES = ['269', '221']; // KM, SN
const WHATSAPP_VERIF_REGEN_MIN_INTERVAL_SECONDES = 10; // anti-spam sur le bouton "régénérer"

/**
 * Génère (ou renvoie le code encore valide) pour un utilisateur non vérifié.
 * Évite de régénérer un nouveau code à chaque chargement du dashboard.
 *
 * @param bool $forcer Si true, ignore le code encore valide et en génère un nouveau
 *                      (utilisé par le bouton "régénérer"). Un petit throttle empêche
 *                      quand même de spammer le bouton (voir REGEN_MIN_INTERVAL).
 * @return array{code: string, expire_at: string, regenere: bool}
 */
function genererOuRecupererCodeVerificationWhatsapp(PDO $pdo, int $userId, bool $forcer = false): array
{
    $stmt = $pdo->prepare(
        "SELECT whatsapp_verif_code, whatsapp_verif_expire_at
         FROM users_monrevenu WHERE id = ? AND phone_verified = 0"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $codeEncoreValide = $row && $row['whatsapp_verif_code'] && strtotime($row['whatsapp_verif_expire_at']) > time();

    if ($codeEncoreValide && !$forcer) {
        return ['code' => $row['whatsapp_verif_code'], 'expire_at' => $row['whatsapp_verif_expire_at'], 'regenere' => false];
    }

    if ($forcer && $codeEncoreValide) {
        // Le code a été (ré)généré il y a moins de X secondes ? on bloque le spam de clics.
        $genereDepuis = (WHATSAPP_VERIF_DUREE_MINUTES * 60) - (strtotime($row['whatsapp_verif_expire_at']) - time());
        if ($genereDepuis < WHATSAPP_VERIF_REGEN_MIN_INTERVAL_SECONDES) {
            return ['code' => $row['whatsapp_verif_code'], 'expire_at' => $row['whatsapp_verif_expire_at'], 'regenere' => false];
        }
    }

    // Alphabet sans caractères ambigus (pas de 0/O, 1/I/L)
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    $expireAt = (new DateTime())->modify('+' . WHATSAPP_VERIF_DUREE_MINUTES . ' minutes')->format('Y-m-d H:i:s');

    $upd = $pdo->prepare(
        "UPDATE users_monrevenu
         SET whatsapp_verif_code = ?, whatsapp_verif_expire_at = ?
         WHERE id = ? AND phone_verified = 0"
    );
    $upd->execute([$code, $expireAt, $userId]);

    require_once __DIR__ . '/audit.php';
    auditInfo($pdo, ['category' => 'auth', 'action' => 'whatsapp_code_genere', 'entity_type' => 'utilisateur', 'entity_id' => $userId,
        'meta' => ['renouvele' => $forcer]]);

    return ['code' => $code, 'expire_at' => $expireAt, 'regenere' => $forcer];
}

/**
 * Normalise un numéro reçu de Meta (format wa_id, ex: "2693312345") en gardant
 * uniquement les chiffres, pour comparaison/stockage cohérent.
 */
function normaliserNumeroWhatsapp(string $numero): string
{
    return preg_replace('/\D/', '', $numero);
}

/**
 * Tente de valider un message entrant contre les codes en attente.
 * Journalise systématiquement dans whatsapp_webhook_log (audit + anti-abus).
 *
 * @return array{success: bool, user_id: ?int, statut: string}
 */
function validerCodeWhatsapp(PDO $pdo, string $texteMessage, string $numeroExpediteur): array
{
    $numero = normaliserNumeroWhatsapp($numeroExpediteur);

    // Le code peut être entouré de texte ("VERIF AB12CD" ou juste "AB12CD")
    preg_match('/\b([A-Z0-9]{6})\b/i', strtoupper(trim($texteMessage)), $matches);
    $code = $matches[1] ?? null;

    $statut = 'ignore';
    $matchedUserId = null;

    if ($code) {
        // Anti-abus simple : max 20 tentatives / heure / numéro
        $tent = $pdo->prepare(
            "SELECT COUNT(*) FROM whatsapp_webhook_log
             WHERE wa_from = ? AND created_at > (NOW() - INTERVAL 1 HOUR)"
        );
        $tent->execute([$numero]);
        $tropDeTentatives = (int) $tent->fetchColumn() >= 20;

        if ($tropDeTentatives) {
            $statut = 'ignore';
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, whatsapp_verif_expire_at FROM users_monrevenu
                 WHERE whatsapp_verif_code = ? AND phone_verified = 0"
            );
            $stmt->execute([$code]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $statut = 'code_inconnu';
            } elseif (strtotime($user['whatsapp_verif_expire_at']) < time()) {
                $statut = 'code_expire';
            } else {
                // Optionnel : n'accepter que les préfixes KM/SN. Commenter si trop strict
                // (ex: diaspora avec un numéro étranger qui reste valide comme identité).
                // $prefixeOk = false;
                // foreach (WHATSAPP_VERIF_PREFIXES_AUTORISES as $p) {
                //     if (str_starts_with($numero, $p)) { $prefixeOk = true; break; }
                // }
                // if (!$prefixeOk) { $statut = 'code_inconnu'; }

                $upd = $pdo->prepare(
                    "UPDATE users_monrevenu
                     SET phone_verified = 1,
                         whatsapp_verif_numero = ?,
                         whatsapp_verif_code = NULL,
                         whatsapp_verif_expire_at = NULL
                     WHERE id = ?"
                );
                $upd->execute([$numero, $user['id']]);

                $statut = 'valide';
                $matchedUserId = (int) $user['id'];
            }
        }
    }

    // Le texte du message n'est jamais conserve : seuls le code extrait et les metadonnees
    // (numero, utilisateur reconnu, statut, horodatage) servent au controle et a l'anti-abus.
    // La colonne message_body n'est plus ecrite du tout ; la migration 007 vide les anciennes valeurs.
    $log = $pdo->prepare(
        "INSERT INTO whatsapp_webhook_log (wa_from, code_extrait, matched_user_id, statut)
         VALUES (?, ?, ?, ?)"
    );
    $log->execute([$numero, $code, $matchedUserId, $statut]);

    require_once __DIR__ . '/audit.php';
    auditInfo($pdo, ['category' => 'auth', 'action' => $statut === 'valide' ? 'verification_whatsapp' : 'whatsapp_code_' . $statut,
        'result' => $statut === 'valide' ? 'ok' : 'refus', 'actor_id' => $matchedUserId, 'actor_role' => null,
        'entity_type' => $matchedUserId ? 'utilisateur' : null, 'entity_id' => $matchedUserId, 'meta' => ['wa_from' => $numero, 'statut' => $statut]]);

    return [
        'success' => $statut === 'valide',
        'user_id' => $matchedUserId,
        'statut'  => $statut,
    ];
}