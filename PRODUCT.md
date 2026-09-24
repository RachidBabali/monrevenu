# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Affiliés : 18 à 35 ans, Sénégal et Comores (deux marchés séparés, chacun dans sa monnaie), téléphone Android d'entrée de gamme, 4G irrégulière, forfait data limité. Ils choisissent un produit dans le catalogue, copient leur lien et le partagent surtout sur WhatsApp, puis suivent leurs commissions et demandent un retrait.
- Acheteurs : ouvrent un lien d'affiliation reçu sur WhatsApp, commandent sans créer de compte (nom, téléphone, adresse), sont rappelés pour la livraison.
- Agents revendeurs : détiennent un stock physique confié par l'admin et déclarent leurs ventes.
- Administrateur : saisit les produits (y compris pour le compte d'un commerçant), valide les commandes et les retraits, gère utilisateurs, publicités et stocks.

## Product Purpose

Plateforme d'affiliation : un affilié gagne une commission sur chaque vente validée réalisée avec son lien, créditée sur son portefeuille MonRevenu, puis retirée. La commission est sa part (30 %) d'un supplément calculé par tranches sur le prix net du commerçant ; l'affilié ne voit jamais le détail du calcul, seulement le prix affiché (une fois son numéro vérifié) et son gain. Réussite : l'affilié comprend en quelques secondes combien il gagne par produit, partage son lien en un geste et voit clairement l'état de ses commissions et retraits.

## Positioning

MonRevenu est un intermédiaire : des commerçants externes fournissent les produits, MonRevenu publie le catalogue, reçoit les commandes, organise la livraison et paie les affiliés. L'affilié n'a ni stock ni encaissement à gérer.

## Operating Context

- Commande : formulaire court sur la page produit publique `/produit/{slug}/{jeton}`, sans paiement en ligne.
- Paiement acheteur : à la livraison. Paiement mobile money vers un numéro MonRevenu (Wave, Orange Money, Yas, Mvola selon configuration admin) avec envoi de preuve : prévu, pas encore implémenté, ne pas l'afficher.
- Partage : WhatsApp en priorité.
- Notifications : messagerie interne, WhatsApp, email, Web Push.

## Capabilities and Constraints

- PHP procédural sur Hostinger mutualisé, pas de Node en production, Cloudflare devant, images sur `cdn.monrevenu.xyz` (R2).
- Commission : part de l'affilié dans un supplément progressif par tranches, barème et répartition stockés en base et éditables dans l'administration (`includs/commission.php`, page Admin > Commissions). Chaque commande garde une copie figée du calcul. Les anciens paliers fixes (500 / 1 000) n'existent plus.
- Marchés : Sénégal (XOF, affiché FCFA) et Comores (KMF), sans conversion (`includs/config_marche.php`). Retrait minimum 1 000 ; moyens de retrait par marché : Wave, Orange Money, Free Money (Sénégal), Mvola (Comores). Paiement des commissions manuel, validé par l'équipe.
- Accès selon la vérification du compte : non vérifié = prix et lien d'affiliation masqués côté serveur (commission visible) et actions bloquées ; sans numéro = image et nom du produit seulement ; vérifié = accès complet.
- Mot de passe : 8 à 64 caractères avec lettre, chiffre et caractère spécial (`includs/mot_de_passe.php`).
- Aucun suivi de clics, aucune catégorie de produit en base.
- Fonctions secondaires : publicités rémunérées, quiz, annuaire de prestataires, messagerie, stock revendeur. Formations, publicités, portefeuille avancé et quiz sont en pause. La page Formations côté affilié est abandonnée.

## Brand Commitments

- Nom : MonRevenu. Logo par défaut : symbole bleu (cercle) avec flèche noire sur fond blanc ; la version fond noir n'apparaît que dans le thème sombre. Fichiers et usages : `assets/img/README.md`. L'ancien `Logo/logo.jpg` n'est plus la référence.
- Voix : vouvoiement, sobre, factuel, aucune promesse de gain, aucun point d'exclamation.

## Evidence on Hand

- Aucun témoignage, chiffre d'activité, partenaire ou avis réel. Rien de tout cela ne doit apparaître.
- Pages légales existantes : `conditions.php`, `confidentialite.php`, `suppression-donnees.php`. Contact : contact@monrevenu.xyz, WhatsApp Business (`WHATSAPP_BUSINESS_DISPLAY_NUMBER`).

## Product Principles

1. Les chiffres sont vrais ou absents : jamais de donnée inventée ni de fonctionnalité annoncée qui n'existe pas.
2. Un geste pour partager : le lien est prêt, on le copie ou on l'envoie sur WhatsApp.
3. L'argent est lisible : solde, statut de chaque commission et de chaque retrait, minimum et délai toujours visibles.
4. Léger avant tout : chaque octet compte sur un forfait data limité.

## Accessibility & Inclusion

Contraste WCAG AA, cibles tactiles de 44 px, lisibilité sur petits écrans de 360 px en plein soleil, français simple.
