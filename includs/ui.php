<?php
/**
 * includs/ui.php : helpers de presentation partages (aucune logique metier, aucun acces base).
 * e(), ico(), formaterMontant(), montant(), dateFr(), badgeStatut(), initiales(), nettoyerPictogrammes().
 */

require_once __DIR__ . '/config_marche.php';

if (!function_exists('e')) {
    function e($valeur): string
    {
        return htmlspecialchars((string) ($valeur ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('ico')) {
    /**
     * Icone SVG en ligne. Sans $libelle elle est decorative (aria-hidden) ;
     * avec $libelle elle est annoncee (role="img" + aria-label).
     */
    function ico(string $nom, string $classe = '', ?string $libelle = null): string
    {
        static $traces = null;
        $traces ??= require __DIR__ . '/icones.php';
        $trace = $traces[$nom] ?? $traces['circle-help'];
        $a11y = $libelle === null
            ? 'aria-hidden="true" focusable="false"'
            : 'role="img" aria-label="' . e($libelle) . '"';
        return '<svg class="ico ' . e($classe) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" ' . $a11y . '>'
            . $trace . '</svg>';
    }
}

if (!function_exists('formaterMontant')) {
    /**
     * "12 500 FCFA" ou "12 500 KMF". Espace ordinaire, signe ASCII. $signe ajoute "+" aux
     * montants positifs. $marche impose la devise (marche du proprietaire du montant) ; sans lui,
     * la devise du marche de la page est utilisee. Le retour ne contient que des chiffres, des
     * espaces et le libelle : sur a afficher tel quel.
     */
    function formaterMontant($montant, bool $signe = false, bool $avecDevise = true, ?string $marche = null): string
    {
        $valeur = (float) ($montant ?? 0);
        $prefixe = $valeur < 0 ? '-' : ($signe && $valeur > 0 ? '+' : '');
        $decimales = fmod(abs($valeur), 1.0) >= 0.005 ? 2 : 0;
        $texte = $prefixe . number_format(abs($valeur), $decimales, ',', ' ');
        return $avecDevise ? $texte . ' ' . deviseLibelle($marche) : $texte;
    }
}

if (!function_exists('montant')) {
    /** Montant dans un <span class="montant"> (tabulaire, sans retour a la ligne). */
    function montant($valeur, bool $signe = false, string $classe = '', ?string $marche = null): string
    {
        return '<span class="montant ' . e($classe) . '">' . formaterMontant($valeur, $signe, true, $marche) . '</span>';
    }
}

if (!function_exists('dateFr')) {
    /**
     * Formats : 'long' (18 septembre 2026), 'court' (18/09/2026), 'heure' (18/09/2026 14:05),
     * 'jour' (Aujourd'hui, Hier ou 18 septembre 2026), 'jour_semaine' (vendredi 18 septembre 2026).
     */
    function dateFr($date = null, string $format = 'long'): string
    {
        static $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        static $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        try {
            $d = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date ?? 'now');
        } catch (Exception $e) {
            return '';
        }
        $long = (int) $d->format('j') . ' ' . $mois[(int) $d->format('n')] . ' ' . $d->format('Y');
        switch ($format) {
            case 'court':
                return $d->format('d/m/Y');
            case 'heure':
                return $d->format('d/m/Y H:i');
            case 'heure_seule':
                return $d->format('H:i');
            case 'jour':
                $ecart = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($d->format('Y-m-d')))->format('%r%a');
                return $ecart === 0 ? "Aujourd'hui" : ($ecart === -1 ? 'Hier' : $long);
            case 'jour_semaine':
                return $jours[(int) $d->format('w')] . ' ' . $long;
            default:
                return $long;
        }
    }
}

if (!function_exists('badgeStatut')) {
    /**
     * Pastille de statut. $contexte : commission, retrait, transaction, compte, vue, message.
     * Un code inconnu est affiche tel quel en neutre plutot que masque.
     */
    function badgeStatut(?string $code, string $contexte = 'transaction'): string
    {
        $code = strtolower(trim((string) $code));
        $table = [
            'commission'  => ['en_attente' => ['En attente', 'attente'], 'contacte' => ['Client contacté', 'attente'], 'colis_recu' => ['Colis reçu', 'info'], 'validee' => ['Créditée', 'succes'], 'annulee' => ['Annulée', 'danger']],
            'retrait'     => ['en_attente' => ['En attente', 'attente'], 'valide' => ['Payé', 'succes'], 'rejete' => ['Refusé', 'danger']],
            'transaction' => ['en_attente' => ['En attente', 'attente'], 'complete' => ['Effectuée', 'succes'], 'echoue' => ['Échouée', 'danger']],
            'compte'      => ['active' => ['Actif', 'succes'], 'suspended' => ['Suspendu', 'danger'], 'deleted' => ['Supprimé', 'neutre'], 'pending' => ['À vérifier', 'attente']],
            'vue'         => ['en_cours' => ['En cours', 'info'], 'termine' => ['Terminée', 'succes']],
            'message'     => ['non_lu' => ['Non lu', 'info'], 'lu' => ['Lu', 'neutre']],
            'stock'       => ['0' => ['Commission à envoyer', 'attente'], '1' => ['Commission envoyée', 'succes']],
            'moderation'  => ['brouillon' => ['Brouillon', 'neutre'], 'en_attente' => ['En attente de validation', 'attente'], 'approuve' => ['Publié', 'succes'], 'refuse' => ['Refusé', 'danger']],
            'boutique'    => ['en_attente' => ['En attente de validation', 'attente'], 'valide' => ['Validée', 'succes'], 'suspendu' => ['Suspendue', 'danger'], 'refuse' => ['Refusée', 'danger']],
            'commande'    => ['en_attente' => ['Nouvelle', 'attente'], 'contacte' => ['Client contacté', 'info'], 'colis_recu' => ['Colis reçu', 'info'], 'validee' => ['Validée', 'succes'], 'annulee' => ['Annulée', 'danger']],
            'produit'     => ['actif' => ['En vente', 'succes'], 'suspendu' => ['Suspendu', 'neutre'], 'termine' => ['Terminé', 'neutre']],
        ];
        [$libelle, $variante] = $table[$contexte][$code] ?? [ucfirst(str_replace('_', ' ', $code)) ?: 'Inconnu', 'neutre'];
        return '<span class="pastille pastille-' . $variante . '">' . e($libelle) . '</span>';
    }
}

if (!function_exists('initiales')) {
    function initiales(?string $nom): string
    {
        $mots = preg_split('/[\s\-]+/u', trim((string) $nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lettres = '';
        foreach (array_slice($mots, 0, 2) as $mot) {
            $lettres .= mb_strtoupper(mb_substr($mot, 0, 1));
        }
        return $lettres !== '' ? $lettres : 'MR';
    }
}

if (!function_exists('nettoyerPictogrammes')) {
    /**
     * Retire a l'affichage les emojis, fleches et tirets typographiques des textes deja en base
     * (anciennes notifications). Ne modifie jamais la base.
     */
    function nettoyerPictogrammes(?string $texte): string
    {
        $t = (string) $texte;
        $t = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}\x{FE0F}\x{20E3}\x{200D}]/u', '', $t);
        $t = preg_replace('/[\x{2190}-\x{21FF}\x{2794}\x{27A1}\x{2022}\x{25B2}-\x{25C6}]/u', '', $t);
        $t = preg_replace('/\s*[\x{2013}\x{2014}]\s*/u', ', ', $t);
        $t = str_replace([mb_chr(0x00A0), mb_chr(0x202F), mb_chr(0x2019), mb_chr(0x2026)], [' ', ' ', "'", '...'], $t);
        return trim(preg_replace('/\s{2,}/u', ' ', $t), " \t\n\r,");
    }
}

if (!function_exists('typeNotification')) {
    /** Icone de type deduite du texte d'une notification (commission, retrait, transfert, stock). */
    function typeNotification(?string $texte): string
    {
        $t = mb_strtolower((string) $texte);
        return match (true) {
            str_contains($t, 'retrait')    => 'banknote',
            str_contains($t, 'transfert')  => 'arrow-left-right',
            str_contains($t, 'commission'), str_contains($t, 'vente'), str_contains($t, 'commande') => 'hand-coins',
            str_contains($t, 'stock')      => 'package',
            str_contains($t, 'publicit')   => 'megaphone',
            default                        => 'bell',
        };
    }
}

if (!function_exists('actif')) {
    /** URL d'un fichier statique avec sa date de modification, pour vider le cache a chaque changement. */
    function actif(string $chemin): string
    {
        $v = @filemtime(__DIR__ . '/..' . $chemin);
        return $chemin . ($v ? '?v=' . $v : '');
    }
}

if (!function_exists('numeroWhatsapp')) {
    /** Numero au format wa.me (chiffres avec indicatif), quel que soit le marche. */
    function numeroWhatsapp(?string $tel): string
    {
        return normaliserNumero((string) $tel) ?? preg_replace('/\D/', '', (string) $tel);
    }
}
