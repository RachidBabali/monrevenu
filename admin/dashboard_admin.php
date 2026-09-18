<?php

/**
 * ECOSYSTÈME D'ADMINISTRATION CENTRALISÉ, MonRevenu
 * Gestion des Produits, Formations, Commissions, Retraits & Ventes d'affiliation
 */
require_once '../basse_de_donner/monrevenu_bd.php';
require_once '../includs/env_loader.php';
require_once '../includs/r2_uploader.php';
require_once '../includs/image_helper.php';
require_once 'auth_middleware.php';
require_once '../includs/ui.php';


// Sécurité d'accès strict à l'administrateur
$admin = requireRole($pdo, 'admin');
$csrf_token = $_SESSION['csrf_token'] ?? '';

// --- LOGIQUE DE TRAITEMENT DES FORMULAIRES ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Action non autorisée (CSRF).');
    }

    // 1. Action : Publier un produit
    if (isset($_POST['action_produit'])) {
        $nom = htmlspecialchars($_POST['nom']);
        $description = htmlspecialchars($_POST['description']);
        $prix = (float)$_POST['prix'];
        $commission = (int)$_POST['commission_pourcentage'];

        $image = imageProduitParDefaut();
        $uploadOk = true;

        if (isset($_FILES['image_produit']) && $_FILES['image_produit']['error'] !== UPLOAD_ERR_NO_FILE) {

            $fichier = $_FILES['image_produit'];

            if ($fichier['error'] !== UPLOAD_ERR_OK) {
                $uploadOk = false;
                $error = "Erreur lors de l'envoi du fichier (code " . $fichier['error'] . ").";
            }

            if ($uploadOk && $fichier['size'] > 2 * 1024 * 1024) {
                $uploadOk = false;
                $error = "L'image dépasse la taille maximale autorisée (2 Mo).";
            }

            $typesAutorises = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReel = finfo_file($finfo, $fichier['tmp_name']);
            finfo_close($finfo);

            if ($uploadOk && !array_key_exists($mimeReel, $typesAutorises)) {
                $uploadOk = false;
                $error = "Format de fichier non autorisé. Utilisez JPG, PNG ou WEBP.";
            }

            if ($uploadOk) {
                $extension = $typesAutorises[$mimeReel];
                $nomFichier = 'produit_' . uniqid() . '_' . time() . '.' . $extension;

                $resultat = uploaderVersR2($fichier['tmp_name'], 'produits/' . $nomFichier, $mimeReel);

                if ($resultat['ok']) {
                    $image = $resultat['url'];
                } else {
                    $uploadOk = false;
                    $error = "" . $resultat['error'];
                }
            }
        }

        if ($uploadOk && !empty($nom) && $prix > 0) {
            $stmt = $pdo->prepare("INSERT INTO vendeur_produits (vendeur_id, nom_produit, description, image, prix_vente, commission_pct) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$admin['id'], $nom, $description, $image, $prix, $commission])) {
                $message = "Le produit a été publié avec succès au catalogue.";
            } else {
                $error = "Une erreur est survenue lors de la création du produit.";
            }
        }
    }

    // 2. Action : Transférer / Ajuster une commission manuellement
    if (isset($_POST['action_transfert_commission'])) {
        $user_id = (int)$_POST['user_id'];
        $montant = (float)$_POST['montant'];
        $description = htmlspecialchars($_POST['description'] ?? 'Ajustement de commission par l\'admin');

        if ($user_id > 0 && $montant > 0) {
            $pdo->beginTransaction();
            try {
                $stmtComm = $pdo->prepare("INSERT INTO agent_commissions (agent_id, operation_type, base_amount, rate, commission_amt) VALUES (?, 'ajustement_admin', ?, 100, ?)");
                $stmtComm->execute([$user_id, $montant, $montant]);

                $stmtUser = $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?");
                $stmtUser->execute([$montant, $user_id]);

                $referenceTx = 'COMM-' . uniqid();
                $stmtTx = $pdo->prepare(
                    "INSERT INTO transactions_monrevenu (user_id, type, amount, reference, status, description)
                     VALUES (?, 'commission', ?, ?, 'complete', ?)"
                );
                $stmtTx->execute([$user_id, $montant, $referenceTx, $description]);

                $stmtNotifComm = $pdo->prepare(
                    "INSERT INTO messages (user_id, expediteur, message, statut)
                     VALUES (?, 'MonRevenu', ?, 'non_lu')"
                );
                $texteNotifComm = "Une commission de " . number_format($montant, 0, ',', ' ') . " FCFA vous a été créditée. Motif : " . $description;
                $stmtNotifComm->execute([$user_id, $texteNotifComm]);

                $pdo->commit();
                $message = "La commission de " . $montant . " FCFA a bien été créditée à l'utilisateur.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Échec du transfert de commission : " . $e->getMessage();
            }
        }
    }

    // 3. Action : Mettre à jour le statut d'une vente d'affiliation
    if (isset($_POST['action_update_statut_vente'])) {
        $vente_id = (int) ($_POST['vente_id'] ?? 0);
        $nouveau_statut = $_POST['statut'] ?? '';
        $statuts_valides = ['en_attente', 'contacte', 'colis_recu', 'validee', 'annulee'];

        if ($vente_id && in_array($nouveau_statut, $statuts_valides, true)) {
            $pdo->beginTransaction();
            try {
                $stmtVente = $pdo->prepare("SELECT * FROM vendeur_ventes WHERE id = ? FOR UPDATE");
                $stmtVente->execute([$vente_id]);
                $vente = $stmtVente->fetch();

                if ($vente) {
                    $update = $pdo->prepare("UPDATE vendeur_ventes SET statut = ? WHERE id = ?");
                    $update->execute([$nouveau_statut, $vente_id]);

                    if ($nouveau_statut === 'validee' && (int) $vente['commission_creditee'] === 0) {
                        $stmtCredit = $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?");
                        $stmtCredit->execute([$vente['commission_earn'], $vente['vendeur_id']]);

                        $stmtFlag = $pdo->prepare("UPDATE vendeur_ventes SET commission_creditee = 1 WHERE id = ?");
                        $stmtFlag->execute([$vente_id]);

                        $referenceVente = 'VENTE-' . $vente_id;
                        $stmtTxVente = $pdo->prepare(
                            "INSERT INTO transactions_monrevenu (user_id, type, amount, reference, status, description)
                             VALUES (?, 'commission', ?, ?, 'complete', ?)"
                        );
                        $stmtTxVente->execute([
                            $vente['vendeur_id'],
                            $vente['commission_earn'],
                            $referenceVente,
                            'Commission sur vente #' . $vente_id
                        ]);
                    }

                    $libelles_notif_statut = [
                        'en_attente' => "Votre vente #" . $vente_id . " est en attente de traitement.",
                        'contacte'   => "Le client de votre vente #" . $vente_id . " a été contacté. En attente de confirmation.",
                        'colis_recu' => "Le client de votre vente #" . $vente_id . " a bien reçu son colis. Le paiement de votre commission suit très vite.",
                        'validee'    => "Vente #" . $vente_id . " validée. " . number_format((float) $vente['commission_earn'], 0, ',', ' ') . " FCFA ont été crédités sur votre solde.",
                        'annulee'    => "Votre vente #" . $vente_id . " a été annulée.",
                    ];
                    require_once __DIR__ . '/../includs/notifications.php';
                    $titres_notif_statut = [
                        'en_attente' => 'Vente en attente',
                        'contacte'   => 'Client contacté',
                        'colis_recu' => 'Colis reçu',
                        'validee'    => 'Commission créditée',
                        'annulee'    => 'Vente annulée',
                    ];
                    envoyerNotification(
                        $pdo,
                        (int) $vente['vendeur_id'],
                        $libelles_notif_statut[$nouveau_statut],
                        $titres_notif_statut[$nouveau_statut] ?? 'MonRevenu',
                        '/page/historique.php'
                    );

                    $pdo->commit();
                    $message = "Statut de la vente mis à jour.";
                } else {
                    $pdo->rollBack();
                    $error = "Vente introuvable.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Échec de la mise à jour : " . $e->getMessage();
            }
        }
    }

    // 3bis. Action : Envoyer directement la commission
    if (isset($_POST['action_envoyer_commission'])) {
        $vente_id = (int) ($_POST['vente_id'] ?? 0);

        if ($vente_id > 0) {
            $pdo->beginTransaction();
            try {
                $stmtVente = $pdo->prepare("SELECT * FROM vendeur_ventes WHERE id = ? FOR UPDATE");
                $stmtVente->execute([$vente_id]);
                $vente = $stmtVente->fetch();

                if ($vente && (int) $vente['commission_creditee'] === 0) {
                    $update = $pdo->prepare("UPDATE vendeur_ventes SET statut = 'validee', commission_creditee = 1 WHERE id = ?");
                    $update->execute([$vente_id]);

                    $stmtCredit = $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?");
                    $stmtCredit->execute([$vente['commission_earn'], $vente['vendeur_id']]);

                    $referenceVente = 'VENTE-' . $vente_id;
                    $stmtTxVente = $pdo->prepare(
                        "INSERT INTO transactions_monrevenu (user_id, type, amount, reference, status, description)
                         VALUES (?, 'commission', ?, ?, 'complete', ?)"
                    );
                    $stmtTxVente->execute([
                        $vente['vendeur_id'],
                        $vente['commission_earn'],
                        $referenceVente,
                        'Commission sur vente #' . $vente_id
                    ]);

                    require_once __DIR__ . '/../includs/notifications.php';
                    envoyerNotification(
                        $pdo,
                        (int) $vente['vendeur_id'],
                        "Commission envoyée. " . number_format((float) $vente['commission_earn'], 0, ',', ' ') . " FCFA ont été crédités sur votre solde pour la vente #" . $vente_id . ".",
                        'Commission créditée',
                        '/page/historique.php'
                    );

                    $pdo->commit();
                    $message = "Commission envoyée avec succès.";
                } else {
                    $pdo->rollBack();
                    $error = "Cette commission a déjà été envoyée ou la vente est introuvable.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Échec de l'envoi de la commission : " . $e->getMessage();
            }
        }
    }

    // 3ter. Action : Valider une demande de retrait
    if (isset($_POST['action_valider_retrait'])) {
        $withdrawal_id = (int) ($_POST['withdrawal_id'] ?? 0);

        if ($withdrawal_id > 0) {
            $pdo->beginTransaction();
            try {
                $stmtW = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
                $stmtW->execute([$withdrawal_id]);
                $w = $stmtW->fetch();

                if ($w && in_array($w['status'], ['en_attente', 'pending'], true)) {
                    $update = $pdo->prepare("UPDATE withdrawals SET status = 'valide' WHERE id = ?");
                    $update->execute([$withdrawal_id]);

                    $stmtTxUpdate = $pdo->prepare("UPDATE transactions_monrevenu SET status = 'complete' WHERE reference = ?");
                    $stmtTxUpdate->execute(['RETRAIT-' . $withdrawal_id]);

                    $stmtNotifW = $pdo->prepare(
                        "INSERT INTO messages (user_id, expediteur, message, statut)
                         VALUES (?, 'MonRevenu', ?, 'non_lu')"
                    );
                    $texteNotifW = "Votre retrait de " . number_format((float) $w['amount'], 0, ',', ' ') . " FCFA a été envoyé avec succès.";
                    $stmtNotifW->execute([$w['user_id'], $texteNotifW]);

                    $pdo->commit();
                    $message = "Retrait validé et marqué comme envoyé.";
                } else {
                    $pdo->rollBack();
                    $error = "Cette demande a déjà été traitée ou est introuvable.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Échec de la validation : " . $e->getMessage();
            }
        }
    }

    // 3quater. Action : Refuser une demande de retrait
    if (isset($_POST['action_refuser_retrait'])) {
        $withdrawal_id = (int) ($_POST['withdrawal_id'] ?? 0);

        if ($withdrawal_id > 0) {
            $pdo->beginTransaction();
            try {
                $stmtW = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
                $stmtW->execute([$withdrawal_id]);
                $w = $stmtW->fetch();

                if ($w && in_array($w['status'], ['en_attente', 'pending'], true)) {
                    $update = $pdo->prepare("UPDATE withdrawals SET status = 'rejete' WHERE id = ?");
                    $update->execute([$withdrawal_id]);

                    $stmtRecredit = $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?");
                    $stmtRecredit->execute([$w['amount'], $w['user_id']]);

                    $stmtTxUpdate = $pdo->prepare("UPDATE transactions_monrevenu SET status = 'echoue' WHERE reference = ?");
                    $stmtTxUpdate->execute(['RETRAIT-' . $withdrawal_id]);

                    $stmtNotifW = $pdo->prepare(
                        "INSERT INTO messages (user_id, expediteur, message, statut)
                         VALUES (?, 'MonRevenu', ?, 'non_lu')"
                    );
                    $texteNotifW = "Votre retrait de " . number_format((float) $w['amount'], 0, ',', ' ') . " FCFA a été refusé. Le montant a été recrédité sur votre solde.";
                    $stmtNotifW->execute([$w['user_id'], $texteNotifW]);

                    $pdo->commit();
                    $message = "Retrait refusé, montant recrédité à l'utilisateur.";
                } else {
                    $pdo->rollBack();
                    $error = "Cette demande a déjà été traitée ou est introuvable.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Échec du refus : " . $e->getMessage();
            }
        }
    }

    // 9. Action : Modifier un produit existant
    if (isset($_POST['action_edit_produit'])) {
        $produit_id = (int) ($_POST['produit_id'] ?? 0);
        $nom = htmlspecialchars($_POST['nom_edit'] ?? '');
        $description = htmlspecialchars($_POST['description_edit'] ?? '');
        $prix = (float) ($_POST['prix_edit'] ?? 0);
        $commission = (int) ($_POST['commission_pourcentage_edit'] ?? 0);

        if ($produit_id > 0 && !empty($nom) && $prix > 0) {
            $stmtEditProduit = $pdo->prepare(
                "UPDATE vendeur_produits SET nom_produit = ?, description = ?, prix_vente = ?, commission_pct = ? WHERE id = ?"
            );
            if ($stmtEditProduit->execute([$nom, $description, $prix, $commission, $produit_id])) {
                $message = "Produit mis à jour.";
            } else {
                $error = "Impossible de mettre à jour ce produit.";
            }
        } else {
            $error = "Nom et prix valides sont obligatoires.";
        }
    }

    // 10. Action : Supprimer un produit
    if (isset($_POST['action_delete_produit'])) {
        $produit_id = (int) ($_POST['produit_id'] ?? 0);
        if ($produit_id > 0) {
            $stmtCheckVentes = $pdo->prepare("SELECT COUNT(*) FROM vendeur_ventes WHERE produit_id = ?");
            $stmtCheckVentes->execute([$produit_id]);
            if ((int) $stmtCheckVentes->fetchColumn() > 0) {
                $error = "Impossible de supprimer : ce produit a déjà des ventes enregistrées. Modifiez-le plutôt, ou contactez le support technique.";
            } else {
                $stmtDelProduit = $pdo->prepare("DELETE FROM vendeur_produits WHERE id = ?");
                if ($stmtDelProduit->execute([$produit_id])) {
                    $message = "Produit supprimé.";
                } else {
                    $error = "Impossible de supprimer ce produit.";
                }
            }
        }
    }

    // 13. Action : Bloquer / débloquer un utilisateur
    if (isset($_POST['action_toggle_user_status'])) {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);

        if ($target_user_id === (int) $admin['id']) {
            $error = "Vous ne pouvez pas bloquer votre propre compte.";
        } elseif ($target_user_id > 0) {
            $stmtToggleUser = $pdo->prepare(
                "UPDATE users_monrevenu SET is_active = 1 - is_active, status = IF(is_active = 1, 'suspended', 'active') WHERE id = ?"
            );
            if ($stmtToggleUser->execute([$target_user_id])) {
                $message = "Statut de l'utilisateur mis à jour.";
            } else {
                $error = "Impossible de mettre à jour le statut de cet utilisateur.";
            }
        }
    }

    // 14. Action : Supprimer un utilisateur (suppression douce)
    if (isset($_POST['action_delete_user'])) {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);

        if ($target_user_id === (int) $admin['id']) {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        } elseif ($target_user_id > 0) {
            $stmtDeleteUser = $pdo->prepare(
                "UPDATE users_monrevenu SET status = 'deleted', is_active = 0 WHERE id = ?"
            );
            if ($stmtDeleteUser->execute([$target_user_id])) {
                $message = "Compte utilisateur supprimé (historique conservé pour la comptabilité).";
            } else {
                $error = "Impossible de supprimer cet utilisateur.";
            }
        }
    }

    // 15. Action : Créer un produit dans le catalogue de stock (indépendant de vendeur_produits)
    if (isset($_POST['action_creer_produit_stock'])) {
        $nom_produit_stock = htmlspecialchars($_POST['nom_produit_stock'] ?? '');
        $prix_produit_stock = (float) ($_POST['prix_produit_stock'] ?? 0);
        $commission_produit_stock = (float) ($_POST['commission_produit_stock'] ?? 0);

        $image_produit_stock = imageProduitParDefaut();
        $uploadOkStock = true;

        if (isset($_FILES['image_produit_stock']) && $_FILES['image_produit_stock']['error'] !== UPLOAD_ERR_NO_FILE) {
            $fichierStock = $_FILES['image_produit_stock'];

            if ($fichierStock['error'] !== UPLOAD_ERR_OK) {
                $uploadOkStock = false;
                $error = "Erreur lors de l'envoi du fichier (code " . $fichierStock['error'] . ").";
            }

            if ($uploadOkStock && $fichierStock['size'] > 2 * 1024 * 1024) {
                $uploadOkStock = false;
                $error = "L'image dépasse la taille maximale autorisée (2 Mo).";
            }

            $typesAutorisesStock = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if ($uploadOkStock) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeStock = finfo_file($finfo, $fichierStock['tmp_name']);
                finfo_close($finfo);

                if (!array_key_exists($mimeStock, $typesAutorisesStock)) {
                    $uploadOkStock = false;
                    $error = "Format de fichier non autorisé. Utilisez JPG, PNG ou WEBP.";
                }
            }

            if ($uploadOkStock) {
                $extensionStock = $typesAutorisesStock[$mimeStock];
                $nomFichierStock = 'produitstock_' . uniqid() . '_' . time() . '.' . $extensionStock;

                $resultat = uploaderVersR2($fichierStock['tmp_name'], 'produits-stock/' . $nomFichierStock, $mimeStock);

                if ($resultat['ok']) {
                    $image_produit_stock = $resultat['url'];
                } else {
                    $uploadOkStock = false;
                    $error = "" . $resultat['error'];
                }
            }
        }

        if ($uploadOkStock && !empty($nom_produit_stock) && $prix_produit_stock > 0) {
            $stmtCreerProduitStock = $pdo->prepare(
                "INSERT INTO produits_stock (nom_produit, image, prix_vente, commission_fixe) VALUES (?, ?, ?, ?)"
            );
            if ($stmtCreerProduitStock->execute([$nom_produit_stock, $image_produit_stock, $prix_produit_stock, $commission_produit_stock])) {
                $message = "Produit ajouté au catalogue de stock.";
            } else {
                $error = "Une erreur est survenue lors de la création du produit.";
            }
        } elseif ($uploadOkStock) {
            $error = "Nom et prix valides sont obligatoires.";
        }
    }

    // 16. Action : Attribuer du stock à un utilisateur (revendeur)
    if (isset($_POST['action_attribuer_stock'])) {
        $stock_user_id    = (int) ($_POST['stock_user_id'] ?? 0);
        $stock_produit_id = (int) ($_POST['stock_produit_id'] ?? 0);
        $stock_quantite   = (int) ($_POST['stock_quantite'] ?? 0);

        if ($stock_user_id > 0 && $stock_produit_id > 0 && $stock_quantite > 0) {
            try {
                $pdo->beginTransaction();

                $stmtExiste = $pdo->prepare(
                    "SELECT quantite_disponible FROM stocks_revendeurs WHERE user_id = ? AND produit_id = ? FOR UPDATE"
                );
                $stmtExiste->execute([$stock_user_id, $stock_produit_id]);
                $ligneExistante = $stmtExiste->fetch();

                $stock_avant    = $ligneExistante ? (int) $ligneExistante['quantite_disponible'] : 0;
                $stock_apres    = $stock_avant + $stock_quantite;
                $type_mouvement = $ligneExistante ? 'RESTOCK' : 'STOCK_INITIAL';

                $pdo->prepare(
                    "INSERT INTO stocks_revendeurs (user_id, produit_id, quantite_disponible) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantite_disponible = quantite_disponible + VALUES(quantite_disponible)"
                )->execute([$stock_user_id, $stock_produit_id, $stock_quantite]);

                $referenceStock = 'STOCK-' . $stock_user_id . '-' . $stock_produit_id . '-' . time();

                $pdo->prepare(
                    "INSERT INTO mouvements_stock (user_id, produit_id, type_mouvement, quantite, stock_avant, stock_apres, reference, effectue_par)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([$stock_user_id, $stock_produit_id, $type_mouvement, $stock_quantite, $stock_avant, $stock_apres, $referenceStock, $admin['id']]);

                $stmtProduitNom = $pdo->prepare("SELECT nom_produit FROM produits_stock WHERE id = ?");
                $stmtProduitNom->execute([$stock_produit_id]);
                $nomProduitAttribue = $stmtProduitNom->fetchColumn() ?: 'un produit';

                $pdo->prepare(
                    "INSERT INTO messages (user_id, expediteur, message, statut) VALUES (?, 'MonRevenu', ?, 'non_lu')"
                )->execute([
                    $stock_user_id,
                    "" . $stock_quantite . " unité(s) de " . $nomProduitAttribue . " ont été ajoutées à votre stock."
                ]);

                $pdo->commit();
                $message = "Stock attribué avec succès.";
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Impossible d'attribuer le stock : " . $e->getMessage();
            }
        } else {
            $error = "Merci de choisir un utilisateur, un produit et une quantité valide.";
        }
    }

    // 17. Action : Envoyer la commission d'une vente de stock
    if (isset($_POST['action_envoyer_commission_stock'])) {
        $vente_stock_id = (int) ($_POST['vente_stock_id'] ?? 0);

        if ($vente_stock_id > 0) {
            try {
                $pdo->beginTransaction();

                $stmtVenteStock = $pdo->prepare("SELECT * FROM ventes_stock WHERE id = ? FOR UPDATE");
                $stmtVenteStock->execute([$vente_stock_id]);
                $venteStock = $stmtVenteStock->fetch();

                if ($venteStock && (int) $venteStock['commission_envoyee'] === 0) {
                    $pdo->prepare("UPDATE ventes_stock SET commission_envoyee = 1 WHERE id = ?")
                        ->execute([$vente_stock_id]);

                    $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?")
                        ->execute([$venteStock['commission_montant'], $venteStock['user_id']]);

                    $pdo->prepare(
                        "INSERT INTO transactions_monrevenu (user_id, type, amount, reference, status, description)
                         VALUES (?, 'commission', ?, ?, 'complete', ?)"
                    )->execute([
                        $venteStock['user_id'],
                        $venteStock['commission_montant'],
                        $venteStock['reference'],
                        'Commission sur vente de stock #' . $venteStock['id']
                    ]);

                    $pdo->prepare(
                        "INSERT INTO messages (user_id, expediteur, message, statut) VALUES (?, 'MonRevenu', ?, 'non_lu')"
                    )->execute([
                        $venteStock['user_id'],
                        "Commission envoyée. " . number_format((float) $venteStock['commission_montant'], 0, ',', ' ') . " FCFA ont été crédités sur votre solde."
                    ]);

                    $pdo->commit();
                    $message = "Commission envoyée avec succès.";
                } else {
                    $pdo->rollBack();
                    $error = "Cette commission a déjà été envoyée ou la vente est introuvable.";
                }
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Échec de l'envoi : " . $e->getMessage();
            }
        }
    }

    // --- POST/REDIRECT/GET ---
    // Empêche la resoumission du formulaire (double création, etc.) quand
    // l'utilisateur rafraîchit la page ou revient en arrière après un POST.
    // Le message/erreur est stocké en session le temps d'une redirection,
    // puis affiché une seule fois sur la page rechargée en GET.
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_error']   = $error;
    header('Location: dashboard_admin.php');
    exit();
}

// Récupère le message flash laissé par un éventuel POST précédent (voir plus haut)
if (isset($_SESSION['flash_message']) || isset($_SESSION['flash_error'])) {
    $message = $_SESSION['flash_message'] ?? '';
    $error   = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_message'], $_SESSION['flash_error']);
}

// --- RÉCUPÉRATION DES DONNÉES DISPONIBLES ---
$produits = $pdo->query("SELECT * FROM vendeur_produits ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$utilisateurs = $pdo->query("SELECT id, fullname, role FROM users_monrevenu ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);

$tous_utilisateurs = $pdo->query(
    "SELECT id, fullname, email, phone, role, balance, is_active, status, created_at
     FROM users_monrevenu
     ORDER BY created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$ventes = $pdo->query(
    "SELECT v.id, v.quantite, v.prix_unitaire, v.commission_earn, v.commission_creditee,
            v.nom_client, v.telephone_client, v.adresse_client, v.statut, v.created_at,
            p.nom_produit AS produit_nom,
            u.fullname AS vendeur_nom
     FROM vendeur_ventes v
     JOIN vendeur_produits p ON p.id = v.produit_id
     JOIN users_monrevenu u ON u.id = v.vendeur_id
     ORDER BY v.created_at DESC
     LIMIT 30"
)->fetchAll(PDO::FETCH_ASSOC);

$retraits = $pdo->query(
    "SELECT w.id, w.amount, w.status, w.method, w.note, w.created_at,
            u.fullname AS utilisateur_nom
     FROM withdrawals w
     JOIN users_monrevenu u ON u.id = w.user_id
     ORDER BY w.created_at DESC
     LIMIT 30"
)->fetchAll(PDO::FETCH_ASSOC);

$historique = $pdo->query(
    "SELECT t.id, t.type, t.amount, t.reference, t.status, t.description, t.created_at,
            u.fullname AS utilisateur_nom
     FROM transactions_monrevenu t
     JOIN users_monrevenu u ON u.id = t.user_id
     ORDER BY t.created_at DESC
     LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

// --- STOCK REVENDEURS ---
$produits_catalogue_complet = $pdo->query("SELECT id, nom_produit, image, prix_vente, commission_fixe FROM produits_stock ORDER BY nom_produit ASC")->fetchAll(PDO::FETCH_ASSOC);

$stocks_tous_utilisateurs = $pdo->query(
    "SELECT sr.user_id, sr.produit_id, sr.quantite_disponible,
            u.fullname, vp.nom_produit, vp.image,
            COALESCE((SELECT SUM(vs.quantite) FROM ventes_stock vs WHERE vs.user_id = sr.user_id AND vs.produit_id = sr.produit_id), 0) AS quantite_vendue
     FROM stocks_revendeurs sr
     JOIN users_monrevenu u ON u.id = sr.user_id
     JOIN produits_stock vp ON vp.id = sr.produit_id
     ORDER BY u.fullname ASC, vp.nom_produit ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$ventes_stock_en_attente = $pdo->query(
    "SELECT vs.id, vs.reference, vs.montant_total, vs.commission_montant,
            u.fullname, vp.nom_produit, vp.image
     FROM ventes_stock vs
     JOIN users_monrevenu u ON u.id = vs.user_id
     JOIN produits_stock vp ON vp.id = vs.produit_id
     WHERE vs.commission_envoyee = 0
     ORDER BY vs.created_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$libelles_type_tx = [
    'depot'         => 'Dépôt',
    'retrait'       => 'Retrait',
    'commission'    => 'Commission',
    'achat_service' => 'Achat de service',
    'jeu_gain'      => 'Gain de jeu',
    'jeu_perte'     => 'Perte de jeu',
];
$couleurs_type_tx = [
    'depot'         => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'retrait'       => 'bg-red-50 text-red-500 border-red-200',
    'commission'    => 'bg-primary-soft text-primary border-primary/20',
    'achat_service' => 'bg-slate-100 text-slate-600 border-slate-200',
    'jeu_gain'      => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'jeu_perte'     => 'bg-red-50 text-red-500 border-red-200',
];

$libelles_statut = [
    'en_attente' => 'En attente',
    'contacte'   => 'Contacté',
    'colis_recu' => 'Colis reçu',
    'validee'    => 'Validée',
    'annulee'    => 'Annulée',
];
$couleurs_statut = [
    'en_attente' => 'bg-amber-50 text-amber-600 border-amber-200',
    'contacte'   => 'bg-sky-50 text-sky-600 border-sky-200',
    'colis_recu' => 'bg-indigo-50 text-indigo-600 border-indigo-200',
    'validee'    => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'annulee'    => 'bg-red-50 text-red-500 border-red-200',
];

$libelles_statut_user = [
    'active'    => 'Actif',
    'suspended' => 'Bloqué',
    'deleted'   => 'Supprimé',
];
$couleurs_statut_user = [
    'active'    => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'suspended' => 'bg-amber-50 text-amber-600 border-amber-200',
    'deleted'   => 'bg-red-50 text-red-500 border-red-200',
];

// --- STATISTIQUES POUR LE BANDEAU D'ACCUEIL ---
$nb_produits       = count($produits);
$nb_utilisateurs   = count($utilisateurs);
$nb_ventes_attente = count(array_filter($ventes, fn($v) => $v['statut'] === 'en_attente'));
$total_commissions = array_sum(array_map(
    fn($v) => $v['statut'] === 'validee' ? (float) $v['commission_earn'] : 0,
    $ventes
));

$repartition_roles = array_count_values(array_column($utilisateurs, 'role'));
$libelles_roles = [
    'admin'   => 'admin',
    'agent'   => 'agent' . (($repartition_roles['agent'] ?? 0) > 1 ? 's' : ''),
    'client'  => 'client' . (($repartition_roles['client'] ?? 0) > 1 ? 's' : ''),
    'affilie' => 'affilié' . (($repartition_roles['affilie'] ?? 0) > 1 ? 's' : ''),
];

$admin_prenom = explode(' ', trim($admin['fullname'] ?? 'Admin'))[0] ?? 'Admin';
$admin_initiales = strtoupper(substr($admin['fullname'] ?? 'A', 0, 1) . substr(strrchr(' ' . ($admin['fullname'] ?? ''), ' '), 1, 1));
?>
<?php
$titre_page = 'Administration';
$head_supp  = '<meta name="robots" content="noindex">';
include __DIR__ . '/../includs/head.php';
$message_affiche = nettoyerPictogrammes(strip_tags((string) ($message ?? '')));
$erreur_affichee = nettoyerPictogrammes(strip_tags((string) ($error ?? '')));
?>
<body class="admin">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<?php include 'sections/sidebar.php'; ?>

<div class="contenu-app pb-8">
  <?php include 'sections/Topbar.php'; ?>
  <?php include 'sections/mobile_sidebar.php'; ?>

  <main id="contenu" class="conteneur flex max-w-[1400px] flex-col gap-6 py-4 lg:py-6">
    <?php include 'sections/MESSAGES_FLASH.php'; ?>
    <?php include 'sections/bandeau_accueil.php'; ?>
    <?php include 'sections/utilisateurs.php'; ?>
    <?php include 'sections/produits.php'; ?>
    <?php include 'sections/edition-produit.php'; ?>
    <?php include 'sections/ventes.php'; ?>
    <?php include 'sections/commissions.php'; ?>
    <?php include 'sections/stock_revendeurs.php'; ?>
    <?php include 'sections/retraits.php'; ?>
    <?php include 'sections/historique.php'; ?>
  </main>
</div>

<div id="toasts" class="toasts" role="status" aria-live="polite"></div>
<script src="<?= e(actif('/assets/js/app-shell.js')) ?>"></script>
<script src="javaScript/script.js?v=<?= (int) @filemtime(__DIR__ . '/javaScript/script.js') ?>"></script>
</body>
</html>
