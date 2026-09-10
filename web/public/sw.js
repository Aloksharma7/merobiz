// Deliberately minimal. This app shows live financial figures (available
// balance, invoices, payments), so caching pages or API responses here would
// risk silently showing stale money numbers — not worth the offline-support
// tradeoff. This service worker exists only so the browser treats the app as
// installable; it never intercepts or caches anything.
self.addEventListener("install", (event) => {
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener("fetch", () => {
  // No-op: every request always goes straight to the network.
});
