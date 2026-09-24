<?php
/**
 * includs/geoip.php
 * Détection du pays d'un visiteur à partir de son adresse IP publique.
 *
 * Utilise l'en-tête Cloudflare CF-IPCountry (gratuit, sans appel externe) ; à activer dans Cloudflare
 * (réglage Réseau > Géolocalisation IP). Le résultat est mis en cache dans la session.
 *
 * Le site étant derrière Cloudflare, $_SERVER['REMOTE_ADDR'] contient
 * l'adresse de l'edge Cloudflare, pas celle du visiteur, on utilise donc
 * en priorité l'en-tête CF-Connecting-IP que Cloudflare ajoute toujours.
 */

require_once __DIR__ . '/config_marche.php';

if (!function_exists('adresseIpVisiteur')) {
    function adresseIpVisiteur(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
    }
}

if (!function_exists('paysIpCloudflare')) {
    /**
     * Pays de l'IP fourni gratuitement par Cloudflare (en-tete CF-IPCountry) : aucun appel externe, aucune
     * latence. Remplace ip-api.com, dont l'offre gratuite est reservee a un usage non commercial.
     * XX = inconnu, T1 = reseau Tor : traites comme indetectables. Hors Cloudflare (dev, local) : null.
     * A ne croire que si le serveur n'est joignable que par Cloudflare.
     */
    function paysIpCloudflare(): ?string
    {
        $code = strtoupper(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
        return preg_match('/^[A-Z]{2}$/', $code) && !in_array($code, ['XX', 'T1'], true) ? $code : null;
    }
}

if (!function_exists('detecterPaysVisiteur')) {
    /**
     * @return array{code: ?string, nom: ?string} Code ISO2 (ex: 'KM') et nom du pays, ou null si indétectable.
     */
    function detecterPaysVisiteur(): array
    {
        if (array_key_exists('geo_pays_code', $_SESSION)) {
            return ['code' => $_SESSION['geo_pays_code'], 'nom' => $_SESSION['geo_pays_nom'] ?? null];
        }

        $ip = adresseIpVisiteur();

        // IP locale/privée (dev, tests, réseau interne) : pas de géolocalisation possible.
        if (paysIpCloudflare() === null) {
            $_SESSION['geo_pays_code'] = null;
            $_SESSION['geo_pays_nom']  = null;
            return ['code' => null, 'nom' => null];
        }

        $code = paysIpCloudflare();
        $nom  = null;
        if ($code !== null) {
            $nom = marcheValide($code) ? marche($code)['nom']
                : (class_exists('Locale') ? (Locale::getDisplayRegion('-' . $code, 'fr') ?: $code) : $code);
        }

        $_SESSION['geo_pays_code'] = $code;
        $_SESSION['geo_pays_nom']  = $nom;

        return ['code' => $code, 'nom' => $nom];
    }
}

if (!function_exists('enregistrerVisitePays')) {
    /**
     * Journalise une visite (une seule fois par session, pas à chaque page
     * vue, pour ne pas saturer la table ni l'API de géolocalisation).
     */
    function enregistrerVisitePays(PDO $pdo, ?int $userId = null): array
    {
        $pays = detecterPaysVisiteur();

        if (isset($_SESSION['visite_deja_enregistree'])) {
            return $pays;
        }

        try {
            $pdo->prepare(
                // audit:exclu telemetrie de visite (volume), hors journal par decision du lot 2
                "INSERT INTO visites_pays (ip, pays_code, pays_nom, user_id, page) VALUES (?, ?, ?, ?, ?)"
            )->execute([
                adresseIpVisiteur(),
                $pays['code'],
                $pays['nom'],
                $userId,
                substr($_SERVER['REQUEST_URI'] ?? '', 0, 255),
            ]);
            $_SESSION['visite_deja_enregistree'] = true;
        } catch (PDOException $e) {
            error_log('enregistrerVisitePays : ' . $e->getMessage());
        }

        return $pays;
    }
}

if (!function_exists('drapeauHtml')) {
    /**
     * Retourne le HTML d'un drapeau (librairie flag-icons, chargée via CDN
     * dans les pages concernées, voir <link> flag-icons dans le <head>).
     * Utilise des SVG, contrairement aux emojis qui ne s'affichent pas
     * correctement sur tous les Windows/navigateurs.
     */
    function drapeauHtml(?string $codePays, string $classesSupplémentaires = ''): string
    {
        if (!$codePays) {
            return '<span class="text-slate-300 text-xs" title="Pays inconnu">Inconnu</span>';
        }
        $code = strtolower(htmlspecialchars($codePays));
        return '<span class="fi fi-' . $code . ' ' . htmlspecialchars($classesSupplémentaires) . '" title="' . htmlspecialchars($codePays) . '"></span>';
    }
}