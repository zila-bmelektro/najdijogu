/* Obrysová postava: SVG kostra z kľúčových bodov + plynulý prechod medzi pózami.
   `aktualna` drží VŽDY to, čo je práve nakreslené (aj uprostred prechodu) — ďalší pohyb
   preto nadväzuje na skutočnú polohu, nie na naposledy dokončenú pózu. */

class Postava {
  constructor(svg, farba) {
    this.svg = svg;
    this.farba = farba || "currentColor";
    this.svg.innerHTML = `
      <line class="zem" x1="4" y1="181" x2="250" y2="181"/>
      <g class="rekvizita" fill="none" stroke="${this.farba}" stroke-width="3" stroke-linecap="round" opacity=".35"></g>
      <g class="telo" fill="none" stroke="${this.farba}" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
        <polyline id="nohaL" opacity=".55" stroke-width="4"/><polyline id="rukaL" opacity=".55" stroke-width="4"/>
        <polyline id="chrbat"/>
        <polyline id="nohaR"/><polyline id="rukaR"/>
        <circle id="hlava" r="9" fill="${this.farba}" stroke="none"/>
      </g>`;
    this.el = {};
    ["nohaL","rukaL","chrbat","nohaR","rukaR","hlava"].forEach(id => this.el[id] = this.svg.querySelector("#"+id));
    this.rekv = this.svg.querySelector(".rekvizita"); this.rekvMeno = "";
    this.aktualna = this.abs(POZY.samasthiti, false);   // absolútne súradnice (zrkadlenie už zapracované)
    this.anim = null;
    this.kresli(this.aktualna);
  }
  klon(p) { const o = {}; for (const k in p) o[k] = [p[k][0], p[k][1]]; return o; }
  /* zrk: true = zrkadlo okolo stredu podložky (pohľad spredu, druhá strana); "nohy" = len výmena ľavej/pravej
     (pohľad z boku: vpredu je druhá noha, postava ostáva na tom istom mieste podložky) */
  /* s = stred chrbta (mačka, krava: chrbát do oblúka); póza bez neho má chrbát rovný (stred medzi krkom a panvou) */
  abs(p, zrk) {
    const o = {};
    if (zrk === "nohy") { for (const k in p) o[k] = [p[k][0], p[k][1]]; for (const [a, b] of [["kL","kR"],["fL","fR"],["eL","eR"],["wL","wR"]]) [o[a], o[b]] = [o[b], o[a]]; }
    else for (const k in p) o[k] = zrk ? [254 - p[k][0], p[k][1]] : [p[k][0], p[k][1]];
    if (!o.s) o.s = [(o.n[0] + o.p[0]) / 2, (o.n[1] + o.p[1]) / 2];
    return o;
  }
  /* rekvizita pod postavou (stolička) podľa REKVIZITY[póza] v sekvencie.js */
  rekvizita(nazov, zrk) {
    const r = (typeof REKVIZITY !== "undefined" && REKVIZITY[nazov]) || "", kl = r + (zrk === true ? "|z" : "");
    if (!this.rekv || kl === this.rekvMeno) return;
    this.rekvMeno = kl;
    const tvar = (typeof REKVIZITY_TVAR !== "undefined" && REKVIZITY_TVAR[r]) || "";
    this.rekv.innerHTML = tvar ? `<path d="${tvar}"${zrk === true ? ' transform="translate(254 0) scale(-1 1)"' : ""}/>` : "";
  }
  kresli(p) {
    const pl = (id, ks) => this.el[id].setAttribute("points", ks.map(k => p[k].join(",")).join(" "));
    pl("nohaL", ["p","kL","fL"]); pl("nohaR", ["p","kR","fR"]);
    pl("rukaL", ["n","eL","wL"]); pl("rukaR", ["n","eR","wR"]);
    pl("chrbat", ["n","s","p"]);
    this.el.hlava.setAttribute("cx", p.h[0]); this.el.hlava.setAttribute("cy", p.h[1]);
  }
  /* prejdi z TERAJŠEJ polohy do pózy za `ms` milisekúnd; `cez` = medzipoloha (napr. skok: ruky na mieste, panva hore) */
  prejdi(nazov, ms, zrk, hotovo, cez) {
    if (!POZY[nazov]) return;
    const ciel = this.abs(POZY[nazov], zrk || false);
    this.rekvizita(nazov, zrk);
    if (this.anim) cancelAnimationFrame(this.anim);
    const body = [this.klon(this.aktualna)], podiel = [];
    if (cez && POZY[cez]) { body.push(this.abs(POZY[cez], zrk || false)); podiel.push(.45); podiel.push(.55); } else podiel.push(1);
    body.push(ciel);
    const t0 = performance.now();
    const ease = t => t < .5 ? 2*t*t : -1 + (4 - 2*t)*t;
    const krok = now => {
      const u = Math.min(1, (now - t0) / Math.max(1, ms));
      let s = 0, i = 0; while (i < podiel.length - 1 && u > s + podiel[i]) { s += podiel[i]; i++; }
      const e = ease(Math.min(1, (u - s) / podiel[i])), od = body[i], k2 = body[i + 1];
      const p = {};
      for (const k in ciel) p[k] = [od[k][0] + (k2[k][0]-od[k][0])*e, od[k][1] + (k2[k][1]-od[k][1])*e];
      this.aktualna = p;
      this.kresli(p);
      if (u < 1) this.anim = requestAnimationFrame(krok);
      else { this.anim = null; if (hotovo) hotovo(); }
    };
    this.anim = requestAnimationFrame(krok);
  }
  skoc(nazov, zrk) { if (this.anim) cancelAnimationFrame(this.anim); this.anim = null; this.rekvizita(nazov, zrk); this.aktualna = this.abs(POZY[nazov], zrk || false); this.kresli(this.aktualna); }
}
