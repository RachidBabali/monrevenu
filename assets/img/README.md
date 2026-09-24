# Identité visuelle : quel fichier pour quel usage

Logo par défaut : symbole bleu (cercle) avec flèche noire sur fond blanc. Les quatre variantes existent en
SVG, WebP et JPEG ; le format se choisit selon ce que le contexte de rendu sait afficher. Correspondance des
variantes (la numérotation des fichiers n'est pas la même selon le format) :

| Variante | SVG | WebP | JPEG |
|---|---|---|---|
| Flèche noire, fond blanc (**par défaut**) | `monrevenulog4` | `monrevenulogo2` | `Gemini_..._56u215` |
| Flèche bleue, fond blanc | `monrevenulog3` | `monrevenulogo1` | `Gemini_..._4b1dxv` |
| Fond noir (avec slogan) | `monrevenulog2` | `monrevenulogo3` | `Gemini_..._t2dnzq` |
| Fond bleu, logo blanc | `monrevenulog1` | `monrevenulogo4` | `Gemini_..._xqcj3i` |

| Contexte | Fichier | Format et raison |
|---|---|---|
| En-têtes, barres latérales, pieds de page, pages publiques (28 à 32 px) | `svg/monrevenu-marque.svg` | **SVG** : reste net à toutes les tailles et écrans (mise à l'échelle sans perte), léger, pas de version 2x à maintenir. C'est le symbole seul (recadrage du logo `svg/monrevenulog4.svg`, sans le nom, illisible à cette taille). |
| Grande zone d'affichage du logo complet, fond clair | `svg/monrevenulog4.svg` (par défaut), `svg/monrevenulog3.svg` (flèche bleue) | **SVG** : mise à l'échelle sans perte. |
| Fond bleu ou fond sombre (bandeaux, écrans de lancement) | `svg/monrevenulog1.svg` (fond bleu), `svg/monrevenulog2.svg` (fond noir, avec le slogan) | **SVG**. |
| Bannières et visuels raster à insérer dans des pages | `webp/monrevenulogo1..4.webp` | **WebP** sans perte avec transparence : bien plus léger que le PNG à qualité égale, pris en charge par tous les navigateurs récents. |
| E-mails (`includs/email_sender.php`) | `jpeg/monrevenu-email.jpg` (symbole 96 px, affiché à 32 px) | **JPEG** : les clients de messagerie (Outlook surtout) n'affichent ni le SVG ni toujours le WebP ; le JPEG passe partout. Fond blanc assumé (les e-mails sont sur fond blanc). |
| Aperçu de partage (Open Graph : WhatsApp, Facebook, etc.) | `jpeg/og-image.jpg` (1200 x 630) et repli quand un produit n'a pas d'image | **JPEG** : les robots de partage ne lisent pas tous le WebP ni le SVG. |
| Sources d'origine (retouche, impression) | `jpeg/Gemini_Generated_Image_*.jpeg` | **JPEG** haute résolution, non servis aux visiteurs. |
| Onglet du navigateur | `favicon/favicon.ico`, `favicon/favicon-32x32.png`, `favicon/favicon-16x16.png` | Fichiers **favicon** dédiés, aux tailles exactes attendues. Le jeu `favicon-sombre/` est proposé aux navigateurs en thème sombre (`media="(prefers-color-scheme: dark)"`). |
| Icône iPhone / iPad | `favicon/apple-touch-icon.png` (180 px) | Fichier dédié : iOS ne lit pas le SVG pour cette icône. |
| Application installée (PWA), `manifest.json` | `favicon/android-chrome-192x192.png`, `favicon/android-chrome-512x512.png`, `favicon/android-chrome-512x512-maskable.png` | PNG aux tailles exigées par les navigateurs. La version « maskable » a une marge de sécurité de 10 % pour résister aux découpes rondes ou arrondies d'Android. |
| Notifications push (`sw.js`) | icône : `favicon/android-chrome-192x192.png`, pastille : `favicon/badge-96.png` | Android exige pour la pastille une silhouette monochrome avec transparence : `badge-96.png` est le symbole en blanc sur fond transparent. |

Remarques
- Le jeu `favicon-sombre/` est inclus tel quel ; ses grandes icônes (`apple-touch-icon.png`, `android-chrome-*`) et
  les variantes « fond noir » (SVG, WebP et JPEG) portent la phrase « Votre partage prut devenir un revenu ! » : coquille à
  corriger à la source (« peut ») avant tout usage public. Elles ne sont pas utilisées par le site aujourd'hui.
- Le service worker met les fichiers de `/assets/` en cache : à chaque remplacement d'un fichier de cette
  page, changer `CACHE_NAME` dans `sw.js`.
- Les anciens PNG (`logo-64`, `icon-192`, `icon-512`, `icon-512-maskable`, `apple-touch-icon`, `favicon-32`,
  `badge-96`, `produit-placeholder`) sont supprimés : plus aucune référence dans le code.
