// Cloche de notifications, abonnement Web Push et enregistrement du service worker.
// Reprend le comportement de js/app.js ; le texte des notifications est insere avec textContent.
(function () {
  'use strict';

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
  }

  var cloches = document.querySelectorAll('[data-notif-bell]');
  if (!cloches.length) return;

  var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var vapidPublicKey = (document.querySelector('meta[name="vapid-public-key"]') || {}).content || '';
  var SVG = 'http://www.w3.org/2000/svg';
  var ICONES = {
    "hand-coins": "<path d=\"M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17\"/> <path d=\"m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9\"/> <path d=\"m2 16 6 6\"/> <circle cx=\"16\" cy=\"9\" r=\"2.9\"/> <circle cx=\"6\" cy=\"5\" r=\"3\"/>",
    "banknote": "<rect width=\"20\" height=\"12\" x=\"2\" y=\"6\" rx=\"2\"/> <circle cx=\"12\" cy=\"12\" r=\"2\"/> <path d=\"M6 12h.01M18 12h.01\"/>",
    "package": "<path d=\"M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z\"/> <path d=\"M12 22V12\"/> <path d=\"m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7\"/> <path d=\"m7.5 4.27 9 5.15\"/>",
    "bell": "<path d=\"M10.268 21a2 2 0 0 0 3.464 0\"/> <path d=\"M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326\"/>"
  };

  // Nettoyage a l'affichage des anciennes notifications (emojis, fleches, tirets longs).
  // Les classes de caracteres sont construites a partir des points de code, sans sequence d'echappement.
  var c = String.fromCodePoint;
  var classe = function (plages) {
    return '[' + plages.map(function (p) { return Array.isArray(p) ? c(p[0]) + '-' + c(p[1]) : c(p); }).join('') + ']';
  };
  var RE_EMOJI = new RegExp(classe([[0x1F000, 0x1FAFF], [0x2600, 0x27BF], [0x2B00, 0x2BFF], [0x2300, 0x23FF], 0xFE0F, 0x20E3, 0x200D]), 'gu');
  var RE_FLECHES = new RegExp(classe([[0x2190, 0x21FF], 0x2794, 0x27A1, 0x2022, [0x25B2, 0x25C6]]), 'gu');
  var RE_TIRETS = new RegExp('\\s*' + classe([0x2013, 0x2014]) + '\\s*', 'g');
  var RE_ESPACES = new RegExp(classe([0x00A0, 0x202F]), 'g');
  function nettoyer(texte) {
    return String(texte || '')
      .replace(RE_EMOJI, '')
      .replace(RE_FLECHES, '')
      .replace(RE_TIRETS, ', ')
      .replace(RE_ESPACES, ' ')
      .replace(/\s{2,}/g, ' ')
      .replace(/^[\s,]+|[\s,]+$/g, '');
  }

  function typeDe(texte) {
    var t = texte.toLowerCase();
    if (t.indexOf('retrait') > -1) return 'banknote';
    if (/commission|vente|commande/.test(t)) return 'hand-coins';
    if (t.indexOf('stock') > -1) return 'package';
    return 'bell';
  }

  function icone(nom) {
    var svg = document.createElementNS(SVG, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.75');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', 'ico ico-16');
    svg.innerHTML = ICONES[nom];
    return svg;
  }

  function element(tag, classe, texte) {
    var el = document.createElement(tag);
    if (classe) el.className = classe;
    if (texte !== undefined) el.textContent = texte;
    return el;
  }

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(base64);
    var sortie = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) sortie[i] = raw.charCodeAt(i);
    return sortie;
  }

  function rafraichir(cloche) {
    fetch('/includs/notifications_api.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        var badge = cloche.querySelector('.notif-badge');
        if (data.unread > 0) {
          badge.textContent = data.unread > 99 ? '99+' : data.unread;
          badge.classList.remove('hidden');
          badge.classList.add('flex');
        } else {
          badge.classList.add('hidden');
          badge.classList.remove('flex');
        }
        var liste = cloche.querySelector('.notif-liste');
        liste.textContent = '';
        if (!data.notifications.length) {
          liste.appendChild(element('p', 'notif-vide px-4 py-8 text-center text-sm text-text-3', 'Aucune notification.'));
          return;
        }
        data.notifications.forEach(function (n) {
          var message = nettoyer(n.message);
          var item = element('button', 'notif-item flex w-full items-start gap-3 border-b border-line px-4 py-3 text-left last:border-b-0 hover:bg-surface-2' + (n.non_lu ? ' bg-primary-soft' : ''));
          item.type = 'button';
          item.dataset.id = n.id;
          item.dataset.lu = n.non_lu ? '0' : '1';
          var pastille = element('span', 'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-2 text-text-2');
          pastille.appendChild(icone(typeDe(message)));
          var corps = element('span', 'min-w-0 flex-1');
          corps.appendChild(element('span', 'block text-sm text-text', message));
          corps.appendChild(element('span', 'mt-1 block text-xs text-text-3', String(n.date || '').replace(' à ', ' ')));
          item.appendChild(pastille);
          item.appendChild(corps);
          if (n.non_lu) item.appendChild(element('span', 'sr-only', 'Non lu'));
          liste.appendChild(item);
        });
      })
      .catch(function () {});
  }

  function proposerPush(cloche) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !vapidPublicKey) return;
    navigator.serviceWorker.ready.then(function (registration) {
      registration.pushManager.getSubscription().then(function (existant) {
        if (existant || Notification.permission === 'denied') return;
        var bouton = cloche.querySelector('.notif-activer-push');
        if (bouton) bouton.classList.remove('hidden');
      });
    });
  }

  function abonnerPush(cloche) {
    navigator.serviceWorker.ready.then(function (registration) {
      return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
      });
    }).then(function (subscription) {
      var corps = subscription.toJSON();
      corps.action = 'subscribe';
      corps.csrf_token = csrfToken;
      return fetch('/includs/push_subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(corps)
      });
    }).then(function () {
      var bouton = cloche.querySelector('.notif-activer-push');
      if (bouton) bouton.classList.add('hidden');
      if (window.MR) window.MR.toast('Notifications activées sur ce téléphone.');
    }).catch(function () {
      if (window.MR) window.MR.toast("Les notifications n'ont pas pu être activées. Vérifiez les autorisations du navigateur.");
    });
  }

  function fermerTout() {
    document.querySelectorAll('[data-notif-bell]').forEach(function (c) {
      c.querySelector('.notif-panel').classList.add('hidden');
      c.querySelector('.notif-bell-toggle').setAttribute('aria-expanded', 'false');
    });
  }

  cloches.forEach(function (cloche) {
    var bouton = cloche.querySelector('.notif-bell-toggle');
    var panneau = cloche.querySelector('.notif-panel');

    bouton.addEventListener('click', function (e) {
      e.stopPropagation();
      var ouvert = !panneau.classList.contains('hidden');
      fermerTout();
      if (!ouvert) {
        panneau.classList.remove('hidden');
        bouton.setAttribute('aria-expanded', 'true');
        rafraichir(cloche);
      }
    });
    panneau.addEventListener('click', function (e) { e.stopPropagation(); });

    cloche.querySelector('.notif-marquer-tout').addEventListener('click', function () {
      fetch('/includs/notifications_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'marquer_tout_lu', csrf_token: csrfToken })
      }).then(function () { rafraichir(cloche); });
    });

    cloche.querySelector('.notif-liste').addEventListener('click', function (e) {
      var item = e.target.closest('.notif-item');
      if (!item || item.dataset.lu === '1') return;
      fetch('/includs/notifications_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'marquer_lu', id: Number(item.dataset.id), csrf_token: csrfToken })
      }).then(function () { rafraichir(cloche); });
    });

    cloche.querySelector('.notif-activer-push').addEventListener('click', function () { abonnerPush(cloche); });

    rafraichir(cloche);
    proposerPush(cloche);
    setInterval(function () { rafraichir(cloche); }, 30000);
  });

  document.addEventListener('click', fermerTout);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fermerTout(); });
})();
