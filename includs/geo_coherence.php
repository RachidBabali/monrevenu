<?php
/**
 * includs/geo_coherence.php : score de coherence entre le pays du numero de telephone, le pays de l'IP
 * (en-tete Cloudflare CF-IPCountry, gratuit) et le fuseau horaire du navigateur (cookie mr_tz).
 *
 * C'est un SIGNAL de segmentation et de support, pas une preuve de localisation : un VPN, un voyage ou
 * un reglage de telephone le contournent, et le fuseau vient du client (falsifiable). Il n'accorde ni ne
 * retire aucun acces : il marque seulement le compte "a revoir" dans l'administration (score FAIBLE).
 * Seul le resultat derive est conserve (score, pays de l'IP, raisons), jamais l'IP ni le fuseau bruts.
 * Regles : ELEVE = indicatif = pays IP = pays du fuseau ; MOYEN = indicatif = pays IP, fuseau incoherent
 * (ou IP inconnue avec fuseau concordant) ; FAIBLE = indicatif different du pays IP (ou indicatif non
 * pris en charge, ou IP inconnue et fuseau non concordant).
 */
require_once __DIR__ . '/config_marche.php';
require_once __DIR__ . '/geoip.php';
require_once __DIR__ . '/audit.php';

if (!function_exists('scoreCoherencePays')) {
    /** @return array{0:string,1:string} [score, raison] ; fonction pure. */
    function scoreCoherencePays(string $telephone, ?string $paysIp, ?string $fuseau): array
    {
        $fuseaux = ['Indian/Comoro' => 'KM', 'Africa/Dakar' => 'SN'];
        $pays = marcheDeNumero(preg_replace('/\D/', '', $telephone));
        $paysIp = ($paysIp && !in_array(strtoupper($paysIp), ['XX', 'T1'], true)) ? strtoupper($paysIp) : null; // XX inconnu, T1 Tor
        $paysFuseau = $fuseau ? ($fuseaux[$fuseau] ?? 'AUTRE') : null;
        if (!$pays) return ['FAIBLE', 'indicatif_non_pris_en_charge'];
        if ($paysIp === null) return $paysFuseau === $pays ? ['MOYEN', 'ip_inconnue'] : ['FAIBLE', 'ip_inconnue'];
        if ($paysIp !== $pays) return ['FAIBLE', 'ip_differente_du_indicatif' . ($paysFuseau === $pays ? ',fuseau_concordant_vpn_possible' : '')];
        return $paysFuseau === $pays ? ['ELEVE', ''] : ['MOYEN', $paysFuseau === null ? 'fuseau_absent' : 'fuseau_incoherent'];
    }
}

if (!function_exists('fuseauNavigateur')) {
    /** Fuseau IANA envoye par le navigateur (cookie mr_tz), ou null s'il est absent ou mal forme. */
    function fuseauNavigateur(): ?string
    {
        $tz = (string) ($_COOKIE['mr_tz'] ?? '');
        return preg_match('#^[A-Za-z_]+(/[A-Za-z0-9_+\-]+){1,2}$#', $tz) ? $tz : null;
    }
}

if (!function_exists('geoCoherenceEnregistrer')) {
    /** Evalue et enregistre le score du compte. Ne bloque jamais l'appelant. */
    function geoCoherenceEnregistrer(PDO $pdo, int $userId, ?string $telephone): void
    {
        try {
            if (trim((string) $telephone) === '') return; // compte sans numero : rien a comparer
            $paysIp = paysIpCloudflare(); // lu a chaque evaluation (pas le cache de session d'avant connexion)
            [$score, $raison] = scoreCoherencePays((string) $telephone, $paysIp, fuseauNavigateur());
            $pdo->prepare("UPDATE users_monrevenu SET geo_score = ?, geo_pays_ip = ?, geo_raisons = ?, geo_evalue_le = NOW() WHERE id = ?")
                ->execute([$score, $paysIp, $raison !== '' ? $raison : null, $userId]);
            if ($score === 'FAIBLE') {
                auditInfoLimite($pdo, 'geo_faible_' . $userId, 86400, ['category' => 'auth', 'action' => 'coherence_pays_faible', 'result' => 'refus',
                    'entity_type' => 'utilisateur', 'entity_id' => $userId, 'meta' => ['pays_ip' => $paysIp, 'raison' => $raison]]);
            }
        } catch (Throwable $e) {
            error_log('geoCoherenceEnregistrer : ' . get_class($e));
        }
    }
}
