const CACHE_NAME = 'monrevenu-actifs-v3';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((cles) => Promise.all(cles.filter((c) => c !== CACHE_NAME).map((c) => caches.delete(c))))
      .then(() => self.clients.claim())
  );
});

// Cache limite aux fichiers statiques de /assets/ (CSS versionne, polices, icones, scripts).
// Les pages PHP et les reponses authentifiees ne sont jamais mises en cache.
self.addEventListener('fetch', (event) => {
  const requete = event.request;
  if (requete.method !== 'GET') return;
  const url = new URL(requete.url);
  if (url.origin !== self.location.origin || !url.pathname.startsWith('/assets/')) return;
  event.respondWith(
    caches.open(CACHE_NAME).then((cache) =>
      cache.match(requete).then((trouve) => trouve || fetch(requete).then((reponse) => {
        if (reponse.ok) cache.put(requete, reponse.clone());
        return reponse;
      }))
    )
  );
});

// Réception d'une notification push (commissions, ventes, retraits, commandes)
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'MonRevenu';
  const options = {
    body: data.body || '',
    icon: '/assets/img/favicon/android-chrome-192x192.png',
    badge: '/assets/img/favicon/badge-96.png',
    tag: data.tag || 'monrevenu',
    renotify: data.renotify !== false,
    timestamp: data.timestamp || Date.now(),
    lang: 'fr',
    data: { url: data.url || '/dashboard.php' }
  };
  if (data.image) options.image = data.image;
  const travaux = [self.registration.showNotification(title, options)];
  // Compteur sur l'icone de l'application quand le navigateur le permet
  if (typeof data.badge_compteur === 'number' && self.navigator && 'setAppBadge' in self.navigator) {
    travaux.push(
      data.badge_compteur > 0
        ? self.navigator.setAppBadge(data.badge_compteur).catch(() => {})
        : self.navigator.clearAppBadge().catch(() => {})
    );
  }
  // Les onglets ouverts rafraichissent la cloche tout de suite, sans attendre leur prochain cycle
  travaux.push(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((liste) => {
      liste.forEach((client) => client.postMessage({ type: 'push-recu' }));
    })
  );
  event.waitUntil(Promise.all(travaux));
});

// Clic sur la notification -> ouvrir/focus l'app sur la bonne page, ou
// simplement mettre au premier plan un onglet déjà ouvert si possible.
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/dashboard.php';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientsList) => {
      for (const client of clientsList) {
        if (client.url.includes(url) && 'focus' in client) {
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(url);
      }
    })
  );
});