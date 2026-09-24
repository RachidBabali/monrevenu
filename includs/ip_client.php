<?php
/**
 * includs/ip_client.php : adresse du visiteur et comparaison de reseau pour le verrouillage de session.
 *
 * REMOTE_ADDR fait foi. L'en-tete CF-Connecting-IP n'est cru que si la requete vient bien d'un serveur
 * Cloudflare (REMOTE_ADDR dans les plages publiees) : un client qui atteint l'origine sans passer par
 * Cloudflare ne peut donc pas usurper son IP. Plages : https://www.cloudflare.com/ips/ (a revoir si Cloudflare
 * en publie de nouvelles).
 */
if (!function_exists('prefixeReseau')) {
    /** Reseau d'une IP : /24 en IPv4, /48 en IPv6 (meme forme que le journal d'audit). null si l'IP est invalide. */
    function prefixeReseau(?string $ip): ?string
    {
        if (!$ip) return null;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0/24';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);
            return inet_ntop(substr($bin, 0, 6) . str_repeat("\0", 10)) . '/48';
        }
        return null;
    }
}

if (!function_exists('ipDansPlage')) {
    function ipDansPlage(string $ip, string $cidr): bool
    {
        [$base, $bits] = explode('/', $cidr) + [1 => null];
        $a = @inet_pton($ip);
        $b = @inet_pton((string) $base);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
        $bits = (int) $bits;
        $octets = intdiv($bits, 8);
        if ($octets && substr($a, 0, $octets) !== substr($b, 0, $octets)) return false;
        $reste = $bits % 8;
        if (!$reste) return true;
        $masque = (0xFF << (8 - $reste)) & 0xFF;
        return (ord($a[$octets]) & $masque) === (ord($b[$octets]) & $masque);
    }
}

if (!function_exists('requeteViaCloudflare')) {
    function requeteViaCloudflare(?string $remote): bool
    {
        static $plages = ['173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
            '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32'];
        if (!$remote) return false;
        foreach ($plages as $p) if (ipDansPlage($remote, $p)) return true;
        return false;
    }
}

if (!function_exists('ipClient')) {
    function ipClient(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && requeteViaCloudflare($remote)) return $cf;
        return $remote;
    }
}

if (!function_exists('reseauSessionCompatible')) {
    /** 'identique' : meme IP ; 'meme_reseau' : IP differente, meme /48 ou /24 ; 'different' : autre reseau. */
    function reseauSessionCompatible(string $ipSession, string $ipCourante): string
    {
        if ($ipSession === $ipCourante) return 'identique';
        $a = prefixeReseau($ipSession);
        return ($a !== null && $a === prefixeReseau($ipCourante)) ? 'meme_reseau' : 'different';
    }
}
