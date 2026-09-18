# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Affiliés : 18 à 35 ans, Sénégal et Mali (héritage Comores dans le code), téléphone Android d'entrée de gamme, 4G irrégulière, forfait data limité. Ils choisissent un produit dans le catalogue, copient leur lien et le partagent surtout sur WhatsApp, puis suivent leurs commissions et demandent un retrait.
- Acheteurs : ouvrent un lien d'affiliation reçu sur WhatsApp, commandent sans créer de compte (nom, téléphone, adresse), sont rappelés pour la livraison.
- Agents revendeurs : détiennent un stock physique confié par l'admin et déclarent leurs ventes.
- Administrateur : saisit les produits (y compris pour le compte d'un commerçant), valide les commandes et les retraits, gère utilisateurs, publicités et stocks.

## Product Purpose

Plateforme d'affiliation : un affilié gagne une commission fixe sur chaque vente validée réalisée avec son lien, créditée sur son portefeuille MonRevenu, puis retirée. Réussite : l'affilié comprend en quelques secondes combien il gagne par produit, partage son lien en un geste et voit clairement l'état de ses commissions et retraits.

## Positioning

MonRevenu est un intermédiaire : des commerçants externes fournissent les produits, MonRevenu publie le catalogue, reçoit les commandes, organise la livraison et paie les affiliés. L'affilié n'a ni stock ni encaissement à gérer.

## Operating Context

- Commande : formulaire court sur la page produit publique `/produit/{slug}/{jeton}`, sans paiement en ligne.
- Paiement acheteur : à la livraison. Paiement mobile money vers un numéro MonRevenu (Wave, Orange Money, Yas, Mvola selon configuration admin) avec envoi de preuve : prévu, pas encore implémenté, ne pas l'afficher.
- Partage : WhatsApp en priorité.
- Notifications : messagerie interne, WhatsApp, email, Web Push.

## Capabilities and Constraints

- PHP procédural sur Hostinger mutualisé, pas de Node en production, Cloudflare devant, images sur `cdn.monrevenu.xyz` (R2).
- Commission : 500 si prix inférieur ou égal à 10 000, sinon 1 000 (`includs/affiliation_helpers.php`).
- Retrait minimum 1 000, transfert minimum 100. Moyen de retrait présent dans le code : Mvola seulement (décision ouverte).
- Devise affichée : FCFA (décision du brief), montants stockés non convertis.
- Aucun suivi de clics, aucune catégorie de produit en base.
- Fonctions secondaires : publicités rémunérées, quiz, annuaire de prestataires, messagerie, stock revendeur. La page Formations côté affilié est abandonnée.

## Brand Commitments

- Nom : MonRevenu. Logo : `Logo/logo.jpg` (mot-symbole bleu profond, symbole cyan).
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
