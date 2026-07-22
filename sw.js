/**
 * WPU Jobs - Service Worker
 * Provides offline support and caching for the applicant portal
 */

// Default configuration - will be updated when config is fetched
let CACHE_NAME = 'wpu-jobs-v1';
let OFFLINE_URL = '/Applicant_WPU/offline.html';
let BASE_PATH = '/Applicant_WPU/';

// Assets to cache on install (default paths)
let PRECACHE_ASSETS = [
  '/Applicant_WPU/assets/css/office-theme.css',
  '/Applicant_WPU/assets/js/office-theme.js',
  '/Applicant_WPU/assets/js/toast.js',
  '/Applicant_WPU/assets/js/notifications.js',
  '/Applicant_WPU/assets/logo.png',
  '/Applicant_WPU/assets/pwa/icon-192x192.png',
  '/Applicant_WPU/assets/pwa/icon-512x512.png',
  '/Applicant_WPU/favicon.ico',
  '/Applicant_WPU/offline.html',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Try to determine base path from service worker location
const swLocation = self.location.pathname;
const swPathMatch = swLocation.match(/^(\/[^\/]+\/)/);
if (swPathMatch) {
  BASE_PATH = swPathMatch[1];
  OFFLINE_URL = BASE_PATH + 'offline.html';
  PRECACHE_ASSETS = [
    BASE_PATH + 'assets/css/office-theme.css',
    BASE_PATH + 'assets/js/office-theme.js',
    BASE_PATH + 'assets/js/toast.js',
    BASE_PATH + 'assets/js/notifications.js',
    BASE_PATH + 'assets/logo.png',
    BASE_PATH + 'assets/pwa/icon-192x192.png',
    BASE_PATH + 'assets/pwa/icon-512x512.png',
    BASE_PATH + 'favicon.ico',
    BASE_PATH + 'offline.html',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
  ];
}

// Install event - cache core assets
self.addEventListener('install', (event) => {
  console.log('[SW] Installing service worker...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] Precaching core assets');
        return cache.addAll(PRECACHE_ASSETS);
      })
      .then(() => {
        console.log('[SW] Service worker installed successfully');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('[SW] Failed to precache assets:', error);
      })
  );
});

// Activate event - cleanup old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating service worker...');
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name !== CACHE_NAME)
            .map((name) => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => {
        console.log('[SW] Service worker activated');
        return self.clients.claim();
      })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Skip API requests and form submissions
  if (url.pathname.includes('/api/') || 
      url.pathname.includes('/actions/') ||
      url.pathname.includes('/auth/')) {
    return;
  }
  
  // Handle navigation requests (HTML pages)
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Cache successful navigation responses
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          // Try to serve from cache first
          return caches.match(request)
            .then((cachedResponse) => {
              if (cachedResponse) {
                return cachedResponse;
              }
              // Serve offline page as fallback
              return caches.match(OFFLINE_URL);
            });
        })
    );
    return;
  }
  
  // Cache-first strategy for static assets
  if (isStaticAsset(url.pathname)) {
    event.respondWith(
      caches.match(request)
        .then((cachedResponse) => {
          if (cachedResponse) {
            // Return cached version and update cache in background
            fetchAndCache(request);
            return cachedResponse;
          }
          return fetchAndCache(request);
        })
        .catch(() => {
          // Return a placeholder for failed image requests
          if (request.destination === 'image') {
            return new Response(
              '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect fill="#f0f0f0" width="100" height="100"/><text fill="#999" font-family="sans-serif" font-size="12" x="50" y="50" text-anchor="middle" dy=".3em">Image</text></svg>',
              { headers: { 'Content-Type': 'image/svg+xml' } }
            );
          }
        })
    );
    return;
  }
  
  // Network-first strategy for dynamic content
  event.respondWith(
    fetch(request)
      .then((response) => {
        // Cache successful responses
        if (response.status === 200) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, responseClone);
          });
        }
        return response;
      })
      .catch(() => {
        return caches.match(request);
      })
  );
});

// Helper function to check if a path is a static asset
function isStaticAsset(pathname) {
  const staticExtensions = [
    '.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico',
    '.woff', '.woff2', '.ttf', '.eot', '.mp3', '.webp'
  ];
  return staticExtensions.some(ext => pathname.endsWith(ext));
}

// Helper function to fetch and cache
function fetchAndCache(request) {
  return fetch(request)
    .then((response) => {
      if (response.status === 200) {
        const responseClone = response.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(request, responseClone);
        });
      }
      return response;
    });
}

// Handle push notifications
self.addEventListener('push', (event) => {
  if (!event.data) return;
  
  try {
    const data = event.data.json();
    
    const options = {
      body: data.body || 'You have a new notification',
      icon: BASE_PATH + 'assets/pwa/icon-192x192.png',
      badge: BASE_PATH + 'assets/pwa/icon-96x96.png',
      vibrate: [100, 50, 100],
      data: {
        url: data.url || BASE_PATH + 'applicant/pages/dashboard.php',
        dateOfArrival: Date.now()
      },
      actions: [
        { action: 'open', title: 'Open' },
        { action: 'close', title: 'Dismiss' }
      ],
      tag: data.tag || 'wpu-notification',
      renotify: true
    };
    
    event.waitUntil(
      self.registration.showNotification(data.title || 'WPU Jobs', options)
    );
  } catch (error) {
    console.error('[SW] Error handling push notification:', error);
  }
});

// Handle notification click
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  
  if (event.action === 'close') {
    return;
  }
  
  const urlToOpen = event.notification.data?.url || BASE_PATH + 'applicant/pages/dashboard.php';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Check if there's already a window open
        for (const client of clientList) {
          if (client.url.includes(BASE_PATH) && 'focus' in client) {
            client.navigate(urlToOpen);
            return client.focus();
          }
        }
        // Open new window if no existing window found
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

// Handle background sync
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-applications') {
    event.waitUntil(syncApplications());
  }
});

// Sync applications when back online
async function syncApplications() {
  console.log('[SW] Syncing applications...');
  // This can be extended to handle offline form submissions
}

// Message handler for communication with main thread
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    caches.delete(CACHE_NAME).then(() => {
      console.log('[SW] Cache cleared');
    });
  }
});

