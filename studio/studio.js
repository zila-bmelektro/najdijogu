/* studio.najdijogu.sk — malé pomôcky bez knižníc */
(() => {
  // tabuľky služieb/akcií: + riadok, ✕ riadok
  document.querySelectorAll('.pridaj').forEach(b => b.onclick = () => { const t = document.getElementById(b.dataset.tab).querySelector('tbody'); const r = t.rows[t.rows.length - 1].cloneNode(true); r.querySelectorAll('input').forEach(i => i.value = ''); t.appendChild(r); vazby(); });
  const vazby = () => document.querySelectorAll('.tab.edit .zmaz').forEach(b => b.onclick = () => { const tb = b.closest('tbody'); if (tb.rows.length > 1) b.closest('tr').remove(); else b.closest('tr').querySelectorAll('input').forEach(i => i.value = ''); });
  vazby();
  // návrhy tém → do políčka
  document.querySelectorAll('.navrh').forEach(a => a.onclick = e => { e.preventDefault(); document.querySelector('input[name=namet]').value = a.dataset.t; });
  // geokódovanie adresy (Nominatim, bez kľúča; stačí pre polohu jogovne)
  const g = document.getElementById('btn-geokod');
  if (g) g.onclick = async () => {
    const a = document.getElementById('adresa').value.trim(); const s = document.getElementById('geo-stav'); if (!a) return;
    s.textContent = 'hľadám…';
    try {
      const r = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=sk,cz&q=' + encodeURIComponent(a), { headers: { 'Accept-Language': 'sk' } });
      const j = await r.json();
      if (j[0]) { document.getElementById('lat').value = (+j[0].lat).toFixed(6); document.getElementById('lng').value = (+j[0].lon).toFixed(6); s.textContent = 'nájdené: ' + j[0].display_name.slice(0, 80) + ' — nezabudni Uložiť'; }
      else s.textContent = 'adresa sa nenašla — skús pridať mesto alebo PSČ';
    } catch (e) { s.textContent = 'geokódovanie zlyhalo, skús neskôr'; }
  };
  // článok: Ctrl+S = uložiť
  document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 's') { const b = document.querySelector('button[name=uloz]'); if (b) { e.preventDefault(); b.click(); } } });
})();
