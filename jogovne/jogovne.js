/* verejný adresár: poloha + anonymné štatistiky (bez cookies) */
(() => {
  const b = document.getElementById('btn-poloha');
  if (b) b.onclick = () => { if (!navigator.geolocation) return alert('Prehliadač nevie zistiť polohu — zadaj mesto.'); b.textContent = 'zisťujem…'; navigator.geolocation.getCurrentPosition(p => { document.getElementById('lat').value = p.coords.latitude.toFixed(5); document.getElementById('lng').value = p.coords.longitude.toFixed(5); document.getElementById('f-filtre').submit(); }, () => { b.textContent = '📍 poloha nepovolená — zadaj mesto'; }, { timeout: 8000 }); };
  const posli = (p, u, o) => { const fd = new FormData(); fd.append('p', p); fd.append('u', u); fd.append('o', o || ''); navigator.sendBeacon('/jogovne/udalost', fd); };
  const art = document.querySelector('[data-p]'); if (!art) return;
  const pid = art.dataset.p, cid = art.dataset.c || '';
  document.querySelectorAll('[data-u]').forEach(a => a.addEventListener('click', () => posli(pid, a.dataset.u, cid)));
  const kon = document.getElementById('koniec-clanku');
  if (kon && 'IntersectionObserver' in window) { let ok = false; new IntersectionObserver(e => { if (e[0].isIntersecting && !ok) { ok = true; posli(pid, 'clanok_docitanie', cid); } }).observe(kon); }
})();
