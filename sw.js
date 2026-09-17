const CACHE_NAME = 'monrevenu-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Réception d'une notification push (commissions, ventes, retraits/transferts...)
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