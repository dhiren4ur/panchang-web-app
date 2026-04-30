// sw.js — Service Worker for ગુજ કૅલેન્ડર
// Strategy: Cache-first for static assets, Network-first for API calls

const CACHE_NAME = 'guj-cal-v3';

// Files to cache on install (app shell)
const STATIC_ASSETS = [
  './index.html',
  './calendar.js?v=3',
  './panchang.html',
  './muhurat.html',
  './nakshatra.html',
  './wallpaper.html',
  './login.html',
  './register.html',
  './manifest.json',
  './icon-192.png',
  './icon-512.png',
  'https://fonts.googleapis.com/css2?family=Noto+Sans+Gujarati:wght@400;600;700&display=swap',
];

// ── Install: cache all static assets ──────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(STATIC_ASSETS).catch(err => {
        console.warn('SW: some assets failed to cache', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// ── Activate: clean old caches ────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch: smart routing ──────────────────────────────────────
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // 1. API calls → Network first, no cache
  //    (panchang data must always be fresh)
  if (url.pathname.includes('api.php') || url.pathname.includes('wallpapers.php')) {
    event.respondWith(
      fetch(event.request).catch(() =>
        new Response(JSON.stringify({
          success: false,
          message: 'Offline — no network connection'
        }), { headers: { 'Content-Type': 'application/json' } })
      )
    );
    return;
  }

  // 2. Wallpaper images → Cache first, then network
  //    (large images, load fast from cache)
  if (url.pathname.includes('/wallpapers/')) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) return cached;
        return fetch(event.request).then(response => {
          // Cache wallpapers as they load
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
          }
          return response;
        }).catch(() => new Response('', { status: 404 }));
      })
    );
    return;
  }

  // 3. Google Fonts → Cache first
  if (url.hostname.includes('fonts.googleapis.com') || url.hostname.includes('fonts.gstatic.com')) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) return cached;
        return fetch(event.request).then(response => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
          return response;
        });
      })
    );
    return;
  }

  // 4. HTML/JS/CSS app files → Cache first, fallback to network
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        // Cache successful responses
        if (response.ok && event.request.method === 'GET') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
        }
        return response;
      }).catch(() => {
        // Offline fallback: return index.html for navigation requests
        if (event.request.mode === 'navigate') {
          return caches.match('./index.html');
        }
        return new Response('', { status: 404 });
      });
    })
  );
});

// ── Push notification support (future use) ────────────────────
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});