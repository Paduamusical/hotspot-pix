self.addEventListener('push', function(event) {
  var data = {};
  try { data = event.data ? event.data.json() : {}; } catch(e) {}
  event.waitUntil(
    self.registration.showNotification(data.title || 'Internet Liberada', {
      body: data.body || 'Seu plano foi ativado com sucesso! Voce ja pode navegar.',
      icon: '/assets/img/logo-accesnet.jpg',
      badge: '/assets/img/logo-accesnet.jpg',
      data: { url: data.url || '/' },
      vibrate: [200, 100, 200],
      tag: 'hotspot-pix',
      requireInteraction: true
    })
  );
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data.url || '/')
  );
});
