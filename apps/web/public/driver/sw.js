/* global self, caches, fetch, URL */
const CACHE = "laras-driver-shell-v1";
const SHELL = "/driver/app";

self.addEventListener("install", (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    const response = await fetch(SHELL, { cache: "reload" });
    if (!response.ok) throw new Error("Driver shell unavailable");
    const html = await response.clone().text();
    const assets = [...new Set([...html.matchAll(/(?:src|href)="(\/_next\/static\/[^"]+)"/g)].map((match) => match[1]))];
    await cache.addAll(assets);
    await cache.put(SHELL, response);
    await self.skipWaiting();
  })());
});

self.addEventListener("activate", (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter((name) => name.startsWith("laras-driver-shell-") && name !== CACHE).map((name) => caches.delete(name)));
    await self.clients.claim();
  })());
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== "GET" || url.origin !== self.location.origin) return;
  if (request.mode === "navigate" && url.pathname === SHELL) {
    event.respondWith((async () => {
      const cache = await caches.open(CACHE);
      try {
        const response = await fetch(request);
        if (response.ok) {
          const html = await response.clone().text();
          const assets = [...new Set([...html.matchAll(/(?:src|href)="(\/_next\/static\/[^"]+)"/g)].map((match) => match[1]))];
          const missing = (await Promise.all(assets.map(async (asset) => (await cache.match(asset)) ? null : asset))).filter(Boolean);
          if (missing.length) await cache.addAll(missing);
          await cache.put(SHELL, response.clone());
        }
        return response;
      } catch (error) {
        const saved = await cache.match(SHELL);
        if (saved) return saved;
        throw error;
      }
    })());
  } else if (url.pathname.startsWith("/_next/static/")) {
    event.respondWith((async () => {
      const cache = await caches.open(CACHE);
      const saved = await cache.match(request);
      if (saved) return saved;
      const response = await fetch(request);
      if (response.ok) await cache.put(request, response.clone());
      return response;
    })());
  }
});
