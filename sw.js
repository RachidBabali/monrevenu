const CACHE_NAME = 'monrevenu-actifs-v2';

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

// Réception d'une notification push (commissions, ventes, retraits)
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'MonRevenu';
  const options = {
    body: data.body || '',
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/icon-192.png',
    data: { url: data.url || '/dashboard.php' }
  };
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
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