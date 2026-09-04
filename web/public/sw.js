// Minimal hand-written service worker — no Workbox. It caches the static
// app shell only, never API responses: patient/care data must always come
// from the network, since offline read/write consistency for that data is
// a dedicated problem this phase deliberately doesn't take on (see the
// Phase 6 plan's Mobile Carer App scope notes).
//
// Bumped to v2: v1 used stale-while-revalidate for the page shell itself
// (index.html), which serves whatever was cached on the *previous* visit
// and only refreshes the cache in the background for the visit after that.
// On a normally-connected device that's pure downside — it just means every
// deploy is invisible until a second reload — and it actively bit us: after
// shipping several fixes in a row, still-open sessions kept rendering a
// build from hours earlier with none of them. The cache name change forces
// every existing v1 cache to be dropped on activate.
const CACHE_NAME = "carenexa360-shell-v2";
const APP_SHELL = ["/", "/manifest.webmanifest", "/icon.svg", "/favicon.svg"];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);

  // Never intercept the API — always hit the network directly.
  if (url.pathname.startsWith("/api/") || url.pathname.startsWith("/sanctum/")) {
    return;
  }

  if (event.request.method !== "GET") {
    return;
  }

  // Page navigations (the HTML shell) are network-first: a connected device
  // always gets what's actually live, and the cache is purely the offline
  // fallback. This is the one request type that determines which build the
  // rest of the app loads, so it must never prefer a stale copy.
  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response.ok) {
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, response.clone()));
          }
          return response;
        })
        .catch(() => caches.open(CACHE_NAME).then((cache) => cache.match(event.request))),
    );
    return;
  }

  // Everything else (hashed JS/CSS bundles, icons, the manifest) is safe
  // under stale-while-revalidate: bundle filenames change with their content,
  // so a cached entry for a given URL is never stale — it's either the exact
  // build that URL has always meant, or a request for a URL that doesn't
  // exist yet, which falls through to the network below regardless.
  event.respondWith(
    caches.open(CACHE_NAME).then((cache) =>
      cache.match(event.request).then((cached) => {
        const network = fetch(event.request)
          .then((response) => {
            if (response.ok) cache.put(event.request, response.clone());
            return response;
          })
          .catch(() => cached);

        return cached ?? network;
      }),
    ),
  );
});
