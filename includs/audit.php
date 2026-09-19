<?php
/**
 * includs/audit.php : journal d'audit en ajout seulement, chaine de hachage.
 *
 * Chaque ligne contient le hachage de la precedente (prev_hash) et le sien (row_hash),
 * calcule en HMAC-SHA256 avec AUDIT_HMAC_KEY (.env) : sans la cle, une ligne modifiee
 * directement en base ne peut pas recevoir un hachage valide. La tete de chaine
 * (audit_chain_head, ligne 1) est verrouillee FOR UPDATE pendant l'ajout.
 *
 * Deux modes :
 *   auditCritique() : argent et administration. Leve une exception si le journal
 *                     echoue : l'appelant annule sa transaction (fail closed).
 *   auditInfo()     : authentification, lecture, systeme. Ne leve jamais : l'erreur
 *                     part dans le journal d'erreurs PHP (fail open).
 * Si l'appelant a une transaction ouverte, la ligne est ecrite dans cette transaction ;
 * sinon une transaction courte est ouverte pour l'ajout.
 * Ordre des verrous : la tete de chaine se verrouille toujours EN DERNIER (apres l'utilisateur,
 * la vente, le retrait), et aucun appel reseau ne doit avoir lieu tant qu'elle est verrouillee :
 * les notifications partent apres le commit.
 *
 * Jamais dans le journal : mots de passe, hachages, codes, jetons, cles, contenu des
 * messages. Telephone et e-mail passent par journalMasquerTelephone()/journalMasquerEmail().
 * L'IP complete et le user-agent sont hors hachage (effaces ou tronques plus tard) ;
 * l'IP tronquee (/24, /48) est dans le hachage.
 */

require_once __DIR__ . '/env_loader.php';

const AUDIT_CATEGORIES = ['auth', 'argent', 'produit', 'commande', 'admin', 'systeme', 'compte'];
const AUDIT_CHAMPS_INTERDITS = ['password', 'mot_de_passe', 'code', 'confirm_code', 'verification_code', 'reset_password_code',
    'whatsapp_verif_code', 'csrf_token', 'token', 'credential', 'secret', 'p256dh', 'auth', 'endpoint', 'message', 'message_body'];

if (!function_exists('auditRequestId')) {
    function auditRequestId(): string
    {
        static $id = null;
        if ($id === null) $id = bin2hex(random_bytes(8));
        return $id;
    }

    function journalMasquerTelephone(?string $tel): ?string
    {
        if ($tel === null || $tel === '') return $tel;
        $chiffres = preg_replace('/\D/', '', $tel);
        if (strlen($chiffres) < 5) return '***';
        return substr($chiffres, 0, 2) . '***' . substr($chiffres, -2);
    }

    function journalMasquerEmail(?string $email): ?string
    {
        if ($email === null || $email === '' || !str_contains($email, '@')) return $email === null ? null : '***';
        [$local, $domaine] = explode('@', $email, 2);
        return mb_substr($local, 0, 1) . '***@' . $domaine;
    }

    function tronquerIp(?string $ip): ?string
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

    /** Retire recursivement les champs interdits et masque telephone et e-mail. */
    function auditNettoyer($valeur)
    {
        if (!is_array($valeur)) return $valeur;
        $propre = [];
        foreach ($valeur as $cle => $v) {
            $c = strtolower((string) $cle);
            if (in_array($c, AUDIT_CHAMPS_INTERDITS, true)) { $propre[$cle] = '[retire]'; continue; }
            if (is_string($v) && in_array($c, ['phone', 'telephone', 'telephone_client', 'wa_from', 'whatsapp_verif_numero', 'numero'], true)) $v = journalMasquerTelephone($v);
            elseif (is_string($v) && in_array($c, ['email', 'identifiant'], true)) $v = str_contains($v, '@') ? journalMasquerEmail($v) : journalMasquerTelephone($v);
            $propre[$cle] = is_array($v) ? auditNettoyer($v) : $v;
        }
        return $propre;
    }

    /** Ne garde que les champs qui changent entre avant et apres. */
    function auditDiff(array $avant, array $apres): array
    {
        $a = []; $b = [];
        foreach (array_unique(array_merge(array_keys($avant), array_keys($apres))) as $k) {
            $va = $avant[$k] ?? null; $vb = $apres[$k] ?? null;
            if ((string) $va !== (string) $vb || ($va === null) !== ($vb === null)) { $a[$k] = $va; $b[$k] = $vb; }
        }
        return [$a, $b];
    }

    function auditCle(): string
    {
        return (string) env('AUDIT_HMAC_KEY', '');
    }

    /** Donnees couvertes par le hachage, dans un ordre fixe. */
    function auditChaineDonnees(array $l): string
    {
        $champs = ['prev_hash', 'occurred_at', 'request_id', 'actor_id', 'actor_role', 'category', 'action', 'entity_type',
            'entity_id', 'result', 'route', 'ip_prefixe', 'before_json', 'after_json', 'meta_json'];
        $v = [];
        foreach ($champs as $c) $v[] = $l[$c] === null ? null : (string) $l[$c];
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    function auditHacher(array $l, ?string $cle = null): string
    {
        return hash_hmac('sha256', auditChaineDonnees($l), $cle ?? auditCle());
    }

    /**
     * Ecrit une ligne. $e : category, action, entity_type, entity_id, result (ok|refus|echec),
     * before, after, meta (tableaux), actor_id, actor_role (sinon pris dans la session).
     * Retourne l'identifiant de la ligne. Leve une exception en cas d'echec.
     */
    function auditEcrire(PDO $pdo, array $e): int
    {
        if (!in_array($e['category'] ?? '', AUDIT_CATEGORIES, true)) {
            throw new InvalidArgumentException('Categorie d\'audit inconnue');
        }
        $json = static function ($v) {
            if ($v === null || $v === []) return null;
            return json_encode(auditNettoyer($v), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        };
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $route = PHP_SAPI === 'cli' ? 'cli:' . basename($_SERVER['argv'][0] ?? '') : (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: null);
        $ligne = [
            'occurred_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.v'),
            'request_id'  => auditRequestId(),
            'actor_id'    => array_key_exists('actor_id', $e) ? $e['actor_id'] : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null),
            'actor_role'  => array_key_exists('actor_role', $e) ? $e['actor_role'] : ($_SESSION['user_role'] ?? null),
            'category'    => $e['category'],
            'action'      => substr((string) $e['action'], 0, 60),
            'entity_type' => $e['entity_type'] ?? null,
            'entity_id'   => isset($e['entity_id']) ? substr((string) $e['entity_id'], 0, 64) : null,
            'result'      => $e['result'] ?? 'ok',
            'route'       => $route !== null ? substr($route, 0, 160) : null,
            'ip_prefixe'  => tronquerIp($ip),
            'before_json' => $json($e['before'] ?? null),
            'after_json'  => $json($e['after'] ?? null),
            'meta_json'   => $json($e['meta'] ?? null),
        ];
        if ($ligne['actor_id'] !== null) $ligne['actor_id'] = (int) $ligne['actor_id'];

        // Dans la transaction de l'appelant : point de sauvegarde, pour qu'un echec partiel (ligne ecrite, tete non
        // mise a jour) soit annule sans toucher au reste de la transaction.
        $propre = !$pdo->inTransaction();
        if ($propre) $pdo->beginTransaction(); else $pdo->exec('SAVEPOINT audit_ligne');
        try {
            $tete = $pdo->query("SELECT last_id, last_hash FROM audit_chain_head WHERE id = 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
            if (!$tete) throw new RuntimeException('Tete de chaine absente');
            $ligne['prev_hash'] = $tete['last_hash'];
            $ligne['row_hash']  = auditHacher($ligne);

            $st = $pdo->prepare(
                "INSERT INTO audit_log (occurred_at, request_id, actor_id, actor_role, category, action, entity_type, entity_id,
                   result, route, ip_prefixe, ip, user_agent, before_json, after_json, meta_json, prev_hash, row_hash)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $st->execute([
                $ligne['occurred_at'], $ligne['request_id'], $ligne['actor_id'], $ligne['actor_role'], $ligne['category'],
                $ligne['action'], $ligne['entity_type'], $ligne['entity_id'], $ligne['result'], $ligne['route'],
                $ligne['ip_prefixe'], $ip, isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
                $ligne['before_json'], $ligne['after_json'], $ligne['meta_json'], $ligne['prev_hash'], $ligne['row_hash'],
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE audit_chain_head SET last_id = ?, last_hash = ? WHERE id = 1")->execute([$id, $ligne['row_hash']]);
            if ($propre) $pdo->commit(); else $pdo->exec('RELEASE SAVEPOINT audit_ligne');
            return $id;
        } catch (Throwable $t) {
            if ($propre && $pdo->inTransaction()) $pdo->rollBack();
            elseif (!$propre && $pdo->inTransaction()) { try { $pdo->exec('ROLLBACK TO SAVEPOINT audit_ligne'); } catch (Throwable $ignore) {} }
            throw $t;
        }
    }

    /** Argent et administration : l'exception remonte, l'appelant annule son action. */
    function auditCritique(PDO $pdo, array $e): int
    {
        return auditEcrire($pdo, $e);
    }

    /** Authentification, lecture, systeme : ne bloque jamais l'action. */
    function auditInfo(?PDO $pdo, array $e): ?int
    {
        if (!$pdo) { error_log('[audit] pas de connexion, evenement ' . ($e['action'] ?? '?') . ' non journalise'); return null; }
        try {
            return auditEcrire($pdo, $e);
        } catch (Throwable $t) {
            error_log('[audit] echec de journalisation (' . ($e['action'] ?? '?') . ') : ' . get_class($t));
            return null;
        }
    }

    /** Echec du jeton CSRF : toujours journalise, jamais bloquant pour la suite (la requete est deja refusee). */
    function auditCsrf(?PDO $pdo, string $page): void
    {
        auditInfo($pdo, ['category' => 'systeme', 'action' => 'csrf_echec', 'result' => 'refus', 'meta' => ['page' => $page]]);
    }

    /**
     * Evenement repetitif (variable manquante, push ignore faute de cles) : une ligne au plus par cle et par periode.
     * Le verrou est un fichier dans storage/cache/ ; sans dossier accessible, l'evenement est journalise a chaque fois.
     */
    function auditInfoLimite(?PDO $pdo, string $cle, int $secondes, array $e): void
    {
        $dossier = dirname(__DIR__) . '/storage/cache';
        $fichier = $dossier . '/audit_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($cle)) . '.flag';
        if (is_file($fichier) && filemtime($fichier) > time() - $secondes) return;
        if (is_dir($dossier) || @mkdir($dossier, 0755, true)) @touch($fichier);
        auditInfo($pdo, $e);
    }

    /**
     * Envoi externe (e-mail, WhatsApp) : reussi ou echoue, sans contenu ni destinataire complet.
     * Utilise la connexion globale $pdo quand l'expediteur n'en recoit pas.
     */
    function auditEnvoi(string $canal, string $objet, bool $ok, ?string $destinataire = null, ?string $erreur = null): void
    {
        $pdo = (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) ? $GLOBALS['pdo'] : null;
        $meta = ['objet' => $objet];
        if ($destinataire !== null) $meta[str_contains($destinataire, '@') ? 'email' : 'telephone'] = $destinataire;
        if (!$ok && $erreur !== null) $meta['erreur'] = mb_substr(preg_replace('/\S+@\S+/', '[adresse]', $erreur), 0, 160);
        auditInfo($pdo, ['category' => 'systeme', 'action' => $canal . ($ok ? '_envoye' : '_echec'), 'result' => $ok ? 'ok' : 'echec', 'meta' => $meta]);
    }

    /**
     * Recalcule la chaine depuis le debut (ou depuis l'ancre d'archive). Retourne
     * ['ok' => bool, 'lignes' => n, 'rupture' => id|null, 'raison' => texte|null].
     */
    function auditVerifierChaine(PDO $pdo, ?string $ancre = null, int $lot = 2000): array
    {
        $precedent = null;
        $n = 0;
        $dernierId = 0;
        $premiere = true;
        $st = $pdo->prepare("SELECT id, occurred_at, request_id, actor_id, actor_role, category, action, entity_type, entity_id, result,
                                    route, ip_prefixe, before_json, after_json, meta_json, prev_hash, row_hash
                             FROM audit_log WHERE id > ? ORDER BY id LIMIT " . (int) $lot);
        do {
            $st->execute([$dernierId]);
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($lignes as $l) {
                $n++;
                // Premiere ligne : zeros, ou dernier hachage du manifeste d'archive si le debut a ete archive
                $attendu = $premiere ? ($ancre ?? str_repeat('0', 64)) : $precedent;
                if (!hash_equals($attendu, $l['prev_hash'])) {
                    return ['ok' => false, 'lignes' => $n, 'rupture' => (int) $l['id'], 'raison' => 'lien avec la ligne precedente rompu'];
                }
                $l['occurred_at'] = substr($l['occurred_at'], 0, 23);
                if (!hash_equals(auditHacher($l), $l['row_hash'])) {
                    return ['ok' => false, 'lignes' => $n, 'rupture' => (int) $l['id'], 'raison' => 'contenu de la ligne modifie'];
                }
                $precedent = $l['row_hash'];
                $dernierId = (int) $l['id'];
                $premiere = false;
            }
        } while (count($lignes) === $lot);

        $tete = $pdo->query("SELECT last_id, last_hash FROM audit_chain_head WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if ($n > 0 && $tete && ((int) $tete['last_id'] !== $dernierId || !hash_equals($tete['last_hash'], (string) $precedent))) {
            return ['ok' => false, 'lignes' => $n, 'rupture' => $dernierId, 'raison' => 'tete de chaine differente de la derniere ligne (ligne supprimee a la fin ?)'];
        }
        return ['ok' => true, 'lignes' => $n, 'rupture' => null, 'raison' => null];
    }
}
