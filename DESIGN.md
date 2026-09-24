# MonRevenu : système de design

Idée directrice : un relevé de compte lisible. Sobre, calme, précis ; les chiffres portent l'interface.
Source de vérité du code : `assets/src/input.css` (tokens et composants), `tailwind.config.js`, `includs/ui.php` (helpers), `includs/icones.php` (icônes), `assets/js/app-shell.js` (comportements). Référence visuelle : `dev/apercu-systeme.html` (local).

## Tokens

Définis une seule fois en variables CSS (canaux RGB) dans `:root` et `.dark`, exposés à Tailwind sous les mêmes noms. Aucune couleur brute dans les classes.

| Rôle | Clair | Sombre | Usage |
|---|---|---|---|
| `bg` | #F6F7F9 | #0E131B | fond de page |
| `surface` | #FFFFFF | #151C27 | cartes, barres, feuilles |
| `surface-2` | #F1F3F6 | #1B2330 | en-têtes de tableau, survol, fond d'image |
| `line` / `line-strong` | #E3E6EB / #CDD2DA | #263041 / #34405A | bordures / bordures de champ |
| `text` / `text-2` / `text-3` | #141A24 / #4A5565 / #5F6B7C | #E8ECF2 / #A9B3C2 / #8792A3 | texte principal / secondaire / méta |
| `primary` | #123F91 | #2F5DB8 | fond des boutons primaires |
| `primary-hover` | #0E3175 | #3A6BC9 | survol |
| `primary-soft` | #E9EFFA | #1B2A48 | entrée de navigation active, segment actif |
| `primary-ink` | #123F91 | #8FB0F0 | liens et textes de couleur primaire |
| `success` / `-soft` | #0F7453 / #E6F4EE | #4CC49A / #14302A | argent entrant, commission créditée, payé |
| `warning` / `-soft` | #8A5700 / #FBF1DC | #E3A33B / #3A2B10 | en attente |
| `danger` / `-soft` | #B3261E / #FBEAE8 | #F2877E / #3D1C1A | refus, erreur, suppression |

Primaire #123F91 (bleu du logo). Fichiers du logo et usages : `assets/img/README.md`. Le cyan du symbole n'apparaît que dans le logo.
Écarts avec les valeurs de départ du brief, pour tenir l'AA : `text-3` #6B7686 vers #5F6B7C (4,3 vers 5,05:1 sur `bg`), `success` #12805C vers #0F7453 (4,34 vers 5,09:1 sur son fond doux), `warning` #9A6200 vers #8A5700 (4,54 vers 5,43:1).
Contrastes vérifiés (4,5:1 minimum) : primaire sur blanc 9,79 ; `text-3` sur `surface-2` 4,87 ; toutes les paires sombres entre 5,2 et 15,7.

Élévation : aucune ombre sur les cartes (bordure de 1 px). Une seule ombre `shadow-pop` (0 8px 24px rgba(16,24,40,.12)) pour feuilles, popovers, toasts. Voile de feuille rgba(10,16,28,.5), sans flou.
Rayons : 6 (`rounded`, champs, boutons), 8 (`rounded-md`, cartes), 12 (`rounded-lg`, feuilles), `rounded-full` réservé aux pastilles, avatars, points.
Espacement : échelle de 4. Conteneur 1200 px, gouttières 16 (mobile) et 24 (desktop).
Mouvement : 150 ms, `cubic-bezier(0.16,1,0.3,1)`, sur couleur et translation. Feuille : montée depuis le bas (mobile), glissement de 12 px (desktop). `prefers-reduced-motion` coupe tout.

## Typographie

IBM Plex Sans 400, 500, 600 et IBM Plex Mono 400, auto-hébergées (`assets/fonts/`, sous-ensemble latin, `font-display: swap`, 2 fichiers préchargés : Sans 400 et 600). Aucune police distante.
Échelle : 12 (`xs`, méta, minimum), 14 (`sm`, interface dense), 16 (`base`, corps), 18 et 20 (titres de section), 24 à 32 (titres de page), H1 de landing en `clamp(28px, 5vw, 44px)`.
Montants : classe `.montant` (tabulaires, 600, sans retour à la ligne), alignés à droite dans les tableaux. Majuscules espacées uniquement dans les en-têtes de colonnes (12 px, 0,04em). Mono uniquement pour liens, références, codes.

## Composants (classes de `input.css`)

- Boutons `.btn` + `.btn-primaire | -secondaire | -discret | -danger`, `.btn-sm` (36 px), `.btn-icone` (toujours avec `aria-label`), `.btn-bloc`. Hauteur 44 par défaut. Chargement : `aria-busy="true"` et libellé "Envoi en cours" posés par `app-shell.js` à l'envoi, sans `disabled` pour que le `name` du bouton parte dans le POST.
- Champs `.champ`, `.champ-label` (toujours visible), `.champ-saisie` (48 px sur mobile, 16 px de texte), `.champ-aide`, `.champ-erreur` (sous le champ, avec icône, dit quoi faire), `.champ-groupe` + `.champ-prefixe`.
- Surfaces `.carte`, `.carte-entete`, `.section-titre`, `.page-titre`, `.meta`.
- Indicateurs `.indicateurs > .indicateur` (libellé, valeur tabulaire, note factuelle). Pas de variation sans donnée.
- Pastilles : `badgeStatut($code, $contexte)`. Texte et point, jamais la couleur seule. Contextes : commission (En attente, Créditée, Annulée), retrait (En attente, Payé, Refusé), transaction, compte (Actif, Suspendu), vue, message, stock.
- Tableau `.tableau` (+ `.tableau-empile` : cartes sous 640 px, chaque `td` porte `data-label`), en-tête collant, lignes de 48, `.col-montant` à droite.
- Ligne de transaction `.ligne-tx` (icône de type, libellé, heure et référence mono, montant signé, statut). Regroupement par jour avec `dateFr($d, 'jour')`.
- Feuille modale : `<dialog class="feuille">` natif ouvert par `data-ouvrir="id"`, fermé par `data-fermer`, Échap ou clic sur le voile. Focus piégé par `showModal()`. Feuille en bas sous 640 px, centrée au-dessus.
- Récapitulatif `.recap > .recap-ligne` (dt, dd), `.recap-total`.
- Alertes `.alerte-succes | -attention | -danger | -info` ; toasts `MR.toast(texte)` (4 s, `aria-live`).
- État vide `.vide` (icône à trait 40 px, titre concret, phrase, action). Squelette `.squelette`.
- Onglets `.onglets > .onglet[aria-selected]`, filtre de période `.segments > .segment[aria-pressed]`.
- Lien copiable `.lien-copie` ; boutons `data-copier` ("Lien copié" 2 s), `data-partager` (`navigator.share`, sinon WhatsApp), `data-whatsapp` (wa.me direct).
- Carte produit `.produit` (image carrée `contain` sur `surface-2`, nom sur 2 lignes, prix, `.produit-commission`, Copier le lien + Partager).
- Avatar `.avatar` avec `initiales()`, fond neutre.
- Coquille : `.barre-laterale` (232 px, à partir de 1024), `.nav-lien[aria-current=page]` (fond primaire doux, trait de 2 px), `.entete-app`, `.onglets-bas` (5 entrées, 56 px + zone de sécurité, sous 1024), `.liste-nav` pour la feuille "Plus".
- Accordéon `details.accordeon`.

## Icônes

Lucide 0.469 (ISC) et WhatsApp de Simple Icons (CC0), tracés dans `includs/icones.php`. `ico('nom', 'classe', 'libellé facultatif')` : trait 1,75, 20 px par défaut, `ico-16`, `ico-24` (navigation), `ico-40` (états vides). Décoratives par défaut (`aria-hidden`), `role="img"` + `aria-label` si un libellé est passé.

## Helpers PHP (`includs/ui.php`)

`e()`, `ico()`, `formaterMontant($m, $signe, $avecDevise, $marche)` ("12 500 FCFA" ou "12 500 KMF" selon le marché, `includs/config_marche.php`), `montant()` (span tabulaire), `dateFr($d, 'long|court|heure|jour|jour_semaine')`, `badgeStatut()`, `initiales()`, `nettoyerPictogrammes()` (affichage des anciennes notifications), `typeNotification()`.

## Performance (budget, page d'accueil)

Mesuré en lecture seule sur la production le 24/09/2026 (`curl`, sans session ni cookie, cas le
plus défavorable pour un nouveau visiteur) :

| Ressource | Poids transféré (compressé) |
|---|---|
| HTML | ~8,8 Ko |
| CSS (`app.css`) | ~8,2 Ko |
| JS (`app-shell.js` + `public.js`) | ~5,7 Ko |
| Polices (2 fichiers woff2) | ~46,8 Ko |
| Icônes/favicon (jeu `assets/img/favicon/`) et symbole SVG | voir `assets/img/README.md` |
| Photo vitrine (webp, déjà optimisée) | ~40 Ko |
| **Total, premier chargement** | **~136 Ko** |

Budget retenu : rester sous 200 Ko transférés pour le premier chargement de la page d'accueil,
sous 60 Ko pour le CSS et le JS combinés. Toute nouvelle dépendance front doit être mesurée avant
merge si elle dépasse 20 Ko.

Point trouvé pendant la mesure, hors poids réseau : la détection du pays visiteur
(`includs/geoip.php`) appelait une API externe avec un délai de 3 secondes avant le premier rendu
de la page pour tout visiteur sans session ; réduit à 1 seconde (0,5 s de connexion). Les images de
produits envoyées par l'administration ou un commerçant sont toutes re-encodées en WebP
(1200 px maximum, métadonnées retirées) à l'envoi ; `tools/generer-miniatures-webp.php` convertit
les images plus anciennes (voir `dev/lot3/RESULTATS_I.md`).

## Règles

- Rédaction : vouvoiement, phrases courtes, verbe d'action sur les boutons, erreurs qui disent quoi faire, aucun point d'exclamation, aucune promesse de gain, dates en français.
- Interdits : emoji, flèches et coches en caractères, tirets cadratins, espaces insécables, guillemets courbes, dégradés, flou, halos, ombres lourdes, surtitres au-dessus des titres, grilles de cartes icône + titre + phrase, chiffres inventés. Vérification : `php dev/verifier-motifs-ia.php --commentaires`.
- Accessibilité : `lang="fr"`, lien d'évitement, anneau de focus 2 px décalé de 2 px, cibles de 44 px, labels visibles, contraste AA en clair et en sombre.
- Mode sombre : espace connecté et admin seulement (classe `dark` sur `<html>`, clé `localStorage` "theme"), jamais sur la landing ni la page produit publique.
