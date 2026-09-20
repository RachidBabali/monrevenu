<?php
/**
 * includs/sante.php : etat de l'application, de la base, du stockage R2, des services externes
 * et des dependances. Lecture seule. Utilise par admin/audit.php et par admin/cron/snapshot.php.
 *
 * Chaque controle renvoie ['cle', 'libelle', 'valeur', 'niveau' => ok|attention|critique|inconnu, 'note'].
 * Aucune valeur de variable d'environnement, aucune cle, aucun secret n'est renvoye : seulement leur presence.
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/r2_uploader.php';

const SANTE_LIMITE_BASE_OCTETS = 3 * 1024 * 1024 * 1024; // offre Hostinger : 3 Go
const SANTE_CACHE_SECONDES = 600;

if (!function_exists('santeControle')) {
    function santeControle(string $cle, string $libelle, $valeur, string $niveau = 'ok', string $note = ''): array
    {
        return ['cle' => $cle, 'libelle' => $libelle, 'valeur' => $valeur, 'niveau' => $niveau, 'note' => $note];
    }

    /** Cache court sur disque (storage/cache) pour les controles couteux (R2, taille des tables). */
    function santeCache(string $cle, int $secondes, callable $calcul)
    {
        $fichier = dirname(__DIR__) . '/storage/cache/sante_' . preg_replace('/[^a-z0-9_]/', '_', $cle) . '.json';
        if (is_file($fichier) && filemtime($fichier) > time() - $secondes) {
            $v = json_decode((string) @file_get_contents($fichier), true);
            if (is_array($v) && array_key_exists('v', $v)) return $v['v'];
        }
        $valeur = $calcul();
        $dossier = dirname($fichier);
        if (is_dir($dossier) || @mkdir($dossier, 0755, true)) {
            $tmp = $fichier . '.' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, json_encode(['v' => $valeur], JSON_UNESCAPED_UNICODE)) !== false) @rename($tmp, $fichier);
        }
        return $valeur;
    }

    function santeOctets(?float $o): string
    {
        if ($o === null) return 'inconnu';
        $unites = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        while ($o >= 1024 && $i < count($unites) - 1) { $o /= 1024; $i++; }
        return number_format($o, $i === 0 ? 0 : 1, ',', ' ') . ' ' . $unites[$i];
    }

    /** 1. Application : PHP, extensions, limites, variables d'environnement, journal d'erreurs. */
    function santeApplication(): array
    {
        $c = [];
        $versionOk = version_compare(PHP_VERSION, '8.1', '>=');
        $c[] = santeControle('php_version', 'Version de PHP', PHP_VERSION, $versionOk ? 'ok' : 'critique',
            $versionOk ? '' : 'Le code demande PHP 8.1 au minimum.');

        $requises = ['pdo_mysql', 'mbstring', 'curl', 'openssl', 'gd', 'fileinfo', 'json'];
        $manquantes = array_values(array_filter($requises, fn($e) => !extension_loaded($e)));
        $c[] = santeControle('php_extensions', 'Extensions requises', $manquantes ? 'manquantes : ' . implode(', ', $manquantes) : implode(', ', $requises),
            $manquantes ? 'critique' : 'ok');
        $utiles = array_values(array_filter(['apcu', 'exif', 'intl', 'zip'], 'extension_loaded'));
        $c[] = santeControle('php_extensions_utiles', 'Extensions utiles présentes', $utiles ? implode(', ', $utiles) : 'aucune', 'ok',
            in_array('apcu', $utiles, true) ? 'Cache mémoire disponible.' : 'Sans APCu, les caches passent par des fichiers.');

        $memoire = ini_get('memory_limit');
        $envoi = min((int) santeTailleIni(ini_get('upload_max_filesize')), (int) santeTailleIni(ini_get('post_max_size')));
        $c[] = santeControle('php_limites', 'Limites PHP', 'mémoire ' . $memoire . ', envoi ' . santeOctets($envoi ?: null),
            $envoi > 0 && $envoi < 2 * 1024 * 1024 ? 'attention' : 'ok',
            $envoi > 0 && $envoi < 2 * 1024 * 1024 ? "Les images de 2 Mo seront refusées par PHP avant d'arriver au code." : '');

        $variables = [
            'DB_HOST' => 'critique', 'DB_NAME' => 'critique', 'DB_USER' => 'critique', 'DB_PASS' => 'critique',
            'AUDIT_HMAC_KEY' => 'critique', 'AFFILIATION_SECRET' => 'critique',
            'R2_ACCOUNT_ID' => 'attention', 'R2_ACCESS_KEY_ID' => 'attention', 'R2_SECRET_ACCESS_KEY' => 'attention',
            'R2_BUCKET_NAME' => 'attention', 'R2_PUBLIC_URL' => 'attention',
            'VAPID_PUBLIC_KEY' => 'attention', 'VAPID_PRIVATE_KEY' => 'attention',
            'SMTP_HOST' => 'attention', 'SMTP_USER' => 'attention', 'SMTP_PASS' => 'attention',
            'WHATSAPP_APP_SECRET' => 'attention', 'WHATSAPP_WEBHOOK_VERIFY_TOKEN' => 'attention',
            'GOOGLE_CLIENT_ID' => 'attention',
        ];
        $absentes = [];
        foreach ($variables as $nom => $gravite) {
            if ((string) env($nom, '') === '') $absentes[$gravite][] = $nom;
        }
        $niveau = !empty($absentes['critique']) ? 'critique' : (!empty($absentes['attention']) ? 'attention' : 'ok');
        $texte = $niveau === 'ok' ? 'toutes présentes'
            : 'absentes : ' . implode(', ', array_merge($absentes['critique'] ?? [], $absentes['attention'] ?? []));
        $c[] = santeControle('env', 'Variables d\'environnement', $texte, $niveau, 'Seuls les noms sont affichés, jamais les valeurs.');

        // Cle d'affiliation : valeur de repli = liens signes avec une cle publique connue
        if (defined('SECRET_AFFILIATION_REPLI') && SECRET_AFFILIATION_REPLI) {
            $c[] = santeControle('affiliation_repli', 'Clé d\'affiliation', 'valeur de repli utilisée', 'critique',
                'Définir AFFILIATION_SECRET dans le .env : sans elle, les liens sont signés avec une clé connue.');
        }
        $idGoogle = (string) env('GOOGLE_CLIENT_ID', '');
        if ($idGoogle !== '' && (str_starts_with($idGoogle, 'http') || str_ends_with($idGoogle, '/'))) {
            $c[] = santeControle('google_client_id', 'Identifiant Google', 'format inattendu', 'attention',
                'Il doit se terminer par .apps.googleusercontent.com, sans http:// ni barre finale.');
        }

        $journal = ini_get('error_log') ?: '';
        if ($journal !== '' && is_file($journal)) {
            $taille = filesize($journal);
            $c[] = santeControle('journal_erreurs', 'Journal d\'erreurs PHP', santeOctets($taille),
                $taille > 20 * 1024 * 1024 ? 'attention' : 'ok', basename($journal));
        } else {
            $c[] = santeControle('journal_erreurs', 'Journal d\'erreurs PHP', 'chemin non configuré', 'inconnu');
        }
        return $c;
    }

    function santeTailleIni(?string $valeur): int
    {
        if (!$valeur) return 0;
        $unite = strtolower(substr(trim($valeur), -1));
        $nombre = (int) $valeur;
        return match ($unite) { 'g' => $nombre * 1024 ** 3, 'm' => $nombre * 1024 ** 2, 'k' => $nombre * 1024, default => $nombre };
    }

    /** Dernieres lignes du journal d'erreurs PHP, adresses et chemins masques. */
    function santeDernieresErreurs(int $lignes = 15): array
    {
        $journal = ini_get('error_log') ?: '';
        if ($journal === '' || !is_file($journal) || !is_readable($journal)) return [];
        $taille = filesize($journal);
        $f = @fopen($journal, 'rb');
        if (!$f) return [];
        fseek($f, max(0, $taille - 64 * 1024));
        $contenu = (string) fread($f, 64 * 1024);
        fclose($f);
        $tout = array_values(array_filter(array_map('trim', explode("\n", $contenu))));
        $dernieres = array_slice($tout, -$lignes);
        return array_map(static function (string $l): string {
            $l = preg_replace('/\S+@\S+\.\S+/', '[adresse]', $l);
            $l = preg_replace('#/home/[^ :]+#', '[chemin]', $l);
            $l = preg_replace('/\b\d{8,}\b/', '[numero]', $l);
            return mb_substr($l, 0, 240);
        }, $dernieres);
    }

    /** 2. Base de donnees : latence, taille, lignes, controles d'integrite. */
    function santeBase(PDO $pdo): array
    {
        $c = [];
        $debut = microtime(true);
        try {
            $pdo->query('SELECT 1')->fetchColumn();
            $latence = (int) round((microtime(true) - $debut) * 1000);
            $c[] = santeControle('bd_latence', 'Latence de la base', $latence . ' ms', $latence > 500 ? 'attention' : 'ok');
        } catch (PDOException $e) {
            return [santeControle('bd_latence', 'Latence de la base', 'injoignable', 'critique')];
        }
        $tables = santeTables($pdo);
        $total = array_sum(array_column($tables, 'octets'));
        $part = SANTE_LIMITE_BASE_OCTETS > 0 ? round($total / SANTE_LIMITE_BASE_OCTETS * 100, 1) : 0;
        $c[] = santeControle('bd_taille', 'Taille de la base', santeOctets($total) . ' (' . $part . ' % de 3 Go)',
            $part > 80 ? 'critique' : ($part > 50 ? 'attention' : 'ok'), count($tables) . ' tables');
        $c[] = santeControle('bd_lignes', 'Lignes (estimation)', number_format(array_sum(array_column($tables, 'lignes')), 0, ',', ' '), 'ok',
            'Estimation donnée par SHOW TABLE STATUS.');
        return array_merge($c, santeTablesManquantes($pdo));
    }

    /** Liste des tables avec leur taille (SHOW TABLE STATUS, jamais information_schema). */
    function santeTables(PDO $pdo): array
    {
        return santeCache('tables', SANTE_CACHE_SECONDES, static function () use ($pdo) {
            $tables = [];
            try {
                foreach ($pdo->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC) as $t) {
                    $tables[] = [
                        'nom' => $t['Name'],
                        'lignes' => (int) ($t['Rows'] ?? 0),
                        'octets' => (int) ($t['Data_length'] ?? 0) + (int) ($t['Index_length'] ?? 0),
                        'moteur' => $t['Engine'] ?? '',
                        'collation' => $t['Collation'] ?? '',
                    ];
                }
            } catch (PDOException $e) {
                error_log('[sante] SHOW TABLE STATUS : ' . $e->getMessage());
            }
            usort($tables, fn($a, $b) => $b['octets'] <=> $a['octets']);
            return $tables;
        });
    }

    /**
     * Tables dont le code a besoin. Une table absente veut dire qu'une migration n'a pas ete
     * appliquee : le code ne cree plus aucune table a la volee (login_attempts_compte comprise).
     */
    function santeTablesAttendues(): array
    {
        return [
            'users_monrevenu', 'vendeur_produits', 'vendeur_ventes', 'transactions_monrevenu', 'withdrawals',
            'agent_commissions', 'stocks_revendeurs', 'mouvements_stock', 'produits_stock', 'ventes_stock',
            'messages', 'push_subscriptions', 'visites_pays', 'whatsapp_webhook_log',
            'login_attempts', 'login_attempts_compte', 'journal_suppressions_compte',
            'audit_log', 'audit_chain_head', 'health_snapshots',
            'commercants_profils', 'commercant_reglements',
        ];
    }

    /** Tables attendues mais absentes : migration oubliee. */
    function santeTablesManquantes(PDO $pdo): array
    {
        $presentes = array_column(santeTables($pdo), 'nom');
        $manquantes = array_values(array_diff(santeTablesAttendues(), $presentes));
        $note = $manquantes
            ? 'Migration non appliquée : ' . implode(', ', $manquantes) . '. Voir le dossier migrations.'
            : 'Les ' . count(santeTablesAttendues()) . ' tables attendues sont présentes.';
        return [santeControle('bd_tables_manquantes', 'Tables attendues absentes', count($manquantes),
            $manquantes ? 'critique' : 'ok', $note)];
    }

    /** Controles d'integrite : orphelins, dates incoherentes, commandes bloquees. */
    function santeIntegrite(PDO $pdo): array
    {
        $controles = [
            ['transactions_sans_compte', 'Transactions sans compte',
                "SELECT COUNT(*) FROM transactions_monrevenu t LEFT JOIN users_monrevenu u ON u.id = t.user_id WHERE u.id IS NULL"],
            ['ventes_sans_produit', 'Commandes sans produit',
                "SELECT COUNT(*) FROM vendeur_ventes v LEFT JOIN vendeur_produits p ON p.id = v.produit_id WHERE p.id IS NULL"],
            ['ventes_sans_affilie', 'Commandes sans affilié',
                "SELECT COUNT(*) FROM vendeur_ventes v LEFT JOIN users_monrevenu u ON u.id = v.vendeur_id WHERE u.id IS NULL"],
            ['retraits_sans_compte', 'Retraits sans compte',
                "SELECT COUNT(*) FROM withdrawals w LEFT JOIN users_monrevenu u ON u.id = w.user_id WHERE u.id IS NULL"],
            ['dates_incoherentes', 'Transactions datées dans le futur',
                "SELECT COUNT(*) FROM transactions_monrevenu WHERE created_at > DATE_ADD(NOW(), INTERVAL 1 DAY)"],
            ['commandes_bloquees', 'Commandes en attente depuis plus de 14 jours',
                "SELECT COUNT(*) FROM vendeur_ventes WHERE statut IN ('en_attente', 'contacte') AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)"],
            ['produits_attente', 'Produits en attente de validation depuis plus de 3 jours',
                "SELECT COUNT(*) FROM vendeur_produits WHERE moderation = 'en_attente' AND COALESCE(updated_at, created_at) < DATE_SUB(NOW(), INTERVAL 3 DAY)"],
            ['comptes_statut', 'Comptes dont is_active et status divergent',
                "SELECT COUNT(*) FROM users_monrevenu WHERE (is_active = 1 AND status = 'suspended') OR (is_active = 0 AND status = 'active' AND phone_verified = 1)"],
        ];
        $resultats = [];
        foreach ($controles as [$cle, $libelle, $sql]) {
            try {
                $n = (int) $pdo->query($sql)->fetchColumn();
                $resultats[] = santeControle($cle, $libelle, $n, $n === 0 ? 'ok' : 'attention');
            } catch (PDOException $e) {
                $resultats[] = santeControle($cle, $libelle, 'non vérifiable', 'inconnu', 'Table absente ?');
            }
        }
        return $resultats;
    }

    /** 3. Stockage R2 : joignabilite, nombre d'objets, images manquantes ou non referencees. */
    function santeStockage(PDO $pdo, bool $complet = false): array
    {
        $configure = env('R2_ACCOUNT_ID') && env('R2_ACCESS_KEY_ID') && env('R2_SECRET_ACCESS_KEY') && env('R2_BUCKET_NAME') && env('R2_PUBLIC_URL');
        if (!$configure) {
            return ['controles' => [santeControle('r2', 'Stockage R2', 'non configuré', 'attention',
                'Les images des commerçants sont alors écrites sur le serveur.')], 'objets' => [], 'manquantes' => [], 'orphelins' => []];
        }
        $donnees = santeCache('r2', SANTE_CACHE_SECONDES, static function () {
            $objets = [];
            $suite = null;
            $pages = 0;
            $duree = 0;
            do {
                $params = ['list-type' => '2', 'max-keys' => '1000'];
                if ($suite) $params['continuation-token'] = $suite;
                $r = r2Requete('GET', '', $params);
                $duree += $r['duree_ms'];
                if (!$r['ok']) return ['erreur' => true, 'code' => $r['code'], 'duree' => $duree, 'objets' => [], 'tronque' => false];
                $xml = @simplexml_load_string($r['corps']);
                if (!$xml) break;
                foreach ($xml->Contents ?? [] as $o) {
                    $objets[] = ['cle' => (string) $o->Key, 'octets' => (int) $o->Size, 'date' => (string) $o->LastModified];
                }
                $suite = (string) ($xml->NextContinuationToken ?? '');
                $pages++;
            } while ($suite !== '' && $pages < 10); // borne : 10 000 objets au plus
            return ['erreur' => false, 'code' => 200, 'duree' => $duree, 'objets' => $objets, 'tronque' => $suite !== ''];
        });

        if (!empty($donnees['erreur'])) {
            return ['controles' => [santeControle('r2', 'Stockage R2', 'injoignable (code ' . $donnees['code'] . ')', 'critique')],
                'objets' => [], 'manquantes' => [], 'orphelins' => []];
        }

        $objets = $donnees['objets'];
        $total = array_sum(array_column($objets, 'octets'));
        $controles = [
            santeControle('r2', 'Stockage R2', 'joignable', 'ok', 'Réponse en ' . $donnees['duree'] . ' ms'),
            santeControle('r2_objets', 'Objets stockés', number_format(count($objets), 0, ',', ' ') . ($donnees['tronque'] ? ' (liste tronquée)' : ''), 'ok'),
            santeControle('r2_taille', 'Espace occupé', santeOctets($total), 'ok'),
        ];

        // Images referencees en base mais absentes du stockage, et objets non referencees (echantillon)
        $public = rtrim((string) env('R2_PUBLIC_URL'), '/');
        $cles = array_column($objets, 'cle');
        $index = array_flip($cles);
        $referencees = [];
        $manquantes = [];
        try {
            foreach ($pdo->query("SELECT id, image FROM vendeur_produits WHERE image LIKE '" . str_replace("'", "''", $public) . "%'")->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $cle = substr($p['image'], strlen($public) + 1);
                $referencees[$cle] = true;
                if (!isset($index[$cle])) $manquantes[] = ['produit_id' => (int) $p['id'], 'cle' => $cle];
            }
            foreach ($pdo->query("SELECT id, image FROM produits_stock WHERE image LIKE '" . str_replace("'", "''", $public) . "%'")->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $cle = substr($p['image'], strlen($public) + 1);
                $referencees[$cle] = true;
                if (!isset($index[$cle])) $manquantes[] = ['produit_stock_id' => (int) $p['id'], 'cle' => $cle];
            }
        } catch (PDOException $e) {
            error_log('[sante] images referencees : ' . $e->getMessage());
        }
        $orphelins = [];
        foreach ($objets as $o) {
            if (!isset($referencees[$o['cle']]) && count($orphelins) < 50) $orphelins[] = $o;
        }
        $controles[] = santeControle('r2_manquantes', 'Images référencées mais absentes du stockage', count($manquantes),
            $manquantes ? 'critique' : 'ok');
        $controles[] = santeControle('r2_orphelins', 'Objets non référencés (échantillon de 50 au plus)', count($orphelins),
            $orphelins ? 'attention' : 'ok', 'Anciennes images remplacées, ou envois interrompus.');

        return ['controles' => $controles, 'objets' => $objets, 'manquantes' => $manquantes, 'orphelins' => $orphelins];
    }

    /** 4. Services externes, d'apres le journal (aucun envoi declenche). */
    function santeServices(PDO $pdo): array
    {
        $dernier = static function (string $sql) use ($pdo) {
            try {
                $v = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
                return $v ?: null;
            } catch (PDOException $e) { return null; }
        };
        $c = [];
        $wh = $dernier("SELECT occurred_at FROM audit_log WHERE action = 'webhook_whatsapp_recu' ORDER BY id DESC LIMIT 1");
        $c[] = santeControle('whatsapp_webhook', 'Dernier webhook WhatsApp reçu', $wh['occurred_at'] ?? 'jamais',
            $wh ? (strtotime($wh['occurred_at']) < time() - 7 * 86400 ? 'attention' : 'ok') : 'inconnu');
        $sig = $dernier("SELECT occurred_at FROM audit_log WHERE action IN ('webhook_whatsapp_signature_invalide','webhook_whatsapp_secret_absent') ORDER BY id DESC LIMIT 1");
        $c[] = santeControle('whatsapp_signature', 'Dernier refus de signature WhatsApp', $sig['occurred_at'] ?? 'aucun', $sig ? 'attention' : 'ok');
        $mail = $dernier("SELECT occurred_at, result FROM audit_log WHERE action LIKE 'email_%' ORDER BY id DESC LIMIT 1");
        $c[] = santeControle('email', 'Dernier e-mail', $mail ? $mail['occurred_at'] . ' (' . $mail['result'] . ')' : 'jamais',
            $mail ? ($mail['result'] === 'ok' ? 'ok' : 'attention') : 'inconnu');
        $push = $dernier("SELECT occurred_at, action, result FROM audit_log WHERE action LIKE 'push_%' ORDER BY id DESC LIMIT 1");
        $c[] = santeControle('push', 'Dernière notification push', $push ? $push['occurred_at'] . ' (' . $push['action'] . ')' : 'jamais',
            $push ? ($push['result'] === 'ok' ? 'ok' : 'attention') : 'inconnu');
        try {
            $abos = (int) $pdo->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
            $comptes = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM push_subscriptions")->fetchColumn();
            $echecs = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'push_echec' AND occurred_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $envois = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action IN ('push_envoye','push_echec') AND occurred_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $taux = $envois > 0 ? round($echecs / $envois * 100) : 0;
            $c[] = santeControle('push_abonnements', 'Abonnements push', $abos . ' sur ' . $comptes . ' compte(s)', $abos === 0 ? 'attention' : 'ok',
                $envois > 0 ? $taux . ' % d\'échecs sur 7 jours' : 'Aucun envoi sur 7 jours');
        } catch (PDOException $e) {
            $c[] = santeControle('push_abonnements', 'Abonnements push', 'non vérifiable', 'inconnu');
        }
        $c[] = santeControle('google', 'Identifiant Google', (string) env('GOOGLE_CLIENT_ID', '') !== '' ? 'présent' : 'absent',
            (string) env('GOOGLE_CLIENT_ID', '') !== '' ? 'ok' : 'attention');
        return $c;
    }

    /** 5. Dependances : composer.lock, contrôle composer audit, composants copies dans le depot. */
    function santeDependances(): array
    {
        $racine = dirname(__DIR__);
        $paquets = [];
        $lock = $racine . '/composer.lock';
        if (is_file($lock)) {
            $json = json_decode((string) file_get_contents($lock), true) ?: [];
            foreach (array_merge($json['packages'] ?? [], $json['packages-dev'] ?? []) as $p) {
                $paquets[] = ['nom' => $p['name'], 'version' => $p['version'], 'licence' => implode(', ', $p['license'] ?? [])];
            }
            $phpRequis = $json['platform-overrides']['php'] ?? ($json['packages'][0]['require']['php'] ?? '');
        }
        $audit = null;
        $fichierAudit = $racine . '/storage/audit/composer-audit.json';
        if (is_file($fichierAudit)) {
            $contenu = json_decode((string) file_get_contents($fichierAudit), true) ?: [];
            $audit = [
                'date' => $contenu['date'] ?? date('Y-m-d', filemtime($fichierAudit)),
                'vulnerabilites' => count($contenu['advisories'] ?? []),
                'age_jours' => (int) floor((time() - filemtime($fichierAudit)) / 86400),
            ];
        }
        $copies = [
            ['Lucide (icônes, tracés copiés)', '0.469', 'ISC'],
            ['IBM Plex Sans et Mono (polices)', 'sous-ensemble latin', 'SIL OFL 1.1'],
            ['Tailwind CLI (compilation locale)', '3.4.17', 'MIT'],
            ['PHPMailer (copie dans lib/)', 'voir lib/PHPMailer-master', 'LGPL 2.1'],
        ];
        return ['paquets' => $paquets, 'audit' => $audit, 'copies' => $copies];
    }

    /** 6. En-tetes de securite reellement servis par le site. */
    function santeEntetes(string $url): array
    {
        return santeCache('entetes', SANTE_CACHE_SECONDES, static function () use ($url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true]);
            $reponse = (string) curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code === 0) return ['erreur' => true, 'entetes' => []];
            $entetes = [];
            foreach (explode("\n", $reponse) as $ligne) {
                if (str_contains($ligne, ':')) {
                    [$k, $v] = explode(':', $ligne, 2);
                    $entetes[strtolower(trim($k))] = trim($v);
                }
            }
            return ['erreur' => false, 'entetes' => $entetes];
        });
    }

    function santeControlesEntetes(array $entetes): array
    {
        $attendus = [
            'content-security-policy' => 'critique',
            'x-content-type-options' => 'attention',
            'x-frame-options' => 'attention',
            'referrer-policy' => 'attention',
        ];
        $c = [];
        foreach ($attendus as $nom => $gravite) {
            $present = isset($entetes[$nom]);
            $c[] = santeControle('entete_' . $nom, 'En-tête ' . $nom, $present ? 'servi' : 'absent', $present ? 'ok' : $gravite,
                $present ? mb_substr($entetes[$nom], 0, 80) : '');
        }
        return $c;
    }

    /** Niveau le plus grave d'une liste de controles. */
    function santeNiveauGlobal(array $controles): string
    {
        $ordre = ['ok' => 0, 'inconnu' => 1, 'attention' => 2, 'critique' => 3];
        $max = 'ok';
        foreach ($controles as $c) {
            if (($ordre[$c['niveau']] ?? 0) > ($ordre[$max] ?? 0)) $max = $c['niveau'];
        }
        return $max;
    }

    /** Tableau d'affichage d'une liste de controles (utilise par les onglets de l'audit). */
    function tableauControles(array $controles, string $titre): void
{
    ?>
    <section class="flex flex-col gap-3">
      <h2 class="section-titre"><?= e($titre) ?></h2>
      <div class="carte overflow-hidden">
        <table class="tableau tableau-empile">
          <thead><tr><th scope="col">Contrôle</th><th scope="col">Valeur</th><th scope="col">État</th><th scope="col">Note</th></tr></thead>
          <tbody>
          <?php foreach ($controles as $c): ?>
            <tr>
              <td data-label="Contrôle" class="font-medium"><?= e($c['libelle']) ?></td>
              <td data-label="Valeur" class="text-text-2"><?= e((string) $c['valeur']) ?></td>
              <td data-label="État"><span class="pastille pastille-<?= ['ok' => 'succes', 'attention' => 'attente', 'critique' => 'danger', 'inconnu' => 'neutre'][$c['niveau']] ?>">
                <?= ['ok' => 'Correct', 'attention' => 'À surveiller', 'critique' => 'Critique', 'inconnu' => 'Inconnu'][$c['niveau']] ?></span></td>
              <td data-label="Note" class="text-text-2"><?= e($c['note']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php
}
}
