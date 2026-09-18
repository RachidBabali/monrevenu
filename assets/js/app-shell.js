// Scripts d'interface communs a toutes les pages (theme, feuilles, toasts, copie, partage, envoi).
(function () {
  'use strict';

  var MR = (window.MR = window.MR || {});

  // Theme : classe "dark" sur <html>, memorisee sous la cle "theme" (compatible avec l'existant)
  MR.basculerTheme = function () {
    var sombre = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('theme', sombre ? 'dark' : 'light'); } catch (e) {}
    document.querySelectorAll('[data-action="theme"]').forEach(function (b) {
      b.setAttribute('aria-pressed', sombre ? 'true' : 'false');
    });
  };
  window.toggleTheme = MR.basculerTheme;

  // Toasts
  MR.toast = function (message, duree) {
    var zone = document.getElementById('toasts');
    if (!zone) {
      zone = document.createElement('div');
      zone.id = 'toasts';
      zone.className = 'toasts';
      zone.setAttribute('role', 'status');
      zone.setAttribute('aria-live', 'polite');
      document.body.appendChild(zone);
    }
    var t = document.createElement('div');
    t.className = 'toast';
    t.textContent = message;
    zone.appendChild(t);
    setTimeout(function () { t.remove(); }, duree || 4000);
  };

  // Feuilles modales : <dialog class="feuille">, ouverture par data-ouvrir="id"
  MR.ouvrir = function (id) {
    var d = document.getElementById(id);
    if (!d) return;
    if (typeof d.showModal === 'function') {
      if (!d.open) d.showModal();
    } else {
      d.setAttribute('open', '');
    }
    var cible = d.querySelector('[autofocus]');
    if (cible) cible.focus();
  };
  MR.fermer = function (id) {
    var d = typeof id === 'string' ? document.getElementById(id) : id;
    if (d && d.open) d.close();
  };

  // Copie presse-papiers avec repli execCommand
  MR.copier = function (texte) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(texte);
    }
    return new Promise(function (ok, ko) {
      var zone = document.createElement('textarea');
      zone.value = texte;
      zone.setAttribute('readonly', '');
      zone.style.position = 'fixed';
      zone.style.opacity = '0';
      document.body.appendChild(zone);
      zone.select();
      try { document.execCommand('copy') ? ok() : ko(); } catch (e) { ko(e); }
      zone.remove();
    });
  };

  function retourBouton(bouton, texte, classe) {
    var libelle = bouton.querySelector('[data-libelle]') || bouton;
    if (!bouton.dataset.libelleInitial) bouton.dataset.libelleInitial = libelle.textContent;
    libelle.textContent = texte;
    if (classe) bouton.classList.add(classe);
    clearTimeout(bouton._minuteur);
    bouton._minuteur = setTimeout(function () {
      libelle.textContent = bouton.dataset.libelleInitial;
      if (classe) bouton.classList.remove(classe);
    }, 2000);
  }

  // Partage : navigator.share si disponible, sinon WhatsApp (wa.me)
  MR.partager = function (url, texte) {
    var message = (texte ? texte + ' ' : '') + url;
    if (navigator.share) {
      return navigator.share({ text: texte || '', url: url }).catch(function () {});
    }
    window.open('https://wa.me/?text=' + encodeURIComponent(message), '_blank', 'noopener');
  };

  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-ouvrir],[data-fermer],[data-copier],[data-partager],[data-whatsapp],[data-action="theme"]');
    if (!el) return;

    if (el.hasAttribute('data-ouvrir')) {
      ev.preventDefault();
      MR.ouvrir(el.getAttribute('data-ouvrir'));
    } else if (el.hasAttribute('data-fermer')) {
      ev.preventDefault();
      MR.fermer(el.closest('dialog'));
    } else if (el.hasAttribute('data-copier')) {
      ev.preventDefault();
      MR.copier(el.getAttribute('data-copier')).then(
        function () { retourBouton(el, el.getAttribute('data-copie-ok') || 'Lien copié', 'btn-copie-ok'); },
        function () { MR.toast('Copie impossible. Appuyez longuement sur le lien pour le copier.'); }
      );
    } else if (el.hasAttribute('data-partager')) {
      ev.preventDefault();
      MR.partager(el.getAttribute('data-partager'), el.getAttribute('data-texte'));
    } else if (el.hasAttribute('data-whatsapp')) {
      ev.preventDefault();
      var msg = (el.getAttribute('data-texte') ? el.getAttribute('data-texte') + ' ' : '') + el.getAttribute('data-whatsapp');
      window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank', 'noopener');
    } else {
      MR.basculerTheme();
    }
  });

  // Clic sur le voile d'une feuille : fermeture
  document.addEventListener('click', function (ev) {
    var d = ev.target;
    if (d.tagName === 'DIALOG' && d.classList.contains('feuille') && d.open) {
      var r = d.getBoundingClientRect();
      if (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom) d.close();
    }
  });

  // Formulaires : etat "Envoi en cours" sans desactiver le bouton (son name doit partir dans le POST)
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form.dataset.envoye === '1') { ev.preventDefault(); return; }
    if (ev.defaultPrevented) return;
    form.dataset.envoye = '1';
    var bouton = ev.submitter || form.querySelector('button[type="submit"],button:not([type])');
    if (bouton && bouton.classList.contains('btn')) {
      bouton.setAttribute('aria-busy', 'true');
      var libelle = bouton.querySelector('[data-libelle]');
      if (libelle) libelle.textContent = 'Envoi en cours';
    }
  });

  // Retour arriere (bfcache) : on reactive les formulaires
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-envoye]').forEach(function (f) { delete f.dataset.envoye; });
    document.querySelectorAll('.btn[aria-busy="true"]').forEach(function (b) { b.removeAttribute('aria-busy'); });
  });

  document.addEventListener('DOMContentLoaded', function () {
    var sombre = document.documentElement.classList.contains('dark');
    document.querySelectorAll('[data-action="theme"]').forEach(function (b) {
      b.setAttribute('aria-pressed', sombre ? 'true' : 'false');
    });
  });
})();
