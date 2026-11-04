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
**Platí: `git push` na `main` = naživo do ~5 min** (panelový CRON na WebSupporte spúšťa `~/najdijogu.sk/sub/deploy.php` = kópia `nastroje/deploy-server.php`, sťahuje zip z GitHubu). Recept `00-VELENIE/RECEPTY/2026/R-2026-09-19-websupport-cron-deploy-bez-gitu.md`. `nastroje/deploy.py` (FTPS) **nefunguje** — účet `claude-bot` priečinok `/najdijogu.sk/` nevidí; nepoužívať.

Pôvodný zámer (neplatí):
Hosting **WebSupport `bmelektro`**, doména pripojená štandardne, docroot `/najdijogu.sk/web`, PHP 8.5. Nasadenie z RUDY 2: `python nastroje\deploy.py` (FTPS cez `_spojenie.py` + `bm_creds`, len zmenené súbory). GitHub Pages vypnuté. Subdomény = priečinky `/najdijogu.sk/sub/<nazov>/` (studio., jogovne.).

## Pokyny Bohuša k cvičeniu (3.11.2026) — dodržať pri každom novom bloku
- **Jazyk hlasu** (len slovensky / len sanskrit / oboje) a **jazyk textov** na obrazovke zvlášť; v režime „len slovensky" ani jedno cudzie slovo (slovenské číslovky `COUNT_SK`, `nazovSk`).
- **Číslo** (vinyása count) sa dá vypnúť → hlas povie len pokyn na cvik.
- **Tempo dychu podľa bloku cvičenia** (`N.tempo[blok]`), meniteľné v nastavení aj počas cvičenia (−/+, klávesy + / −); H / T / C = jazyk hlasu / textov / číslo.
- **Skoky** idú cez medzipolohu (`cez:"skok_vpred"|"skok_vzad"`), ruky ostávajú na podložke; stoj je na prednom konci podložky (x≈127, viewBox 254×200).
- Každý vyslovený text musí mať nahraný klip (`python3 nastroje/hlas-generuj.py`), inak hovorí robotický hlas prehliadača.
- Zadania ďalších blokov: velenie `PROJEKTY/P-2026-11-najdijogu-zaverecna-sekvencia.md`, `PROJEKTY/P-2026-11-najdijogu-kratke-jogy.md`.

## Krátke cvičenia (fáza 1, 4.11.2026)
- Programy `CHRBAT`, `VECER`, `KANCELARIA`, `DYCH` na konci `SEKVENCIE` (`kratky:true`) — nie sú v zozname blokov aštangy, spúšťa ich dlaždica v sekcii `#kratke` na úvode (1 klik = len ten program s jeho tempom `tempo:{in,out}`).
- Krok `d:"volne"` = pohyb bez väzby na dych (`trv` = počet voľných dychov, 1 voľný dych = priemer nádychu a výdychu tempa bloku); výdrže `hold` sú v krátkych programoch vo voľnom dychu bez tónov.
- Opakovanie = skupina `{ opak, hovor, tip, kroky:[…] }` (napr. mačka–krava 8×); od 2. opakovania hlas povie len `kr`, po `hovor` opakovaniach už len tóny; `ticho:true` = krok sa nehovorí; `tip` sa len zobrazí.
- Pózy: voliteľný bod `s` (stred chrbta, oblúk pri mačke/krave), stolička cez `REKVIZITY` + `REKVIZITY_TVAR`. Náhľad `_film.html` rozvinie skupiny 2×.
- Texty, zostavy a bezpečnostné upozornenie: len podľa velenia `PROJEKTY/P-2026-11-najdijogu-kratke-jogy.md` §1a–§3 (žiadne zdravotné sľuby).
