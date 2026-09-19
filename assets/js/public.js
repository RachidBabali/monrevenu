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
      confirmCode: function (v) { return v !== code.value ? 'Les deux mots de passe sont différents.' : ''; }
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

  var essais = 0;
  function initGoogle() {
    var meta = document.querySelector('meta[name="google-signin-client_id"]');
    var clientId = meta ? meta.content : '';
    if (!clientId || clientId.indexOf('YOUR_GOOGLE_CLIENT_ID') > -1) return;
    if (!window.google || !google.accounts || !google.accounts.id) {
      if (++essais < 40) setTimeout(initGoogle, 300);
      return;
    }
    google.accounts.id.initialize({ client_id: clientId, callback: handleGoogleCredential });
    [['googleBtnLogin', 'continue_with'], ['googleBtnRegister', 'signup_with']].forEach(function (b) {
      var el = document.getElementById(b[0]);
      if (!el) return;
      var bloc = el.closest('[data-bloc-google]');
      if (bloc) bloc.hidden = false;
      google.accounts.id.renderButton(el, { theme: 'outline', size: 'large', shape: 'rectangular', text: b[1], width: 300, locale: 'fr' });
      // Si le bouton n'a rien rendu (identifiant refuse, iframe bloquee), le separateur et l'emplacement disparaissent
      setTimeout(function () { if (bloc && !el.firstElementChild) bloc.hidden = true; }, 3000);
    });
  }
  // Le bloc (separateur "ou" + emplacement) reste masque tant que le bouton Google n'est pas rendu :
  // identifiant vide, script bloque ou echec de chargement le laissent donc cache.
  document.addEventListener('DOMContentLoaded', initGoogle);

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
