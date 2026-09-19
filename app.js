/* najdijogu.sk — dychový tréner + Surya Namaskara A/B v tempe vlastného dychu */
(() => {
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));

  /* ---------- nastavenia (uložené v prehliadači) ---------- */
  const N = Object.assign({ in: 5, out: 5, zamok: true, zvuk: true, hlas: true, hlasURI: "", sekv: "A", kola: 3, pes: 5, sanskrit: true, nazvy: true },
    (() => { try { return JSON.parse(localStorage.getItem("nj-nast") || "{}"); } catch (e) { return {}; } })());
  const uloz = () => { try { localStorage.setItem("nj-nast", JSON.stringify(N)); } catch (e) {} };
  const fmt = s => s.toFixed(1).replace(".", ",") + " s";

  /* ---------- obrazovky ---------- */
  const ukaz = id => {
    $$(".obrazovka").forEach(o => o.classList.toggle("aktivna", o.id === id));
    window.scrollTo(0, 0);
    if (id !== "cvicenie") cv.stop();
    if (id !== "dych") dych.stop();
    if (id === "nastavenie") obnovNastavenie();
    location.hash = id === "uvod" ? "" : id;
  };
  document.addEventListener("click", e => {
    const b = e.target.closest("[data-obr]"); if (!b) return;
    e.preventDefault(); ukaz(b.dataset.obr);
  });

  /* ---------- zvuk (Web Audio) ---------- */
  let ac = null;
  const audio = () => { if (!ac) ac = new (window.AudioContext || window.webkitAudioContext)(); if (ac.state === "suspended") ac.resume(); return ac; };
  const ton = (f1, f2, dur, hlasitost = .18) => {
    if (!N.zvuk) return;
    const c = audio(), o = c.createOscillator(), g = c.createGain(), t = c.currentTime;
    o.type = "sine"; o.frequency.setValueAtTime(f1, t); o.frequency.exponentialRampToValueAtTime(f2, t + dur);
    g.gain.setValueAtTime(0, t); g.gain.linearRampToValueAtTime(hlasitost, t + .03); g.gain.exponentialRampToValueAtTime(.001, t + dur);
    o.connect(g); g.connect(c.destination); o.start(t); o.stop(t + dur + .05);
  };
  const tonNadych = () => ton(330, 520, .45);
  const tonVydych = () => ton(520, 300, .6);
  const tik = () => ton(1200, 1200, .04, .06);

  /* ---------- hlas (Web Speech, sk-SK; ak nie je, ticho) ---------- */
  let hlasSK = null;
  /* poradie: uložený výber → "Natural/Online" sk hlas → iný sk → cs. Nie každý prístroj má slovenský hlas;
     zoznam sa ponúka v nastavení, aby si človek vybral ten, ktorý mu znie najprirodzenejšie. */
  const zoznamHlasov = () => ("speechSynthesis" in window) ? speechSynthesis.getVoices().filter(x => /^(sk|cs)/i.test(x.lang)) : [];
  const najdiHlas = () => {
    const v = zoznamHlasov(); if (!v.length) { hlasSK = null; return; }
    hlasSK = v.find(x => x.voiceURI === N.hlasURI) || v.find(x => /^sk/i.test(x.lang) && /natural|online|neural/i.test(x.name)) || v.find(x => /^sk/i.test(x.lang)) || v[0];
    const sel = $("#in-hlas-vyber");
    if (sel) {
      sel.innerHTML = v.map(x => `<option value="${x.voiceURI}">${x.name.replace(/Microsoft |Google /, "")} (${x.lang})</option>`).join("") || "<option value=''>žiadny slovenský hlas v tomto prístroji</option>";
      sel.value = hlasSK.voiceURI;
    }
  };
  if ("speechSynthesis" in window) { najdiHlas(); speechSynthesis.onvoiceschanged = najdiHlas; }
  $("#in-hlas-vyber").onchange = e => { N.hlasURI = e.target.value; uloz(); najdiHlas(); zastavHlas(); povedzPrehliadacom("Toto je náhradný hlas prehliadača. Názvy cvikov hovorí nahraný hlas."); };
  $("#btn-hlas-test").onclick = () => { audio(); povedz(["dve", "predklon", "uttánásana"], true); };
  $("#btn-hlas-test2").onclick = () => { audio(); zastavHlas(); povedzPrehliadacom("Toto je náhradný hlas prehliadača. Použije sa len pre vety, ktoré nie sú nahrané."); };
  /* ---------- nahraný hlas (predrenderované klipy z Google TTS, hlas/manifest.json) ---------- */
  let KLIPY = null, klipCache = {}, fronta = [], hraAudio = null;
  fetch("hlas/manifest.json").then(r => r.ok ? r.json() : null).then(m => { KLIPY = m && m.klipy ? m.klipy : null; if (KLIPY) $("#hlas-info").textContent = "nahraný hlas: " + (m.hlas || "").replace("sk-SK-Chirp3-HD-", ""); }).catch(() => {});
  const klip = text => {
    if (!KLIPY || !KLIPY[text]) return null;
    if (!klipCache[text]) { klipCache[text] = new Audio("hlas/" + KLIPY[text]); klipCache[text].preload = "auto"; }
    return klipCache[text];
  };
  const zastavHlas = () => { fronta = []; if (hraAudio) { hraAudio.pause(); hraAudio.currentTime = 0; hraAudio = null; } if ("speechSynthesis" in window) speechSynthesis.cancel(); };
  const hrajFrontu = () => {
    if (hraAudio || !fronta.length) return;
    const a = fronta.shift(); hraAudio = a;
    a.onended = a.onerror = () => { hraAudio = null; hrajFrontu(); };
    a.currentTime = 0; a.play().catch(() => { hraAudio = null; hrajFrontu(); });
  };
  const povedzPrehliadacom = text => {
    if (!("speechSynthesis" in window)) return;
    const u = new SpeechSynthesisUtterance(text);
    u.lang = "sk-SK"; if (hlasSK) u.voice = hlasSK; u.rate = 1; u.pitch = 1;
    speechSynthesis.speak(u);
  };
  /* povedz: text alebo pole textov; každý kus = klip, ak existuje, inak hlas prehliadača */
  const povedz = (text, dolezite) => {
    if (!N.hlas || !text) return;
    if (dolezite) zastavHlas();
    const kusy = Array.isArray(text) ? text : [text];
    const chybaju = [];
    kusy.forEach(t => { const k = klip(t); if (k) fronta.push(k); else chybaju.push(t); });
    hrajFrontu();
    if (chybaju.length) povedzPrehliadacom(chybaju.join(", "));
  };

  /* ---------- dychový tréner ---------- */
  const dych = (() => {
    const kruh = $("#dych-kruh"), faza = $("#dych-faza"), cas = $("#dych-cas"), btn = $("#dych-start");
    let bezi = false, t = null, tick = null;
    const setUI = () => {
      $("#in-nadych").value = N.in; $("#in-vydych").value = N.out;
      $("#out-in").textContent = fmt(N.in); $("#out-out").textContent = fmt(N.out);
      $("#in-zamok").checked = N.zamok; $("#in-zvuk").checked = N.zvuk; $("#in-hlas").checked = N.hlas;
    };
    setUI();
    $("#in-nadych").oninput = e => { N.in = +e.target.value; if (N.zamok) N.out = N.in; setUI(); uloz(); };
    $("#in-vydych").oninput = e => { N.out = +e.target.value; if (N.zamok) N.in = N.out; setUI(); uloz(); };
    $("#in-zamok").onchange = e => { N.zamok = e.target.checked; if (N.zamok) N.out = N.in; setUI(); uloz(); };
    $("#in-zvuk").onchange = e => { N.zvuk = e.target.checked; uloz(); };
    $("#in-hlas").onchange = e => { N.hlas = e.target.checked; uloz(); };

    let stav = "out";
    const fazaStart = () => {
      stav = stav === "out" ? "in" : "out";
      const d = stav === "in" ? N.in : N.out;
      kruh.style.transitionDuration = d + "s";
      kruh.classList.toggle("nadych", stav === "in");
      faza.textContent = stav === "in" ? "nádych" : "výdych";
      if (stav === "in") tonNadych(); else tonVydych();
      povedz(stav === "in" ? "nádych" : "výdych");
      let zost = d; cas.textContent = Math.ceil(zost);
      clearInterval(tick);
      tick = setInterval(() => { zost -= 1; cas.textContent = Math.max(0, Math.ceil(zost)); }, 1000);
      t = setTimeout(fazaStart, d * 1000);
    };
    const start = () => { audio(); bezi = true; btn.textContent = "Stop"; stav = "out"; fazaStart(); };
    const stop = () => { bezi = false; btn.textContent = "Dýchať"; clearTimeout(t); clearInterval(tick); kruh.classList.remove("nadych"); kruh.style.transitionDuration = "1s"; faza.textContent = "nádych"; cas.textContent = Math.ceil(N.in); };
    btn.onclick = () => bezi ? stop() : start();
    return { stop };
  })();

  /* ---------- nastavenie cvičenia ---------- */
  const obnovNastavenie = () => {
    $$("input[name=sekv]").forEach(r => r.checked = r.value === N.sekv);
    $("#in-kola").value = N.kola; $("#out-kola").textContent = N.kola;
    $("#in-pes").value = N.pes; $("#out-pes").textContent = N.pes;
    $("#tempo-in").textContent = fmt(N.in); $("#tempo-out").textContent = fmt(N.out);
    $("#in-sanskrit").checked = N.sanskrit; $("#in-nazvy").checked = N.nazvy;
    const s = zostavProgram();
    const sek = s.reduce((a, k) => a + k.trvanie, 0);
    $("#odhad").textContent = `${s.length} pohybov · odhad ${Math.round(sek / 60)} min pri tomto tempe`;
  };
  $$("input[name=sekv]").forEach(r => r.onchange = e => { N.sekv = e.target.value; uloz(); obnovNastavenie(); });
  $("#in-kola").oninput = e => { N.kola = +e.target.value; uloz(); obnovNastavenie(); };
  $("#in-pes").oninput = e => { N.pes = +e.target.value; uloz(); obnovNastavenie(); };
  $("#in-sanskrit").onchange = e => { N.sanskrit = e.target.checked; uloz(); };
  $("#in-nazvy").onchange = e => { N.nazvy = e.target.checked; uloz(); };

  /* Program = plochý zoznam pohybov (krok × kolá), každý s trvaním v sekundách */
  const zostavProgram = () => {
    const casti = N.sekv === "AB" ? ["A", "B"] : [N.sekv];
    const prog = [];
    casti.forEach(pismeno => {
      const S = SEKVENCIE[pismeno];
      for (let kolo = 1; kolo <= N.kola; kolo++) {
        S.kroky.forEach(k => {
          const d = k.d === "in" ? N.in : N.out;
          const hold = k.hold === "pes" ? N.pes : 0;
          prog.push({ ...k, sekv: pismeno, kolo, kolaSpolu: N.kola, trvanie: d + hold * (N.in + N.out), holdDychov: hold });
        });
      }
    });
    return prog;
  };

  /* ---------- cvičenie ---------- */
  const cv = (() => {
    const svg = $("#cv-svg"), post = new Postava(svg, "#f3efe4");
    const elCount = $("#cv-count"), elNazov = $("#cv-nazov"), elSk = $("#cv-nazov-sk"), elDych = $("#cv-dych"), elDris = $("#cv-dris"),
          elKolo = $("#cv-kolo"), elDalej = $("#cv-dalej"), elOstava = $("#cv-ostava"), pruh = $("#cv-progres-pruh"), dPruh = $("#cv-dych-pruh-vnutro"), btnPauza = $("#cv-pauza");
    let prog = [], i = 0, rezim = "tv", bezi = false, pauza = false, timer = null, holdTimer = null, wl = null;

    const zobrazKrok = (k, ms) => {
      const ct = k.c ? COUNT[k.c] : null;
      elCount.textContent = ct ? (N.sanskrit ? ct[0] : String(k.c)) : "";
      elNazov.textContent = k.san; elSk.textContent = k.sk;
      elDych.textContent = k.d === "in" ? "NÁDYCH" : "VÝDYCH";
      elDych.className = "cv-dych " + (k.d === "in" ? "nadych" : "vydych");
      elDris.textContent = "drishti: " + k.dr;
      const vKole = prog.filter(x => x.sekv === k.sekv && x.kolo === k.kolo);
      const poradie = vKole.indexOf(k) + 1;
      elKolo.textContent = `${SEKVENCIE[k.sekv].nazov} · kolo ${k.kolo}/${k.kolaSpolu} · pohyb ${poradie}/${vKole.length}`;
      const dalsi = prog[i + 1];
      elDalej.textContent = dalsi ? `ďalej: ${dalsi.san} — ${dalsi.sk}` + (dalsi.kolo !== k.kolo || dalsi.sekv !== k.sekv ? ` (kolo ${dalsi.kolo})` : "") : "ďalej: koniec";
      const ostava = prog.length - i - 1;
      elOstava.textContent = `ostáva ${ostava} ${ostava === 1 ? "pohyb" : ostava < 5 ? "pohyby" : "pohybov"}`;
      pruh.style.width = (100 * i / prog.length) + "%";
      /* predcvičovanie: každý cvik sa ukáže od základného postoja; cvičenie: nadväzuje na predchádzajúci */
      if (rezim === "predcvic") post.skoc("samasthiti");
      post.prejdi(k.poza, ms * 0.85, k.zrkadlo);
    };
    const dychPruh = (d, ms) => {
      dPruh.style.transition = "none"; dPruh.style.width = d === "in" ? "0%" : "100%";
      dPruh.className = d === "in" ? "nadych" : "vydych";
      requestAnimationFrame(() => requestAnimationFrame(() => { dPruh.style.transition = `width ${ms}ms linear`; dPruh.style.width = d === "in" ? "100%" : "0%"; }));
    };
    const hovorKrok = k => {
      const ct = k.c ? COUNT[k.c] : null;
      const casti = [];
      if (ct && N.sanskrit) casti.push(ct[1]); else if (k.c) casti.push(String(k.c));
      if (N.nazvy) { casti.push(k.sk); casti.push(k.vysl || k.san); } /* najskôr slovensky, potom sanskrit (pokyn 19.9.) */
      povedz(casti, true);
    };

    const spusti = () => {
      if (i >= prog.length) return koniec(true);
      const k = prog[i];
      const ms = (k.d === "in" ? N.in : N.out) * 1000;
      zobrazKrok(k, ms); dychPruh(k.d, ms);
      if (k.d === "in") tonNadych(); else tonVydych();
      hovorKrok(k);
      clearTimeout(timer); clearTimeout(holdTimer);
      const poPohybe = () => {
        if (!k.holdDychov) return dalej();
        /* výdrž: n dychov, každý nádych/výdych tón + pruh */
        let zost = k.holdDychov, faza = "in";
        povedz(`${k.holdDychov} ${k.holdDychov === 1 ? "dych" : k.holdDychov < 5 ? "dychy" : "dychov"}`);
        const jeden = () => {
          if (faza === "in" && zost === 0) return dalej();
          const d = faza === "in" ? N.in : N.out;
          elDych.textContent = (faza === "in" ? "NÁDYCH" : "VÝDYCH") + `  ${zost}`;
          elDych.className = "cv-dych " + (faza === "in" ? "nadych" : "vydych");
          dychPruh(faza, d * 1000);
          if (faza === "in") tonNadych(); else tonVydych();
          holdTimer = setTimeout(() => { if (faza === "out") zost--; faza = faza === "in" ? "out" : "in"; jeden(); }, d * 1000);
        };
        jeden();
      };
      if (rezim === "tv") timer = setTimeout(poPohybe, ms);
      else timer = setTimeout(() => { if (k.holdDychov) poPohybe(); /* predcvičovanie: čaká na Ďalej */ }, ms);
    };
    const dalej = () => { if (rezim === "tv" || prog[i].holdDychov) { i++; spusti(); } };
    const koniec = dokoncene => {
      stop();
      if (dokoncene) { povedz("Hotovo. Samasthiti.", true); setTimeout(() => ukaz("nastavenie"), 2500); }
      else ukaz("nastavenie");
    };
    const start = r => {
      rezim = r; prog = zostavProgram(); i = 0; bezi = true; pauza = false;
      btnPauza.textContent = "Pauza";
      $(".cv-klav").textContent = r === "tv" ? "medzerník = pauza · šípky = cvik · R = znova · Esc = koniec" : "Ďalej = ďalší cvik · R = zopakovať · šípky · Esc = koniec";
      $("#cv-next").style.visibility = "visible";
      audio(); post.skoc("samasthiti");
      if ("wakeLock" in navigator) navigator.wakeLock.request("screen").then(l => { wl = l; }).catch(() => {});
      ukaz("cvicenie");
      povedz([SEKVENCIE[prog[0].sekv].nazov, "Samasthiti. Pripravený?"], true);
      timer = setTimeout(spusti, 2500);
    };
    const stop = () => { bezi = false; clearTimeout(timer); clearTimeout(holdTimer); zastavHlas(); };
    const prepniPauzu = () => {
      if (!bezi) return;
      pauza = !pauza; btnPauza.textContent = pauza ? "Pokračovať" : "Pauza";
      if (pauza) { clearTimeout(timer); clearTimeout(holdTimer); povedz("pauza"); } else spusti();
    };
    const skok = smer => { if (!bezi) return; i = Math.max(0, Math.min(prog.length - 1, i + smer)); pauza = false; btnPauza.textContent = "Pauza"; spusti(); };
    const znova = () => { if (!bezi) return; pauza = false; btnPauza.textContent = "Pauza"; spusti(); };

    $("#btn-tv").onclick = () => start("tv");
    $("#btn-predcvic").onclick = () => start("predcvic");
    $("#cv-koniec").onclick = () => koniec(false);
    $("#cv-pauza").onclick = prepniPauzu;
    $("#cv-next").onclick = () => rezim === "tv" ? skok(1) : (i++, spusti());
    $("#cv-prev").onclick = () => skok(-1);
    $("#cv-znova").onclick = znova;
    document.addEventListener("keydown", e => {
      if (!bezi) return;
      if (e.key === " " || e.key === "Enter" || e.key === "MediaPlayPause") { e.preventDefault(); rezim === "tv" ? prepniPauzu() : (i++, spusti()); }
      else if (e.key === "ArrowRight") skok(1);
      else if (e.key === "ArrowLeft") skok(-1);
      else if (e.key.toLowerCase() === "r") znova();
      else if (e.key === "Escape" || e.key === "Backspace") koniec(false);
    });
    /* obrazovka nech nezhasne počas cvičenia (wake lock sa po skrytí karty obnoví) */
    document.addEventListener("visibilitychange", () => { if (bezi && !document.hidden && "wakeLock" in navigator) navigator.wakeLock.request("screen").then(l => { wl = l; }).catch(() => {}); });
    return { start, stop };
  })();

  /* ---------- úvodná postava: pomaly prechádza pózami Surya A ---------- */
  (() => {
    const hero = new Postava($("#hero-svg"), "#1f6f5a");
    let i = 0; const kroky = SEKVENCIE.A.kroky;
    setInterval(() => { hero.prejdi(kroky[i].poza, 1600, kroky[i].zrkadlo); i = (i + 1) % kroky.length; }, 2600);
  })();

  /* ---------- štart podľa #hash ---------- */
  const h = location.hash.replace("#", "");
  if (["dych", "nastavenie", "oprogramoch"].includes(h)) ukaz(h);
  if ("serviceWorker" in navigator) navigator.serviceWorker.register("sw.js").catch(() => {});
})();
