const CACHE_NAME = 'monrevenu-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Réception d'une notification push (Firebase l'utilisera plus tard)
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'MonRevenu';
  const options = {
    body: data.body || '',
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/icon-192.png'
  };
  event.waitUntil(self.clients.claim());
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// Clic sur la notification -> ouvrir/focus l'app
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    self.clients.openWindow('/index.php')
  );
});