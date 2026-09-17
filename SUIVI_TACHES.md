# Suivi des tâches — MonRevenu

Dernière analyse du code : **2026-09-17** (branche `main`, commit `017718a`).
Ce fichier est la liste de travail. Le contexte détaillé (architecture, flux d'argent, tables) est gardé dans la mémoire de l'assistant — demander une explication si besoin plutôt que dupliquer ici.

Légende : 🔴 Critique (perte d'argent / triche) · 🟠 Haute (fonctionnel/concurrence) · 🟡 Moyenne (hygiène/sécu) · ⚪ Faible (qualité)

---

## 🔴 Critique

- [ ] **Quiz sans validation serveur** — `joueur/jeu/comores_quiz.php`. `action=win` crédite `mise×5` sans vérifier qu'il y a eu 8 bonnes réponses côté serveur. Appeler `start` puis `win` directement (sans jouer) rapporte +400 KMF net, en boucle illimitée. Aucun CSRF sur les actions AJAX, aucune écriture dans `transactions_monrevenu`.
  - Piste : stocker la question posée / les bonnes réponses en session au `start`, revalider les réponses envoyées par le client à chaque question (ou au minimum exiger une preuve côté serveur avant `win`), journaliser `jeu_gain`/`jeu_perte`, ajouter un jeton CSRF.

- [ ] **`produit.php` : `ref` sans signature accepté** — ligne ~119, `elseif ($ref_id_brut > 0) $ref_id = $ref_id_brut;` attribue une commission à n'importe quel `ref=<id>` sans vérifier `sig`. Supprimer cette branche ou exiger systématiquement une signature valide.

- [ ] **`produit.php` : vendeur suspendu/supprimé toujours payé + pas de plafond de quantité** — la requête vendeur ne filtre pas `status='active' AND is_active=1` ; `quantite` n'a pas de borne haute. Réintroduire les deux contrôles (régression déjà survenue une fois selon l'historique).

- [ ] **Webhook WhatsApp cassé** — `includs/webhook_whatsapp.php` fait `require __DIR__.'/../config/connexion.php'`, fichier inexistant (le projet utilise `basse_de_donner/monrevenu_bd.php`). Chaque appel du webhook plante → la vérification téléphone par WhatsApp entrante ne fonctionne jamais en l'état. Correctif d'une ligne, mais bloquant pour la fonctionnalité livrée au dernier commit.

## 🟠 Haute

- [ ] **Course critique sur les transferts** — `sections/wallet.php` (fonction transfert) et `sections/transfert.php` (code dupliqué) débitent `balance = balance - ?` sans `FOR UPDATE` ni `WHERE balance >= ?`. Solde négatif possible en double soumission. Le retrait (`wallet.php`) est déjà protégé correctement — copier le même pattern sur les deux chemins de transfert.

- [ ] **`admin/dashboard_agent.php` absent** — `login_handler.php` (login classique et Google) redirige les comptes `agent` vers ce fichier qui n'existe pas → 404 pour tout agent qui se connecte. Créer la page ou changer la redirection.

- [ ] **`includs/generer_cles_vapid.php` référencé mais absent** — à retrouver/recréer si les clés VAPID doivent être régénérées, sinon retirer les références mortes.

- [ ] **Parrainage : code mort mais trompeur** — `register_handler.php` dit ne plus avoir de système de parrainage et n'insère jamais `parrain_id`, mais `verification.php` garde ~170 lignes de logique de crédit de parrainage jamais déclenchée. Décider : relancer le parrainage (ajouter l'insertion `parrain_id` à l'inscription) ou nettoyer le code mort de `verification.php`.

## 🟡 Moyenne

- [ ] **XSS notifications** — `js/app.js` (~ligne 192) injecte `${n.message}` dans `innerHTML` sans échappement. Échapper le HTML avant insertion (ou passer par `textContent`).

- [ ] **Secrets/identifiants en dur** :
  - `services/Formation.php` : connexion PDO en dur (`localhost`, `mon_revenu_db`, `root`, sans mot de passe) au lieu de `basse_de_donner/monrevenu_bd.php`.
  - `includs/whatsapp_sender.php` : constantes `REMPLACER_PAR_...` en dur au lieu de lire `.env` (`WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, etc. existent pourtant dans le `.env`).
  - `produit.php` / `services/boutique.php` : `SECRET_AFFILIATION` en dur (les deux valeurs sont identiques donc pas de bug fonctionnel actuellement, mais le `.env` a une clé `AFFILIATION_SECRET` jamais utilisée — à brancher).

- [ ] **Cookies de session non durcis hors admin** — pas de `session_set_cookie_params`/SameSite pour les sessions utilisateur standard (dashboard, wallet, quiz), contrairement à `admin/auth_middleware.php`. Ajouter les mêmes réglages (secure/httponly/samesite) au démarrage de session côté utilisateur.

- [ ] **Monnaie incohérente** — KMF (wallet, mon-stock, Publicites, produit UI) vs FCFA (`sections/transfert.php`) vs XOF (`og:price:currency` dans `produit.php`). Choisir une devise unique et corriger les libellés.

- [ ] **Open redirect** — `accepter_cookies.php` redirige vers `$_GET['redirect']` sans validation. Restreindre à une liste blanche de chemins internes.

- [ ] **`page/marquer-lu.php`** — action d'état par GET sans CSRF (mineur, mais facile à corriger en passant en POST + jeton).

## ⚪ Faible

- [ ] Annuaire « prestataires » (`annuaire_prestataires.php`) codé en dur, lien « Devenir prestataire » mort, commandes jamais persistées (INSERT en commentaire).
- [ ] Stock revendeur : ventes auto-déclarées sans info client, facile à faire du farming de commissions.
- [ ] Commission affichée (`commission_pct`, boutique) ≠ commission réellement créditée (paliers fixes 500/1000 KMF dans `produit.php`) — harmoniser l'affichage.
- [ ] PWA non réellement offline — `sw.js` déclare un `CACHE_NAME` inutilisé, aucun handler `fetch`.
- [ ] Publicités : quota 10/jour consommé dès `start` même sans finir la vidéo.
- [ ] Titres copiés-collés « Mon Profil » sur `page/messagerie.php`, `page/historique.php`, `services/Formation.php`.

---

## Déjà vérifié comme OK / corrigé (ne pas retraiter sans nouvelle raison)

- ✅ Connexion — mot de passe : casse conservée telle que saisie (`password_verify` direct), le bug de conversion en majuscules est corrigé (commit `18d72c5`).
- ✅ Connexion Google (`includs/google_auth_handler.php`) : implémentée et fonctionnelle (vérification du jeton, création/liaison de compte, exigence de complétion téléphone).
- ✅ Retrait (`sections/wallet.php`) : verrouillage `FOR UPDATE` correct, pas de race condition.
- ✅ Publicités (`services/Publicites.php`) : validation serveur du temps de visionnage, anti-double-crédit OK.
- ✅ Lien d'affiliation boutique ↔ produit : les deux secrets HMAC sont maintenant identiques (le bug de mismatch signalé le 15/09 n'est plus reproductible), même si le secret reste codé en dur (voir 🟡 ci-dessus).

---

*Pour le détail architecture / tables / flux d'argent, demander à l'assistant — c'est en mémoire persistante (`projet-monrevenu*`).*
