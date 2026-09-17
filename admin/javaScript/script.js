    function switchTab(tabId, clickedBtn) {
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
            content.classList.remove('block');
        });
        document.getElementById(tabId).classList.remove('hidden');
        document.getElementById(tabId).classList.add('block');

        // Synchronise l'état actif entre la sidebar desktop et les tabs mobile.
        // On matche par nom d'onglet (tabId) plutôt que par position dans la liste :
        // sidebar et barre mobile peuvent être dans un ordre différent (regroupement
        // par thème sur desktop), un matching positionnel se déréglerait silencieusement.
        document.querySelectorAll('.nav-btn, .mobile-tab-btn').forEach(btn => {
            const estCetOnglet = btn.getAttribute('onclick') && btn.getAttribute('onclick').includes("'" + tabId + "'");
            btn.classList.toggle('is-active', estCetOnglet);
        });

        // Mémorise l'onglet actif : sans ça, chaque soumission de formulaire
        // (qui recharge entièrement la page) revient toujours sur "Produits"
        // par défaut, même si on était sur un autre onglet.
        localStorage.setItem('admin_active_tab', tabId);
    }

    // Restaure l'onglet actif après un rechargement de page (soumission de
    // formulaire, F5, etc.) au lieu de toujours retomber sur "Produits".
    document.addEventListener('DOMContentLoaded', () => {
        const dernierOnglet = localStorage.getItem('admin_active_tab');
        if (dernierOnglet && document.getElementById(dernierOnglet)) {
            switchTab(dernierOnglet, null);
        }
    });

    // --- Modale d'édition produit : pré-remplit les champs puis ouvre le <dialog> ---
    function ouvrirEditionProduit(id, nom, description, prix, commission) {
        document.getElementById('edit-produit-id').value = id;
        document.getElementById('edit-produit-nom').value = nom;
        document.getElementById('edit-produit-description').value = description;
        document.getElementById('edit-produit-prix').value = prix;
        document.getElementById('edit-produit-commission').value = commission;
        document.getElementById('modal-edition-produit').showModal();
    }

    // --- Modale d'édition formation ---
    function ouvrirEditionFormation(id, titre, description, urlYoutube) {
        document.getElementById('edit-formation-id').value = id;
        document.getElementById('edit-formation-titre').value = titre;
        document.getElementById('edit-formation-description').value = description;
        document.getElementById('edit-formation-url').value = urlYoutube;
        document.getElementById('modal-edition-formation').showModal();
    }

    // --- Recherche en direct dans le tableau des utilisateurs (filtrage côté client) ---
    const champRechercheUtilisateurs = document.getElementById('recherche-utilisateurs');
    if (champRechercheUtilisateurs) {
        champRechercheUtilisateurs.addEventListener('input', function (e) {
            const terme = e.target.value.trim().toLowerCase();
            document.querySelectorAll('#table-utilisateurs tr[data-recherche]').forEach(function (ligne) {
                ligne.style.display = ligne.getAttribute('data-recherche').includes(terme) ? '' : 'none';
            });
        });
    }
