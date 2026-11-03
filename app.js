/* najdijogu.sk — dychový tréner + Surya Namaskara A/B v tempe vlastného dychu */
(() => {
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));

  /* ---------- nastavenia (uložené v prehliadači) ---------- */
  const ULOZENE = (() => { try { return JSON.parse(localStorage.getItem("nj-nast") || "{}") || {}; } catch (e) { return {}; } })();
  const N = Object.assign({ in: 5, out: 5, zamok: true, zvuk: true, hlas: true, hlasURI: "", bloky: ["A"], kola: 3, pes: 5, drz: 5,
    jazyk: "oboje", jazykText: "oboje", cislo: true, nazvy: true, tempo: {} }, ULOZENE);
  const JAZYKY = ["sk", "san", "oboje"];
  if (!JAZYKY.includes(N.jazyk)) N.jazyk = "oboje";
  if (!JAZYKY.includes(N.jazykText)) N.jazykText = "oboje";
  /* bloky cvičenia (pokyn 3.11.2026): namiesto jednej voľby A / B / A+B sa zaškrtnú bloky; staré „sekv" sa prevedie */
  const BLOKY = Object.keys(SEKVENCIE);
  if (!ULOZENE.bloky && ULOZENE.sekv) N.bloky = ULOZENE.sekv === "AB" ? ["A", "B"] : [ULOZENE.sekv];
  if (!Array.isArray(N.bloky)) N.bloky = ["A"];
  N.bloky = N.bloky.filter(b => BLOKY.includes(b)); if (!N.bloky.length) N.bloky = ["A"];
  if (!N.tempo || typeof N.tempo !== "object") N.tempo = {};
  delete N.sekv;
  /* tempo dychu podľa bloku cvičenia: vlastné, inak hlavné tempo z Dýchania */
  const tempoB = b => N.tempo[b] || { in: N.in, out: N.out };
  const dlzka = k => { const T = tempoB(k.sekv); return k.d === "in" ? T.in : T.out; };
  const dychovVydrze = k => k.hold === "pes" ? N.pes : k.hold === "drz" ? N.drz : (+k.hold || 0);
  const nazovBloku = (S, jazyk) => (jazyk === "sk" ? S.nazovSk : S.nazov) || S.nazov;
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
  const tonNadych = () => ton(330, 520, .45, .34);   /* hlasnejšie (pokyn 19.9.) */
  const tonVydych = () => ton(520, 300, .6, .34);
  /* „blomp" – krátky mäkký úder pri začiatku ďalšieho cviku */
  const blomp = () => { ton(440, 220, .28, .4); setTimeout(() => ton(330, 165, .22, .25), 90); };
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
  $("#btn-hlas-test").onclick = () => { audio(); povedz(slovaKroku({ ...SEKVENCIE.A.kroky[1], sekv: "A" }), true); };
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
  /* jazyk hlasu (pokyn 3.11.2026): "sk" = len slovensky (slovenské číslovky + slovenský názov, žiadne cudzie slovo),
     "san" = len sanskrit (sanskritské počítanie + sanskritský názov), "oboje" = počet sanskritom + názov slovensky aj sanskritom */
  const slovaKroku = k => {
    const casti = [], sk = N.jazyk === "sk", san = N.jazyk === "san";
    if (k.c && N.cislo) casti.push(sk ? COUNT_SK[k.c] : COUNT[k.c][1]);
    if (N.nazvy) { if (!san) casti.push(k.sk); if (!sk) casti.push(k.vysl || k.san); }
    return casti;
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
  const fmtT = T => T.in === T.out ? fmt(T.in) : `${T.in.toFixed(1).replace(".", ",")}/${fmt(T.out)}`;
  const zmenTempo = (b, d) => {
    const T = tempoB(b), n = x => Math.min(12, Math.max(1.5, Math.round((x + d) * 2) / 2));
    N.tempo[b] = { in: n(T.in), out: n(T.out) }; uloz();
  };
  const vykresliBloky = () => {
    const el = $("#bloky"); if (!el) return;
    el.innerHTML = BLOKY.map(b => {
      const S = SEKVENCIE[b], vlastne = !!N.tempo[b];
      return `<div class="blok"><label class="prepinac"><input type="checkbox" data-blok="${b}"${N.bloky.includes(b) ? " checked" : ""}>
        <span>${nazovBloku(S, N.jazykText)} <span class="drobne">(${S.popis})</span></span></label>
        <div class="blok-tempo"><span class="drobne">dych</span><button class="tiche" data-tempo="${b}" data-d="-0.5" title="rýchlejšie dýchanie (kratší nádych aj výdych o 0,5 s)">−</button><b>${fmtT(tempoB(b))}</b><button class="tiche" data-tempo="${b}" data-d="0.5" title="pomalšie dýchanie (dlhší nádych aj výdych o 0,5 s)">+</button>${vlastne ? `<button class="tiche" data-tempo-reset="${b}" title="späť na hlavné tempo z Dýchania (${fmtT({ in: N.in, out: N.out })})">↺</button>` : `<span class="drobne">hlavné</span>`}</div></div>`;
    }).join("");
  };
  document.addEventListener("click", e => {
    const t = e.target.closest("[data-tempo],[data-tempo-reset]"); if (!t || !t.closest("#bloky")) return;
    if (t.dataset.tempoReset) { delete N.tempo[t.dataset.tempoReset]; uloz(); } else zmenTempo(t.dataset.tempo, +t.dataset.d);
    obnovNastavenie();
  });
  document.addEventListener("change", e => {
    const c = e.target.closest("#bloky input[data-blok]"); if (!c) return;
    const zvolene = $$("#bloky input[data-blok]").filter(x => x.checked).map(x => x.dataset.blok);
    if (!zvolene.length) { c.checked = true; return; }   /* aspoň jeden blok */
    N.bloky = zvolene; uloz(); obnovNastavenie();
  });
  const obnovNastavenie = () => {
    vykresliBloky();
    $("#in-kola").value = N.kola; $("#out-kola").textContent = N.kola;
    $("#in-pes").value = N.pes; $("#out-pes").textContent = N.pes;
    $("#in-drz").value = N.drz; $("#out-drz").textContent = N.drz;
    $("#tempo-in").textContent = fmt(N.in); $("#tempo-out").textContent = fmt(N.out);
    $$("input[name=jazyk]").forEach(r => r.checked = r.value === N.jazyk);
    $$("input[name=jazykText]").forEach(r => r.checked = r.value === N.jazykText);
    $("#in-nazvy").checked = N.nazvy; $("#in-cislo").checked = N.cislo;
    const s = zostavProgram();
    const sek = s.reduce((a, k) => a + k.trvanie, 0);
    $("#odhad").textContent = `${s.length} pohybov · odhad ${Math.round(sek / 60)} min pri zvolenom tempe`;
  };
  $("#in-kola").oninput = e => { N.kola = +e.target.value; uloz(); obnovNastavenie(); };
  $("#in-pes").oninput = e => { N.pes = +e.target.value; uloz(); obnovNastavenie(); };
  $("#in-drz").oninput = e => { N.drz = +e.target.value; uloz(); obnovNastavenie(); };
  /* po zmene jazyka hlasu hneď ukážka, nech počuť rozdiel */
  $$("input[name=jazyk]").forEach(r => r.onchange = e => { N.jazyk = e.target.value; uloz(); audio(); povedz(slovaKroku({ ...SEKVENCIE.A.kroky[1], sekv: "A" }), true); });
  $$("input[name=jazykText]").forEach(r => r.onchange = e => { N.jazykText = e.target.value; uloz(); obnovNastavenie(); });
  $("#in-nazvy").onchange = e => { N.nazvy = e.target.checked; uloz(); };
  $("#in-cislo").onchange = e => { N.cislo = e.target.checked; uloz(); };

  /* Program = plochý zoznam pohybov (bloky × kolá), každý s trvaním v sekundách pri tempe svojho bloku */
  const zostavProgram = () => {
    const prog = [];
    BLOKY.filter(b => N.bloky.includes(b)).forEach(b => {
      const S = SEKVENCIE[b], kol = S.kola ? N.kola : 1;
      for (let kolo = 1; kolo <= kol; kolo++) {
        S.kroky.forEach(k => {
          const x = { ...k, sekv: b, kolo, kolaSpolu: kol };
          x.holdDychov = dychovVydrze(x);
          const T = tempoB(b);
          x.trvanie = dlzka(x) + x.holdDychov * (T.in + T.out);
          prog.push(x);
        });
      }
    });
    return prog;
  };

  /* ---------- nezhasnúť obrazovku (TV šetrič) ----------
     1. Wake Lock API (Chrome/Android TV); po návrate z pozadia sa musí požiadať znova.
     2. Záloha: tiché slučkové video (16×16 px, 1,8 kB) – trik NoSleep, funguje aj tam, kde Wake Lock nie je. */
  let wl = null, nespiVideo = null;
  const drzObrazovku = () => {
    if ("wakeLock" in navigator) navigator.wakeLock.request("screen").then(l => { wl = l; }).catch(() => {});
    if (!nespiVideo) {
      nespiVideo = document.createElement("video");
      Object.assign(nespiVideo, { src: "nespi.mp4", loop: true, muted: true, playsInline: true });
      nespiVideo.setAttribute("playsinline", ""); nespiVideo.setAttribute("muted", "");
      nespiVideo.style.cssText = "position:fixed;width:1px;height:1px;opacity:0.01;pointer-events:none;bottom:0;right:0";
      document.body.appendChild(nespiVideo);
    }
    nespiVideo.play().catch(() => {});
  };
  const pustiObrazovku = () => { if (wl) { wl.release().catch(() => {}); wl = null; } if (nespiVideo) nespiVideo.pause(); };
  document.addEventListener("visibilitychange", () => { if (document.visibilityState === "visible" && (wl || (nespiVideo && !nespiVideo.paused))) drzObrazovku(); });

  /* ---------- cvičenie ---------- */
  const cv = (() => {
    const svg = $("#cv-svg"), post = new Postava(svg, "#f3efe4");
    const elCount = $("#cv-count"), elNazov = $("#cv-nazov"), elSk = $("#cv-nazov-sk"), elDych = $("#cv-dych"), elDris = $("#cv-dris"),
          elKolo = $("#cv-kolo"), elDalej = $("#cv-dalej"), elOstava = $("#cv-ostava"), pruh = $("#cv-progres-pruh"), dPruh = $("#cv-dych-pruh-vnutro"), btnPauza = $("#cv-pauza");
    let prog = [], i = 0, rezim = "tv", bezi = false, pauza = false, timer = null, holdTimer = null, predpovedTimer = null;

    /* texty na obrazovke podľa jazyka textov (pokyn 3.11.2026): sk = len slovensky, san = len sanskrit, oboje = sanskrit + slovensky */
    const nazovKroku = k => N.jazykText === "sk" ? k.sk : N.jazykText === "san" ? k.san : `${k.san} — ${k.sk}`;
    const texty = k => {
      const ct = k.c ? COUNT[k.c] : null, t = N.jazykText;
      elCount.textContent = ct ? (t === "sk" ? String(k.c) : ct[0]) : "";
      elNazov.textContent = t === "sk" ? k.sk : k.san;
      elSk.textContent = t === "oboje" ? k.sk : ""; elSk.hidden = t !== "oboje";
      elDris.textContent = k.dr ? (t === "sk" ? "pohľad: " : "drishti: ") + k.dr : "";
      const S = SEKVENCIE[k.sekv], vKole = prog.filter(x => x.sekv === k.sekv && x.kolo === k.kolo);
      elKolo.textContent = `${nazovBloku(S, t)}` + (k.kolaSpolu > 1 ? ` · kolo ${k.kolo}/${k.kolaSpolu}` : "") + ` · pohyb ${vKole.indexOf(k) + 1}/${vKole.length}`;
      const dalsi = prog[i + 1];
      elDalej.textContent = dalsi ? "ďalej: " + nazovKroku(dalsi) + (dalsi.c ? " · " + (t === "sk" ? dalsi.c : COUNT[dalsi.c][0]) : "") + (dalsi.sekv !== k.sekv ? ` (${nazovBloku(SEKVENCIE[dalsi.sekv], t)})` : dalsi.kolo !== k.kolo ? ` (kolo ${dalsi.kolo})` : "") : "ďalej: koniec";
      ovladanie();
    };
    const zobrazKrok = (k, ms) => {
      texty(k);
      elDych.textContent = k.d === "in" ? "NÁDYCH" : "VÝDYCH";
      elDych.className = "cv-dych " + (k.d === "in" ? "nadych" : "vydych");
      const ostava = prog.length - i - 1;
      elOstava.textContent = `ostáva ${ostava} ${ostava === 1 ? "pohyb" : ostava < 5 && ostava > 0 ? "pohyby" : "pohybov"}`;
      pruh.style.width = (100 * i / prog.length) + "%";
      /* predcvičovanie: každý cvik sa ukáže od základného postoja bloku; cvičenie: nadväzuje na predchádzajúci */
      if (rezim === "predcvic") post.skoc(SEKVENCIE[k.sekv].zaciatok || "samasthiti");
      post.prejdi(k.poza, ms * 0.85, k.zrkadlo, null, rezim === "predcvic" ? null : k.cez);
    };
    /* ovládanie počas cvičenia: jazyk hlasu, jazyk textov, číslo, tempo dychu aktuálneho bloku */
    const ovladanie = () => {
      $$("#cv-volby [data-v]").forEach(b => b.classList.toggle("zap", b.dataset.v === "cislo" ? N.cislo : N[b.dataset.v] === b.dataset.h));
      const k = prog[i]; if (k) { $("#cv-tempo").textContent = fmtT(tempoB(k.sekv)); $("#cv-tempo-blok").textContent = nazovBloku(SEKVENCIE[k.sekv], N.jazykText); }
    };
    const nastav = (v, h) => {
      if (v === "cislo") N.cislo = !N.cislo; else N[v] = h;
      uloz(); if (prog[i]) texty(prog[i]); else ovladanie();
      if (v === "jazyk" || v === "cislo") { const k = prog[i]; if (k) povedz(slovaKroku(k), true); }
    };
    $("#cv-volby").addEventListener("click", e => {
      const b = e.target.closest("button"); if (!b) return; b.blur();
      if (b.dataset.v) nastav(b.dataset.v, b.dataset.h);
      else if (b.dataset.tempoCv) { const k = prog[i]; if (k) { zmenTempo(k.sekv, +b.dataset.tempoCv); ovladanie(); } }
    });
    const dychPruh = (d, ms) => {
      dPruh.style.transition = "none"; dPruh.style.width = d === "in" ? "0%" : "100%";
      dPruh.className = d === "in" ? "nadych" : "vydych";
      requestAnimationFrame(() => requestAnimationFrame(() => { dPruh.style.transition = `width ${ms}ms linear`; dPruh.style.width = d === "in" ? "100%" : "0%"; }));
    };
    /* poradie hlasu (pokyn 19.9.): ~2,5 s pred ďalším cvikom povie LEN slovensky, čo príde („ďalej: predklon");
       pri začiatku cviku „blomp" + počet + slovensky + sanskrit */
    /* na začiatku nového bloku najprv jeho názov */
    const hovorKrok = k => {
      const novy = i > 0 && prog[i - 1].sekv !== k.sekv;
      povedz((novy ? [nazovBloku(SEKVENCIE[k.sekv], N.jazyk)] : []).concat(slovaKroku(k)), true);
    };
    /* predpoveď: v režime „oboje" aj „sk" slovensky (pokyn 19.9.), v režime „san" sanskritom */
    const predpovedz = () => { const d = prog[i + 1]; if (d && N.nazvy && rezim === "tv") povedz(["ďalej", N.jazyk === "san" ? (d.vysl || d.san) : d.sk]); };

    const spusti = () => {
      if (i >= prog.length) return koniec(true);
      const k = prog[i];
      const ms = dlzka(k) * 1000;
      zobrazKrok(k, ms); dychPruh(k.d, ms);
      blomp();
      setTimeout(() => { if (k.d === "in") tonNadych(); else tonVydych(); }, 250);
      hovorKrok(k);
      clearTimeout(timer); clearTimeout(holdTimer); clearTimeout(predpovedTimer);
      if (!k.holdDychov && rezim === "tv") predpovedTimer = setTimeout(predpovedz, Math.max(ms * 0.5, ms - 2500));
      const poPohybe = () => {
        if (!k.holdDychov) return dalej();
        /* výdrž: n dychov, každý nádych/výdych tón + pruh */
        let zost = k.holdDychov, faza = "in";
        povedz(`${k.holdDychov} ${k.holdDychov === 1 ? "dych" : k.holdDychov < 5 ? "dychy" : "dychov"}`);
        const jeden = () => {
          if (faza === "in" && zost === 0) return dalej();
          const T = tempoB(k.sekv), d = faza === "in" ? T.in : T.out;   /* zmena tempa počas výdrže platí hneď od ďalšieho dychu */
          elDych.textContent = (faza === "in" ? "NÁDYCH" : "VÝDYCH") + `  ${zost}`;
          elDych.className = "cv-dych " + (faza === "in" ? "nadych" : "vydych");
          dychPruh(faza, d * 1000);
          if (faza === "in") tonNadych(); else tonVydych();
          if (faza === "out" && zost === 1) predpovedTimer = setTimeout(predpovedz, Math.max(d * 500, d * 1000 - 2500));
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
      if (dokoncene) { povedz(N.jazyk === "sk" ? "Hotovo." : "Hotovo. Samasthiti.", true); setTimeout(() => ukaz("nastavenie"), 2500); }
      else ukaz("nastavenie");
    };
    const start = r => {
      rezim = r; prog = zostavProgram(); i = 0; bezi = true; pauza = false;
      btnPauza.textContent = "Pauza";
      $(".cv-klav").textContent = (r === "tv" ? "medzerník = pauza · šípky = cvik · R = znova · Esc = koniec" : "Ďalej = ďalší cvik · R = zopakovať · šípky · Esc = koniec") + " · + / − = tempo dychu · H = jazyk hlasu · T = jazyk textov · C = číslo";
      $("#cv-next").style.visibility = "visible";
      audio(); post.skoc(SEKVENCIE[prog[0].sekv].zaciatok || "samasthiti");
      drzObrazovku();
      ukaz("cvicenie");
      const S0 = SEKVENCIE[prog[0].sekv];
      texty(prog[0]); elDych.textContent = ""; elDych.className = "cv-dych";
      povedz(N.jazyk === "sk" ? [S0.nazovSk || S0.nazov, "Stoj. Pripravený?"] : [S0.nazov, "Samasthiti. Pripravený?"], true);
      timer = setTimeout(spusti, 2500);
    };
    const stop = () => { bezi = false; clearTimeout(timer); clearTimeout(holdTimer); clearTimeout(predpovedTimer); zastavHlas(); pustiObrazovku(); };
    const prepniPauzu = () => {
      if (!bezi) return;
      pauza = !pauza; btnPauza.textContent = pauza ? "Pokračovať" : "Pauza";
      if (pauza) { clearTimeout(timer); clearTimeout(holdTimer); clearTimeout(predpovedTimer); povedz("pauza"); } else spusti();
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
      if (!bezi || e.ctrlKey || e.metaKey || e.altKey) return;
      if (e.key === " " || e.key === "Enter" || e.key === "MediaPlayPause") { e.preventDefault(); rezim === "tv" ? prepniPauzu() : (i++, spusti()); }
      else if (e.key === "ArrowRight") skok(1);
      else if (e.key === "ArrowLeft") skok(-1);
      else if (e.key.toLowerCase() === "r") znova();
      else if (e.key === "Escape" || e.key === "Backspace") koniec(false);
      else if (e.key === "+" || e.key === "=" || e.key === "-" || e.key === "_") { const k = prog[i]; if (k) { zmenTempo(k.sekv, (e.key === "-" || e.key === "_") ? -0.5 : 0.5); ovladanie(); } }
      else if (e.key.toLowerCase() === "h") nastav("jazyk", JAZYKY[(JAZYKY.indexOf(N.jazyk) + 1) % 3]);
      else if (e.key.toLowerCase() === "t") nastav("jazykText", JAZYKY[(JAZYKY.indexOf(N.jazykText) + 1) % 3]);
      else if (e.key.toLowerCase() === "c") nastav("cislo");
    });
    /* obrazovka nech nezhasne počas cvičenia (wake lock sa po skrytí karty obnoví) */
    document.addEventListener("visibilitychange", () => { if (bezi && !document.hidden && "wakeLock" in navigator) navigator.wakeLock.request("screen").then(l => { wl = l; }).catch(() => {}); });
    return { start, stop };
  })();

  /* ---------- úvodná postava: pomaly prechádza pózami Surya A ---------- */
  (() => {
    const hero = new Postava($("#hero-svg"), "#1f6f5a");
    let i = 0; const kroky = SEKVENCIE.A.kroky;
    setInterval(() => { hero.prejdi(kroky[i].poza, 1600, kroky[i].zrkadlo, null, kroky[i].cez); i = (i + 1) % kroky.length; }, 2600);
  })();

  /* ---------- štart podľa #hash ---------- */
  const h = location.hash.replace("#", "");
  if (["dych", "nastavenie", "oprogramoch"].includes(h)) ukaz(h);
  if ("serviceWorker" in navigator) navigator.serviceWorker.register("sw.js").catch(() => {});
})();
