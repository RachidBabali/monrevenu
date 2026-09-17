 // Configuration (à adapter avec vos endpoints réels)
  const CONFIG = {
    GOOGLE_CLIENT_ID: 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
    REGISTER_URL: 'register.php',           // URL de votre backend d'inscription classique
    GOOGLE_REGISTER_URL: 'google_register.php',
    DASHBOARD_URL: 'dashboard.php',
  };

  // Afficher/masquer mot de passe
  function togglePassword(inputId, eyeOpenId, eyeOffId) {
    const input = document.getElementById(inputId);
    const eyeOpen = document.getElementById(eyeOpenId);
    const eyeOff = document.getElementById(eyeOffId);
    if (input.type === 'password') {
      input.type = 'text';
      eyeOpen.style.display = 'none';
      eyeOff.style.display = 'block';
    } else {
      input.type = 'password';
      eyeOpen.style.display = 'block';
      eyeOff.style.display = 'none';
    }
  }

  // Utilitaires d'affichage
  function showMsg(el, txt, isError = true) {
    el.textContent = txt;
    el.style.display = 'block';
    if (isError) {
      document.getElementById('registerOk').style.display = 'none';
    } else {
      document.getElementById('registerErr').style.display = 'none';
    }
  }

  function setLoading(btn, on) {
    if (on) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span>Inscription...';
    } else {
      btn.disabled = false;
      btn.innerHTML = "S'INSCRIRE";
    }
  }

  // Validation des champs
  function validateEmail(email) {
    return /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/.test(email);
  }
  function validatePhone(phone) {
    return /^[\d\s\-+]{6,20}$/.test(phone);
  }
  function validateFullName(name) {
    return name.trim().length >= 2;
  }

  // Gestion principale de l'inscription classique
  function handleRegister() {
    const errEl = document.getElementById('registerErr');
    const okEl = document.getElementById('registerOk');
    const btn = document.getElementById('btnRegister');
    errEl.style.display = 'none';
    okEl.style.display = 'none';

    const fullName = document.getElementById('fullName').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const pwd = document.getElementById('password').value;
    const confirmPwd = document.getElementById('confirmPassword').value;
    const acceptTerms = document.getElementById('acceptTerms').checked;

    if (!fullName) return showMsg(errEl, 'Veuillez indiquer votre nom complet.');
    if (!validateFullName(fullName)) return showMsg(errEl, 'Nom complet trop court (minimum 2 caractères).');
    if (!email) return showMsg(errEl, 'Adresse email requise.');
    if (!validateEmail(email)) return showMsg(errEl, 'Format d\'email invalide (exemple@domaine.com).');
    if (!phone) return showMsg(errEl, 'Numéro de téléphone requis.');
    if (!validatePhone(phone)) return showMsg(errEl, 'Numéro de téléphone invalide (6 à 20 chiffres, espaces, +, -).');
    if (!pwd) return showMsg(errEl, 'Mot de passe requis.');
    if (pwd.length < 6) return showMsg(errEl, 'Le mot de passe doit contenir au moins 6 caractères.');
    if (pwd !== confirmPwd) return showMsg(errEl, 'Les mots de passe ne correspondent pas.');
    if (!acceptTerms) return showMsg(errEl, 'Vous devez accepter les conditions générales.');

    setLoading(btn, true);

    // --- Exemple d'appel réel (décommentez et adaptez votre endpoint)
    /*
    fetch(CONFIG.REGISTER_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        fullname: fullName,
        email: email,
        phone: phone,
        password: pwd
      })
    })
    .then(r => r.text())
    .then(resp => {
      if (resp.startsWith('redirect:')) {
        showMsg(okEl, '✓ Compte créé avec succès ! Redirection...', false);
        setTimeout(() => window.location.href = CONFIG.DASHBOARD_URL, 800);
      } else {
        showMsg(errEl, resp.trim() || 'Erreur lors de l\'inscription.');
        setLoading(btn, false);
      }
    })
    .catch(() => { showMsg(errEl, 'Erreur réseau. Réessayez.'); setLoading(btn, false); });
    */

    // Simulation de succès (à remplacer par l'appel réel)
    setTimeout(() => {
      showMsg(okEl, '✓ Inscription réussie ! Bienvenue sur MonRevenu.', false);
      setTimeout(() => window.location.href = CONFIG.DASHBOARD_URL, 1000);
    }, 1200);
  }

  // Inscription avec Google
  function signUpWithGoogle() {
    if (typeof google === 'undefined') {
      showMsg(document.getElementById('registerErr'), 'Google Sign-In non disponible. Vérifiez votre connexion.');
      return;
    }
    const client = google.accounts.oauth2.initTokenClient({
      client_id: CONFIG.GOOGLE_CLIENT_ID,
      scope: 'email profile openid',
      callback: (tokenResponse) => {
        if (tokenResponse.error) {
          showMsg(document.getElementById('registerErr'), 'Erreur Google : ' + tokenResponse.error);
          return;
        }
        sendGoogleRegisterToken(tokenResponse.access_token);
      }
    });
    client.requestAccessToken();
  }

  function sendGoogleRegisterToken(token) {
    const errEl = document.getElementById('registerErr');
    const okEl = document.getElementById('registerOk');
    // --- Appel backend réel (décommentez)
    /*
    fetch(CONFIG.GOOGLE_REGISTER_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ google_token: token })
    })
    .then(r => r.text())
    .then(resp => {
      if (resp.startsWith('redirect:')) {
        showMsg(okEl, '✓ Inscription Google réussie !', false);
        setTimeout(() => window.location.href = CONFIG.DASHBOARD_URL, 600);
      } else {
        showMsg(errEl, resp.trim() || 'Erreur lors de l\'inscription avec Google.');
      }
    })
    .catch(() => showMsg(errEl, 'Erreur réseau.'));
    */
    // Simulation
    showMsg(okEl, '✓ Inscription Google réussie (démo) !', false);
    setTimeout(() => window.location.href = CONFIG.DASHBOARD_URL, 900);
  }

  // Inscription avec Facebook
  function signUpWithFacebook() {
    const errEl = document.getElementById('registerErr');
    if (typeof FB === 'undefined') {
      showMsg(errEl, 'Facebook SDK non chargé. Ajoutez le SDK Facebook pour activer cette fonction.');
      return;
    }
    FB.login(function(response) {
      if (response.authResponse) {
        // --- Appel backend réel
        /*
        fetch(CONFIG.REGISTER_URL.replace('register', 'facebook_register'), {
          method: 'POST',
          body: new URLSearchParams({ fb_token: response.authResponse.accessToken })
        })
        .then(r => r.text())
        .then(resp => {
          if (resp.startsWith('redirect:')) {
            showMsg(document.getElementById('registerOk'), '✓ Inscription Facebook réussie !', false);
            setTimeout(() => window.location.href = CONFIG.DASHBOARD_URL, 600);
          } else {
            showMsg(errEl, resp.trim() || 'Erreur Facebook.');
          }
        });
        */
        // Simulation
        showMsg(document.getElementById('registerOk'), '✓ Inscription Facebook réussie (démo) !', false);
        setTimeout(() => window.location.href = CONFIG.DASHBOARD_URL, 900);
      } else {
        showMsg(errEl, 'Connexion Facebook annulée ou refusée.');
      }
    }, { scope: 'public_profile,email' });
  }

  // Soumission avec la touche Entrée
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      const active = document.activeElement;
      if (active && active.tagName === 'INPUT' && !active.closest('.eye-btn')) {
        handleRegister();
      }
    }
  });