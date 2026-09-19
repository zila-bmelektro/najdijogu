# najdijogu.sk / najdijogu.cz

Aštanga joga vedená dychom: dychový tréner + Surya Namaskara A/B s obrysovou postavou, metronómom a slovenským hlasom. Statická PWA (HTML/CSS/JS, bez buildu).

- `index.html` — všetky obrazovky (úvod, dych, nastavenie, cvičenie, o projekte)
- `sekvencie.js` — pózy (kľúčové body) + sekvencie s vinyasa countom
- `postava.js` — SVG kostra a prechody medzi pózami
- `app.js` — metronóm (Web Audio), hlas (Web Speech sk-SK), riadenie cvičenia
- `sw.js` — offline cache (pri nasadení zvýš `VERZIA`)

Nasadenie: GitHub Pages z vetvy `main` (koreň), domény cez `CNAME`.
Projekt: `G:\Spoločné disky\CLAUDE\joga-portal\` (analýza, návrh, rozhodnutia).

## Nasadenie (od 19.9.2026)
Hosting **WebSupport `bmelektro`**, doména pripojená štandardne, docroot `/najdijogu.sk/web`, PHP 8.5. Nasadenie z RUDY 2: `python nastroje\deploy.py` (FTPS cez `_spojenie.py` + `bm_creds`, len zmenené súbory). GitHub Pages vypnuté. Subdomény = priečinky `/najdijogu.sk/sub/<nazov>/` (studio., jogovne.).
