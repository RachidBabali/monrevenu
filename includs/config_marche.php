<?php
/**
 * includs/config_marche.php : un seul endroit pour tout ce qui depend du pays.
 *
 * MonRevenu fonctionne sur deux marches separes : le Senegal (SN, franc CFA de l'UEMOA, XOF)
 * et les Comores (KM, franc comorien, KMF). Les deux monnaies sont arrimees a l'euro a taux fixe
 * (1 EUR = 655,957 XOF et 1 EUR = 491,96775 KMF, soit 100 FCFA = 75 KMF), mais MonRevenu ne
 * convertit jamais : un solde, un prix, une commission et un retrait restent dans la devise du
 * marche de leur proprietaire. Aucune somme ne melange les deux devises.
 *
 * Longueurs de numero verifiees : Senegal, indicatif 221, numero national de 9 chiffres ;
 * Comores, indicatif 269, numero national de 7 chiffres. La validation se fait par longueur,
 * jamais par liste de prefixes, pour ne pas rejeter un operateur nouveau ou peu connu.
 *
 * Les montants (seuil de commission, commissions, minimum de retrait) et les moyens de retrait
 * sont des valeurs a confirmer : voir dev/lot3/NOTES.md.
 */

/** Marche utilise quand rien ne permet de choisir (visiteur inconnu, compte sans pays). */
if (!defined('MARCHE_DEFAUT')) define('MARCHE_DEFAUT', 'SN');

if (!function_exists('marches')) {

    function marches(): array
    {
        static $marches = null;
        if ($marches !== null) return $marches;
        $marches = [
            'SN' => [
                'code'               => 'SN',
                'nom'                => 'Sénégal',
                'indicatif'          => '221',
                'longueur_nationale' => 9,
                'devise'             => 'XOF',
                'devise_libelle'     => 'FCFA',
                'retrait_minimum'    => 1000,
                'moyens_retrait'     => ['Wave', 'Orange Money', 'Free Money', 'Expresso E-Money', 'Wizall Money', 'Joni Joni'],
                'commission'         => ['seuil' => 10000, 'basse' => 500, 'haute' => 1000],
                'exemple_numero'     => '77 123 45 67',
            ],
            'KM' => [
                'code'               => 'KM',
                'nom'                => 'Comores',
                'indicatif'          => '269',
                'longueur_nationale' => 7,
                'devise'             => 'KMF',
                'devise_libelle'     => 'KMF',
                'retrait_minimum'    => 1000,
                'moyens_retrait'     => ['Mvola', 'Huri Money'],
                'commission'         => ['seuil' => 10000, 'basse' => 500, 'haute' => 1000],
                'exemple_numero'     => '32 12 345',
            ],
        ];
        return $marches;
    }

    /** Code de marche valide, sinon null. */
    function marcheValide($code): ?string
    {
        $code = strtoupper(trim((string) $code));
        return isset(marches()[$code]) ? $code : null;
    }

    /** Configuration complete d'un marche ; le marche par defaut si le code est inconnu. */
    function marche(?string $code = null): array
    {
        $code = marcheValide($code) ?? marcheValide(marcheCourant()) ?? MARCHE_DEFAUT;
        return marches()[$code];
    }

    /** Libelle affiche de la devise d'un marche : FCFA ou KMF. */
    function deviseLibelle(?string $codeMarche = null): string
    {
        return marche($codeMarche)['devise_libelle'];
    }

    /** Code ISO de la devise d'un marche : XOF ou KMF. */
    function deviseIso(?string $codeMarche = null): string
    {
        return marche($codeMarche)['devise'];
    }

    /** Marche correspondant a un code ISO de devise (XOF, KMF). */
    function marcheDeDevise(?string $iso): ?string
    {
        foreach (marches() as $code => $m) {
            if (strtoupper((string) $iso) === $m['devise']) return $code;
        }
        return null;
    }

    /**
     * Marche d'un compte : son pays quand c'est un marche connu, sinon l'indicatif de son
     * numero, sinon le marche par defaut. Le pays vient de la detection a l'inscription et
     * peut valoir autre chose (un membre senegalais inscrit depuis la France) : le numero
     * reste alors la meilleure indication.
     */
    function marcheDeCompte(?array $compte): string
    {
        if (!$compte) return MARCHE_DEFAUT;
        $parPays = marcheValide($compte['pays_code'] ?? null);
        if ($parPays !== null) return $parPays;
        $parNumero = marcheDeNumero((string) ($compte['phone'] ?? ''));
        return $parNumero ?? MARCHE_DEFAUT;
    }

    /** Marche deduit d'un numero enregistre (chiffres avec indicatif), sinon null. */
    function marcheDeNumero(string $numero): ?string
    {
        $chiffres = preg_replace('/\D/', '', $numero);
        foreach (marches() as $code => $m) {
            if (str_starts_with($chiffres, $m['indicatif'])
                && strlen($chiffres) === strlen($m['indicatif']) + $m['longueur_nationale']) {
                return $code;
            }
        }
        return null;
    }

    /**
     * Numero saisi ramene au format de stockage (indicatif + numero national, chiffres seuls),
     * ou null s'il ne correspond a aucun marche. Accepte 00221, +221, 221 et le numero local.
     * $marcheImpose limite la validation a un seul marche (commande sur un produit d'un marche).
     */
    function normaliserNumero(string $saisie, ?string $marcheImpose = null): ?string
    {
        $n = preg_replace('/\D/', '', $saisie);
        if ($n === '') return null;
        $n = preg_replace('/^00/', '', $n);
        $candidats = $marcheImpose !== null && marcheValide($marcheImpose) !== null
            ? [marcheValide($marcheImpose) => marches()[marcheValide($marcheImpose)]]
            : marches();
        foreach ($candidats as $m) {
            $complet = strlen($m['indicatif']) + $m['longueur_nationale'];
            if (strlen($n) === $complet && str_starts_with($n, $m['indicatif'])) return $n;
            if (strlen($n) === $m['longueur_nationale']) return $m['indicatif'] . $n;
        }
        return null;
    }

    /** Numero enregistre presente a l'ecran : "+221 77 123 45 67", "+269 32 12 345". */
    function afficherNumero(?string $numero): string
    {
        $n = preg_replace('/\D/', '', (string) $numero);
        $code = marcheDeNumero($n);
        if ($code === null) return (string) $numero;
        $m = marches()[$code];
        $local = substr($n, strlen($m['indicatif']));
        $groupes = $m['longueur_nationale'] === 9
            ? [substr($local, 0, 2), substr($local, 2, 3), substr($local, 5, 2), substr($local, 7, 2)]
            : [substr($local, 0, 2), substr($local, 2, 2), substr($local, 4)];
        return '+' . $m['indicatif'] . ' ' . implode(' ', array_filter($groupes, fn($g) => $g !== ''));
    }

    /** Commission due a l'affilie pour un prix, selon la regle du marche. */
    function commissionMarche(float $prix, ?string $codeMarche = null): int
    {
        $regle = marche($codeMarche)['commission'];
        return $prix <= $regle['seuil'] ? $regle['basse'] : $regle['haute'];
    }

    /** Minimum de retrait du marche. */
    function retraitMinimum(?string $codeMarche = null): int
    {
        return (int) marche($codeMarche)['retrait_minimum'];
    }

    /** Moyens de retrait proposes sur le marche. */
    function moyensRetrait(?string $codeMarche = null): array
    {
        return marche($codeMarche)['moyens_retrait'];
    }

    /**
     * Marche de la page en cours. Dans l'ordre : marche pose par la page (compte connecte,
     * produit affiche), cookie du visiteur, pays detecte, marche par defaut.
     */
    function marcheCourant(): string
    {
        if (isset($GLOBALS['__marche_courant'])) return $GLOBALS['__marche_courant'];
        $GLOBALS['__marche_courant'] = marcheValide($_COOKIE['marche'] ?? null)
            ?? marcheValide($_SESSION['geo_pays_code'] ?? null)
            ?? MARCHE_DEFAUT;
        return $GLOBALS['__marche_courant'];
    }

    /** Impose le marche de la page (compte connecte, fiche produit). */
    function definirMarcheCourant(?string $code): string
    {
        $valide = marcheValide($code);
        if ($valide !== null) $GLOBALS['__marche_courant'] = $valide;
        return marcheCourant();
    }

    /**
     * Condition SQL (fragment pret a inserer dans un WHERE, alias "vp" attendu sur
     * vendeur_produits) qui limite le catalogue au marche donne. Une ligne sans devise
     * (anterieure a la migration 010) est supposee du marche par defaut (SN, hypothese
     * documentee dans dev/lot3/NOTES.md, valable tant qu'aucun produit n'appartient a un
     * proprietaire du marche KM). Les deux valeurs inserees viennent uniquement de
     * marches() (jamais d'une saisie libre) : aucun risque d'injection.
     */
    function catalogueFiltreMarche(?string $codeMarche = null): string
    {
        $code = marcheValide($codeMarche) ?? MARCHE_DEFAUT;
        $devise = deviseIso($code);
        $devise_defaut = deviseIso(MARCHE_DEFAUT);
        if ($devise === $devise_defaut) {
            return "(vp.devise = '{$devise}' OR vp.devise IS NULL)";
        }
        return "(vp.devise = '{$devise}')";
    }

    /**
     * Expression SQL de la devise effective d'une ligne (colonne devise si renseignee,
     * sinon celle du marche du proprietaire) : a utiliser dans un SELECT/GROUP BY pour ne
     * jamais additionner XOF et KMF. $aliasLigne porte la colonne devise, $aliasProprietaire
     * la colonne pays_code de users_monrevenu (peut etre le meme alias si la ligne est deja
     * un compte). Deux valeurs possibles seulement (XOF/KMF) : aucun risque d'injection.
     */
    function deviseEffectiveSql(string $aliasLigne, string $aliasProprietaire): string
    {
        $devise_defaut = deviseIso(MARCHE_DEFAUT);
        $devise_km = deviseIso('KM');
        return "COALESCE({$aliasLigne}.devise, IF({$aliasProprietaire}.pays_code = 'KM', '{$devise_km}', '{$devise_defaut}'))";
    }
}
