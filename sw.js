const CACHE_NAME = 'notesphere-v1';
const assetsToCache = [
  './', // para sa index/landing page
  'auth/login.php',
  'dashboard/student_dashboard.php', // I-adjust mo kung nasaan mang folder ito
  'assets/logo.png'
];


// Install Event
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(assetsToCache);
    })
  );
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event (Offline support)
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request).catch(() => {
        // Optional: Pwede kang maglagay ng offline fallback page dito kung sakali
      });
    })
  );
});