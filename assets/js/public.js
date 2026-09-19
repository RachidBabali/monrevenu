// Pages publiques : feuilles de connexion et d'inscription, validation, Google Sign-In, service worker.
(function () {
  'use strict';

  // API conservee pour les appels existants : openModal('modal-login'), closeModal(...)
  window.openModal = function (id) { if (window.MR) MR.ouvrir(id); };
  window.closeModal = function (id) { if (window.MR) MR.fermer(id); };
  window.closeAllModals = function () {
    document.querySelectorAll('dialog[open]').forEach(function (d) { d.close(); });
  };

  document.addEventListener('click', function (ev) {
    var bascule = ev.target.closest('[data-basculer]');
    if (bascule) {
      ev.preventDefault();
      var courant = bascule.closest('dialog');
      if (courant) courant.close();
      MR.ouvrir(bascule.getAttribute('data-basculer'));
      return;
    }
    var oeil = ev.target.closest('[data-afficher-mdp]');
    if (oeil) {
      var champ = document.getElementById(oeil.getAttribute('data-afficher-mdp'));
      var visible = champ.type === 'password';
      champ.type = visible ? 'text' : 'password';
      oeil.setAttribute('aria-pressed', visible ? 'true' : 'false');
      oeil.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    }
  });

  function erreur(id, message) {
    var el = document.getElementById('err-' + id);
    var champ = document.getElementById(id);
    if (el) { el.textContent = message; el.hidden = !message; }
    if (champ) champ.setAttribute('aria-invalid', message ? 'true' : 'false');
    return !message;
  }
  window.afficherErreurLogin = erreur;
  window.afficherErreur = erreur;

  function bloquer(e, form) {
    e.preventDefault();
    var premier = form.querySelector('[aria-invalid="true"]');
    if (premier) premier.focus();
  }

  // Connexion
  var login = document.getElementById('loginForm');
  if (login) {
    var identifiant = document.getElementById('loginPhone');
    var motDePasse = document.getElementById('loginCode');
    var verifIdentifiant = function () { return erreur('loginPhone', identifiant.value.trim().length < 5 ? 'Saisissez votre email ou votre numéro de téléphone.' : ''); };
    var verifMdp = function () { return erreur('loginCode', motDePasse.value === '' ? 'Saisissez votre mot de passe.' : ''); };
    identifiant.addEventListener('blur', function () { if (identifiant.value) verifIdentifiant(); });
    motDePasse.addEventListener('blur', function () { if (motDePasse.value) verifMdp(); });
    login.addEventListener('submit', function (e) {
      var ok = verifIdentifiant() & verifMdp();
      if (!ok) bloquer(e, login);
    }, true);
  }

  // Inscription
  var inscription = document.getElementById('registerForm');
  if (inscription) {
    var pays = document.getElementById('phoneCountry');
    var tel = document.getElementById('phone');
    var code = document.getElementById('code');
    var confirmation = document.getElementById('confirmCode');
    var naissance = document.getElementById('birthdate');

    // Choix du type de compte : les champs de la boutique n'apparaissent que pour un commercant,
    // et l'inscription Google (qui cree un compte affilie) est masquee dans ce cas.
    var estCommercant = function () { var r = document.getElementById('typeCommercant'); return !!(r && r.checked); };
    var basculerType = function () {
      var c = estCommercant();
      var champs = inscription.querySelector('[data-champs-commercant]');
      if (champs) champs.hidden = !c;
      document.querySelectorAll('[data-masquer-commercant]').forEach(function (b) { b.classList.toggle('hidden', c); });
    };
    inscription.querySelectorAll('input[name="type_compte"]').forEach(function (r) { r.addEventListener('change', basculerType); });
    basculerType();

    var placeholderTel = function () {
      if (pays.value === 'SN') { tel.placeholder = '77 123 45 67'; tel.maxLength = 12; }
      else { tel.placeholder = '321 23 45'; tel.maxLength = 9; }
    };
    placeholderTel();
    pays.addEventListener('change', placeholderTel);

    var regles = {
      fullName: function (v) { v = v.trim(); return v.length < 2 || v.length > 100 ? 'Indiquez votre nom complet (2 à 100 caractères).' : ''; },
      email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()) ? '' : 'Adresse email invalide. Exemple : nom@exemple.com.'; },
      birthdate: function (v) {
        if (!v) return 'Indiquez votre date de naissance.';
        var n = new Date(v), a = new Date();
        var age = a.getFullYear() - n.getFullYear() - ((a.getMonth() < n.getMonth() || (a.getMonth() === n.getMonth() && a.getDate() < n.getDate())) ? 1 : 0);
        return age < 18 ? 'Vous devez avoir au moins 18 ans.' : '';
      },
      phone: function (v) {
        var n = v.replace(/\D/g, ''), local = n;
        if (n.indexOf('00269') === 0 || n.indexOf('00221') === 0) local = n.slice(5);
        else if (n.indexOf('269') === 0 && n.length === 10) local = n.slice(3);
        else if (n.indexOf('221') === 0 && n.length === 12) local = n.slice(3);
        if (pays.value === 'SN') return /^7\d{8}$/.test(local) ? '' : 'Numéro sénégalais : 9 chiffres commençant par 7.';
        return /^[34]\d{6}$/.test(local) ? '' : 'Numéro comorien : 7 chiffres commençant par 3 ou 4.';
      },
      code: function (v) { return v.length < 8 ? 'Choisissez un mot de passe d\'au moins 8 caractères.' : ''; },
      confirmCode: function (v) { return v !== code.value ? 'Les deux mots de passe sont différents.' : ''; },
      nomBoutique: function (v) {
        if (!estCommercant()) return '';
        v = v.trim();
        return v.length < 2 || v.length > 120 ? 'Indiquez le nom de votre boutique (2 à 120 caractères).' : '';
      }
    };

    Object.keys(regles).forEach(function (id) {
      var el = document.getElementById(id);
      el.addEventListener('blur', function () { if (el.value) erreur(id, regles[id](el.value)); });
    });

    inscription.addEventListener('submit', function (e) {
      var ok = true;
      Object.keys(regles).forEach(function (id) {
        if (!erreur(id, regles[id](document.getElementById(id).value))) ok = false;
      });
      if (!erreur('acceptTerms', document.getElementById('acceptTerms').checked ? '' : 'Cochez la case pour accepter les conditions générales.')) ok = false;
      if (!ok) bloquer(e, inscription);
    }, true);
  }

  // Ouverture automatique au retour d'une erreur
  document.querySelectorAll('dialog[data-ouvrir-auto]').forEach(function (d) {
    window.addEventListener('DOMContentLoaded', function () { MR.ouvrir(d.id); });
  });
  if (location.hash === '#connexion') window.addEventListener('DOMContentLoaded', function () { MR.ouvrir('modal-login'); });
  if (location.hash === '#inscription') window.addEventListener('DOMContentLoaded', function () { MR.ouvrir('modal-register'); });
  if (location.hash === '#inscription-commercant') window.addEventListener('DOMContentLoaded', function () {
    var r = document.getElementById('typeCommercant');
    if (r) { r.checked = true; r.dispatchEvent(new Event('change')); }
    MR.ouvrir('modal-register');
  });
  // Liens internes vers l'inscription commercant (page d'accueil deja chargee)
  document.addEventListener('click', function (e) {
    var lien = e.target.closest('a[href="#inscription-commercant"], a[href="/#inscription-commercant"]');
    if (!lien) return;
    e.preventDefault();
    var r = document.getElementById('typeCommercant');
    if (r) { r.checked = true; r.dispatchEvent(new Event('change')); }
    MR.ouvrir('modal-register');
  });

  // Google Sign-In : une seule initialisation pour toute la page (deux initialize() cassent la bibliotheque)
  function handleGoogleCredential(response) {
    var form = document.getElementById('loginForm') || document.getElementById('registerForm');
    var csrf = form ? form.querySelector('input[name="csrf_token"]').value : '';
    fetch('includs/google_auth_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'credential=' + encodeURIComponent(response.credential) + '&csrf_token=' + encodeURIComponent(csrf)
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) { window.location.href = data.redirect || '/dashboard.php'; return; }
        MR.toast(data.error || 'La connexion avec Google a échoué. Réessayez ou utilisez votre mot de passe.');
      })
      .catch(function () { MR.toast('Connexion impossible. Vérifiez votre réseau et réessayez.'); });
  }
  window.handleGoogleCredential = handleGoogleCredential;

  // Le bloc (separateur "ou" + emplacement) reste masque tant que le bouton Google n'a rien rendu :
  // identifiant vide, script bloque ou echec de chargement le laissent donc cache.
  // Sur un reseau lent, le bloc apparait des que le bouton est rendu, sans delai maximal.
  var googlePret = false;
  // Le bouton Google a une largeur fixe (200 a 400 px) et son cadre deborde d'environ 10 px :
  // on la calcule d'apres l'emplacement pour ne jamais depasser la feuille sur les petits ecrans.
  function largeurGoogle(el) {
    // Feuille fermee ou bloc masque : largeur 0, on la deduit de la fenetre (pleine largeur sous 640 px, 448 px au-dela)
    var bloc = el.closest('[data-bloc-google]');
    var vw = document.documentElement.clientWidth;
    var dispo = bloc && bloc.clientWidth ? bloc.clientWidth : (vw < 640 ? vw : 448) - 32;
    return Math.max(200, Math.min(300, Math.floor(dispo) - 12));
  }
  function initGoogle() {
    if (googlePret) return;
    var meta = document.querySelector('meta[name="google-signin-client_id"]');
    var clientId = meta ? meta.content : '';
    if (!clientId || clientId.indexOf('YOUR_GOOGLE_CLIENT_ID') > -1) return;
    if (!window.google || !google.accounts || !google.accounts.id) return;
    googlePret = true;
    google.accounts.id.initialize({ client_id: clientId, callback: handleGoogleCredential });
    [['googleBtnLogin', 'continue_with'], ['googleBtnRegister', 'signup_with']].forEach(function (b) {
      var el = document.getElementById(b[0]);
      if (!el) return;
      var bloc = el.closest('[data-bloc-google]');
      if (bloc) {
        var afficher = function () { if (el.firstElementChild) { bloc.hidden = false; return true; } return false; };
        if (!afficher()) {
          var obs = new MutationObserver(function () { if (afficher()) obs.disconnect(); });
          obs.observe(el, { childList: true });
        }
      }
      google.accounts.id.renderButton(el, { theme: 'outline', size: 'large', shape: 'rectangular', text: b[1], width: largeurGoogle(el), locale: 'fr' });
    });
  }
  // La bibliotheque Google appelle window.onGoogleLibraryLoad une fois chargee, meme longtemps apres la page
  var rappelGoogle = window.onGoogleLibraryLoad;
  window.onGoogleLibraryLoad = function () {
    if (typeof rappelGoogle === 'function') rappelGoogle();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initGoogle);
    else initGoogle();
  };
  document.addEventListener('DOMContentLoaded', function () {
    initGoogle();
    var script = document.querySelector('script[src^="https://accounts.google.com/gsi/client"]');
    if (script) script.addEventListener('load', initGoogle);
  });

  // Le SDK Google peut laisser overflow:hidden sur <html> ou <body> : on le retire si aucune feuille n'est ouverte
  function restaurerScroll() {
    if (document.querySelector('dialog[open]')) return;
    [document.documentElement, document.body].forEach(function (el) {
      if (el && getComputedStyle(el).overflowY === 'hidden') el.style.setProperty('overflow', 'visible', 'important');
    });
  }
  new MutationObserver(restaurerScroll).observe(document.documentElement, { attributes: true, attributeFilter: ['style', 'class'] });

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }
})();
