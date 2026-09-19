/* Obrysová postava: SVG kostra z kľúčových bodov + plynulý prechod medzi pózami.
   `aktualna` drží VŽDY to, čo je práve nakreslené (aj uprostred prechodu) — ďalší pohyb
   preto nadväzuje na skutočnú polohu, nie na naposledy dokončenú pózu. */

class Postava {
  constructor(svg, farba) {
    this.svg = svg;
    this.farba = farba || "currentColor";
    this.svg.innerHTML = `
      <line class="zem" x1="10" y1="181" x2="190" y2="181"/>
      <g class="telo" fill="none" stroke="${this.farba}" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
        <polyline id="nohaL" opacity=".55" stroke-width="4"/><polyline id="rukaL" opacity=".55" stroke-width="4"/>
        <line id="chrbat"/>
        <polyline id="nohaR"/><polyline id="rukaR"/>
        <circle id="hlava" r="9" fill="${this.farba}" stroke="none"/>
      </g>`;
    this.el = {};
    ["nohaL","rukaL","chrbat","nohaR","rukaR","hlava"].forEach(id => this.el[id] = this.svg.querySelector("#"+id));
    this.aktualna = this.abs(POZY.samasthiti, false);   // absolútne súradnice (zrkadlenie už zapracované)
    this.anim = null;
    this.kresli(this.aktualna);
  }
  klon(p) { const o = {}; for (const k in p) o[k] = [p[k][0], p[k][1]]; return o; }
  abs(p, zrk) { const o = {}; for (const k in p) o[k] = zrk ? [200 - p[k][0], p[k][1]] : [p[k][0], p[k][1]]; return o; }
  kresli(p) {
    const pl = (id, ks) => this.el[id].setAttribute("points", ks.map(k => p[k].join(",")).join(" "));
    pl("nohaL", ["p","kL","fL"]); pl("nohaR", ["p","kR","fR"]);
    pl("rukaL", ["n","eL","wL"]); pl("rukaR", ["n","eR","wR"]);
    this.el.chrbat.setAttribute("x1", p.n[0]); this.el.chrbat.setAttribute("y1", p.n[1]);
    this.el.chrbat.setAttribute("x2", p.p[0]); this.el.chrbat.setAttribute("y2", p.p[1]);
    this.el.hlava.setAttribute("cx", p.h[0]); this.el.hlava.setAttribute("cy", p.h[1]);
  }
  /* prejdi z TERAJŠEJ polohy do pózy za `ms` milisekúnd */
  prejdi(nazov, ms, zrk, hotovo) {
    const ciel = this.abs(POZY[nazov], !!zrk); if (!ciel) return;
    if (this.anim) cancelAnimationFrame(this.anim);
    const od = this.klon(this.aktualna);
    const t0 = performance.now();
    const ease = t => t < .5 ? 2*t*t : -1 + (4 - 2*t)*t;
    const krok = now => {
      const u = Math.min(1, (now - t0) / Math.max(1, ms));
      const e = ease(u);
      const p = {};
      for (const k in ciel) p[k] = [od[k][0] + (ciel[k][0]-od[k][0])*e, od[k][1] + (ciel[k][1]-od[k][1])*e];
      this.aktualna = p;
      this.kresli(p);
      if (u < 1) this.anim = requestAnimationFrame(krok);
      else { this.anim = null; if (hotovo) hotovo(); }
    };
    this.anim = requestAnimationFrame(krok);
  }
  skoc(nazov, zrk) { if (this.anim) cancelAnimationFrame(this.anim); this.anim = null; this.aktualna = this.abs(POZY[nazov], !!zrk); this.kresli(this.aktualna); }
}
