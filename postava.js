/* Obrysová postava: SVG kostra z kľúčových bodov + plynulý prechod medzi pózami. */

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
    this.aktualna = this.klon(POZY.samasthiti);
    this.zrkadlo = false;
    this.anim = null;
    this.kresli(this.aktualna, false);
  }
  klon(p) { const o = {}; for (const k in p) o[k] = [p[k][0], p[k][1]]; return o; }
  bod(pt, zrk) { return zrk ? [200 - pt[0], pt[1]] : pt; }
  kresli(p, zrk) {
    const b = k => this.bod(p[k], zrk);
    const pl = (id, ks) => this.el[id].setAttribute("points", ks.map(k => b(k).join(",")).join(" "));
    pl("nohaL", ["p","kL","fL"]); pl("nohaR", ["p","kR","fR"]);
    pl("rukaL", ["n","eL","wL"]); pl("rukaR", ["n","eR","wR"]);
    const n = b("n"), h = b("h"), hip = b("p");
    this.el.chrbat.setAttribute("x1", n[0]); this.el.chrbat.setAttribute("y1", n[1]);
    this.el.chrbat.setAttribute("x2", hip[0]); this.el.chrbat.setAttribute("y2", hip[1]);
    this.el.hlava.setAttribute("cx", h[0]); this.el.hlava.setAttribute("cy", h[1]);
  }
  /* prejdi do pózy za `ms` milisekúnd (dĺžka dychovej fázy) */
  prejdi(nazov, ms, zrk) {
    const ciel = POZY[nazov]; if (!ciel) return;
    if (this.anim) cancelAnimationFrame(this.anim);
    const od = this.klon(this.aktualna), odZrk = this.zrkadlo, doZrk = !!zrk;
    const t0 = performance.now();
    const ease = t => t < .5 ? 2*t*t : -1 + (4 - 2*t)*t;
    const krok = now => {
      const u = Math.min(1, (now - t0) / Math.max(1, ms));
      const e = ease(u);
      const p = {};
      for (const k in ciel) {
        const a = this.bod(od[k], odZrk), c = this.bod(ciel[k], doZrk);
        p[k] = [a[0] + (c[0]-a[0])*e, a[1] + (c[1]-a[1])*e];
      }
      this.kresli(p, false);
      if (u < 1) this.anim = requestAnimationFrame(krok);
      else { this.aktualna = this.klon(ciel); this.zrkadlo = doZrk; this.anim = null; }
    };
    this.anim = requestAnimationFrame(krok);
  }
  skoc(nazov, zrk) { this.aktualna = this.klon(POZY[nazov]); this.zrkadlo = !!zrk; this.kresli(this.aktualna, this.zrkadlo); }
}
