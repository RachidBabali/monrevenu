# MonRevenu : documentation technique

Ce fichier décrit les briques ajoutées au lot 2 (septembre 2026) : journal d'audit, mouvements de solde,
espace commerçants, notifications push, santé et conservation. Le système de design est décrit dans `DESIGN.md`,
le produit dans `PRODUCT.md`.

## Journal d'audit (`includs/audit.php`)

Table `audit_log`, en ajout seulement. Chaque ligne porte le hachage de la précédente (`prev_hash`) et le sien
(`row_hash`), calculés en HMAC-SHA256 avec la clé `AUDIT_HMAC_KEY` du `.env`. La tête de chaîne
(`audit_chain_head`, ligne 1) est verrouillée en `FOR UPDATE` pendant l'ajout, et se verrouille toujours **en
dernier** : aucun appel réseau (notification, push) ne doit avoir lieu tant qu'elle est tenue.

```php
require_once __DIR__ . '/audit.php';

// Argent et administration : si le journal échoue, l'action est annulée
auditCritique($pdo, [
    'category' => 'argent', 'action' => 'commission_credit',
    'entity_type' => 'transaction', 'entity_id' => $txId,
    'before' => ['balance' => $avant], 'after' => ['balance' => $apres],
    'meta' => ['reference' => 'VENTE-12'],
]);

// Authentification, lecture, système : ne bloque jamais l'action
auditInfo($pdo, ['category' => 'auth', 'action' => 'connexion', 'entity_type' => 'utilisateur', 'entity_id' => $id]);
```

Catégories : `auth`, `argent`, `produit`, `commande`, `admin`, `systeme`, `compte`. Résultats : `ok`, `refus`, `echec`.

Ne va jamais dans le journal : mot de passe, hachage, code de vérification, jeton, clé, contenu de message.
Téléphone et e-mail sont masqués (`journalMasquerTelephone()`, `journalMasquerEmail()`). L'IP tronquée
(`/24`, `/48`) entre dans le hachage ; l'IP complète et le user-agent sont hors hachage, ce qui permet de les
effacer après 90 jours sans casser la chaîne.

Aides : `auditCsrf()` (jeton invalide), `auditEnvoi()` (e-mail, WhatsApp), `auditInfoLimite()` (événement
répétitif, une ligne par période), `auditVerifierChaine()` (recalcul complet, renvoie la première rupture).
Une écriture SQL volontairement hors journal porte le commentaire `audit:exclu <raison>` :
`php tools/couverture-audit.php` liste toutes les écritures et échoue si l'une n'est ni couverte ni exclue.

## Mouvements de solde (`includs/argent.php`)

Tout changement de `users_monrevenu.balance` passe par `mouvementSolde()`, appelé **dans** une transaction ouverte
par l'appelant :

```php
$pdo->beginTransaction();
mouvementSolde($pdo, $userId, $montant, 'commission', 'VENTE-' . $id, 'complete',
    'Commission sur vente #' . $id, 'commission_credit', ['commande_id' => $id], $acteurId);
$pdo->commit();
```

La fonction verrouille l'utilisateur, refuse un solde négatif, écrit la ligne `transactions_monrevenu` avec
`balance_before`, `balance_after`, `actor_id` et `audit_id`, puis la ligne de journal. La colonne `reference` est
UNIQUE : une seconde écriture avec la même référence échoue, ce qui rend l'opération idempotente.

## Espace commerçants (`includs/commercant.php`, `commercant/`)

- Rôle `commercant` dans `users_monrevenu.role`, profil de boutique dans `commercants_profils`.
- Les produits restent dans `vendeur_produits` : `vendeur_id` est le propriétaire (commerçant ou administrateur),
  `created_by` l'auteur. Attention : dans `vendeur_ventes`, `vendeur_id` désigne l'**affilié** qui touche la
  commission ; les commandes d'un commerçant se trouvent par `produit_id` puis `vendeur_produits.vendeur_id`.
- Modération : `brouillon`, `en_attente`, `approuve`, `refuse`. Un produit publié dont le nom, le prix ou l'image
  change repasse en validation, sauf si la boutique a l'option de publication directe (`confiance`).
- Catalogue : constantes `CATALOGUE_JOINTURE` et `CATALOGUE_CONDITION`, à utiliser partout où des produits sont
  listés (produit actif, approuvé, boutique validée).
- Images : `includs/image_produit.php` vérifie le type réel, borne la taille et les dimensions, ré-encode en WebP
  (JPEG en secours) à 1200 px, retire les métadonnées et range le fichier sous `merchants/<id>/` sur R2.
- Commandes : le commerçant avance jusqu'à `colis_recu` ou annule avec un motif (`COMMERCANT_TRANSITIONS`) ;
  seul un administrateur passe une vente à `validee`, ce qui crédite la commission de l'affilié.
- Dette : `detteCommercant()` = commissions des ventes validées moins les règlements (`commercant_reglements`).
  MonRevenu ne prélève rien : les règlements sont saisis à la main par un administrateur.

## Notifications (`includs/notifications.php`, `sw.js`)

`envoyerNotification($pdo, $userId, $message, $titre, $lien, 'MonRevenu', ['type' => 'argent'])` écrit le message
en base et envoie le push. À appeler **après** le commit. La charge utile porte un titre court, un aperçu d'une
ligne, une étiquette par type d'événement (les notifications d'un même type se remplacent), un horodatage, le
badge monochrome, une image facultative et le compteur de messages non lus.
Les cas où le push est ignoré (clés VAPID absentes, aucun abonnement) sont journalisés.
Le bloc `sections/activer_notifications.php` gère l'activation, toujours déclenchée par un clic.

## Santé, audit et conservation

- `includs/sante.php` : contrôles de l'application, de la base (`SHOW TABLE STATUS`, jamais `information_schema`),
  du stockage R2, des services externes et des dépendances. Aucune valeur de variable d'environnement n'est
  renvoyée, seulement leur présence.
- `includs/rapprochement.php` : rapprochement des soldes, signale sans jamais corriger.
- `admin/audit.php` : page d'audit en huit onglets, réservée au rôle `admin`, consultation et export journalisés.
- `admin/cron/snapshot.php` : tâche planifiée quotidienne (ligne de commande seulement), écrit dans
  `health_snapshots` et ancre le dernier hachage du journal dans `storage/audit/ancre-journal.json`.
- `tools/archiver-journal.php` : efface l'IP complète après 90 jours, archive les lignes de plus de 24 mois
  (`.jsonl.gz` + manifeste) et ne supprime qu'après relecture de l'archive.
- `tools/composer-audit.sh` : contrôle local des vulnérabilités, résultat déposé dans
  `storage/audit/composer-audit.json` et affiché dans l'onglet Dépendances.

## Variables d'environnement ajoutées

| Variable | Rôle | Valeur par défaut |
|---|---|---|
| `AUDIT_HMAC_KEY` | clé du journal d'audit, à ne jamais changer | aucune (obligatoire) |
| `QUANTITE_MAX_COMMANDE` | plafond de quantité sur une commande | 10 |
| `COMMERCANT_MAX_PRODUITS_JOUR` | créations de produits par jour et par commerçant | 20 |
| `COMMERCANT_MAX_PRODUITS` | produits par commerçant | 200 |
| `JOURNAL_CONSERVATION_MOIS` | conservation du journal | 24 |
| `JOURNAL_IP_JOURS` | délai avant effacement de l'IP complète | 90 |

## Dossiers non servis par le web

`dev/`, `migrations/`, `storage/`, `tools/` et `.kilo/` sont refusés par le `.htaccess` racine ;
`storage/` et `tools/` ont en plus leur propre `.htaccess`. Les scripts de `tools/` et `admin/cron/`
refusent de s'exécuter autrement qu'en ligne de commande.
