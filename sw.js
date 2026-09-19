/* offline: cache-first pre statické súbory, verzia sa mení pri každom nasadení */
const VERZIA = "nj-v1";
const SUBORY = ["./", "index.html", "style.css", "app.js", "postava.js", "sekvencie.js", "manifest.json", "ikona.svg"];
self.addEventListener("install", e => { e.waitUntil(caches.open(VERZIA).then(c => c.addAll(SUBORY)).then(() => self.skipWaiting())); });
self.addEventListener("activate", e => { e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== VERZIA).map(k => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener("fetch", e => {
  if (e.request.method !== "GET") return;
  e.respondWith(fetch(e.request).then(r => { const k = r.clone(); caches.open(VERZIA).then(c => c.put(e.request, k)); return r; }).catch(() => caches.match(e.request)));
});
