"""Predrenderuje slovenský hlas pre najdijogu.sk cez Google Cloud Text-to-Speech (Chirp3-HD).

Beží na RUDY 2:  python nastroje\hlas-generuj.py [--hlas sk-SK-Chirp3-HD-Aoede] [--vystup hlas]
Kľúč: service account ~/bm-secrets/gcp_openexec_reader.json (projekt bm-elektro-analytics, API zapnuté 19.9.2026).
Výstup: <vystup>/<hlas>/<kluc>.mp3 + <vystup>/manifest.json  { "hlas": ..., "klipy": { "<text>": "<subor>" } }
Znova sa generuje len to, čo chýba (idempotentné). Cena: Chirp3-HD 30 USD / 1 mil. znakov → celá sada < 0,20 USD.
"""
import argparse, hashlib, json, re, sys, time, base64, urllib.request, urllib.parse
from pathlib import Path

def token():
    import jwt
    sa = json.load(open(Path.home()/"bm-secrets"/"gcp_openexec_reader.json"))
    now = int(time.time())
    t = jwt.encode({"iss": sa["client_email"], "scope": "https://www.googleapis.com/auth/cloud-platform",
                    "aud": "https://oauth2.googleapis.com/token", "iat": now, "exp": now+3600}, sa["private_key"], algorithm="RS256")
    d = urllib.parse.urlencode({"grant_type": "urn:ietf:params:oauth:grant-type:jwt-bearer", "assertion": t}).encode()
    return json.load(urllib.request.urlopen(urllib.request.Request("https://oauth2.googleapis.com/token", d)))["access_token"], sa["project_id"]

def texty():
    """Zoznam viet na nahratie — musí sedieť s tým, čo appka posiela do povedz()."""
    src = Path(__file__).resolve().parent.parent / "sekvencie.js"
    js = src.read_text(encoding="utf-8")
    t = set()
    for m in re.finditer(r'\["[^"]+","([^"]+)"\]', js):            # COUNT fonetika
        t.add(m.group(1))
    for m in re.finditer(r'vysl:"([^"]+)"', js): t.add(m.group(1))   # názvy ásan foneticky
    for m in re.finditer(r'sk:"([^"]+)"', js): t.add(m.group(1))     # slovenské názvy
    for m in re.finditer(r'nazov: "([^"]+)"', js): t.add(m.group(1)) # Surya Namaskara A/B
    t |= {"nádych", "výdych", "pauza", "Hotovo. Samasthiti.", "Samasthiti. Pripravený?", "Pripravená?",
          "ekam, úrdhva hastásana, ruky hore", "dve, uttánásana, predklon",
          "Nádych aj výdych rovnako dlhé. Nechaj si tempo, ktoré udýchaš.", "Posledný pohyb.", "Ešte raz."}
    for n in range(1, 9):
        t.add(f"{n} {'dych' if n == 1 else 'dychy' if n < 5 else 'dychov'}")
    return sorted(t)

def kluc(text):
    return re.sub(r"[^a-z0-9]+", "-", text.lower().translate(str.maketrans("áäčďéíĺľňóôŕšťúýž", "aacdeillnoorstuyz"))).strip("-")[:60] + "-" + hashlib.md5(text.encode()).hexdigest()[:6]

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--hlas", default="sk-SK-Chirp3-HD-Aoede")
    ap.add_argument("--vystup", default=str(Path(__file__).resolve().parent.parent / "hlas"))
    ap.add_argument("--rychlost", type=float, default=0.95)
    ap.add_argument("--len-ukazka", action="store_true", help="len jedna ukážková veta (na výber hlasu)")
    a = ap.parse_args()
    acc, proj = token()
    out = Path(a.vystup) / a.hlas; out.mkdir(parents=True, exist_ok=True)
    man_p = Path(a.vystup) / "manifest.json"
    man = json.load(open(man_p, encoding="utf-8")) if man_p.exists() else {"hlas": a.hlas, "klipy": {}}
    vety = ["ekam, úrdhva hastásana, ruky hore. dve, uttánásana, predklon. Nádych. Výdych."] if a.len_ukazka else texty()
    nove = 0; znaky = 0
    for text in vety:
        k = kluc(text); f = out / f"{k}.mp3"
        if f.exists() and not a.len_ukazka:
            man["klipy"][text] = f"{a.hlas}/{k}.mp3"; continue
        body = json.dumps({"input": {"text": text}, "voice": {"languageCode": "sk-SK", "name": a.hlas},
                           "audioConfig": {"audioEncoding": "MP3", "speakingRate": a.rychlost, "sampleRateHertz": 24000}}).encode()
        req = urllib.request.Request("https://texttospeech.googleapis.com/v1/text:synthesize", body,
                                     headers={"Authorization": "Bearer " + acc, "x-goog-user-project": proj, "Content-Type": "application/json"})
        for pokus in range(3):
            try:
                r = json.load(urllib.request.urlopen(req, timeout=60)); break
            except urllib.error.HTTPError as e:
                print("HTTP", e.code, e.read().decode()[:300]); time.sleep(2 + pokus * 3)
        else:
            print("PRESKOCENE:", text); continue
        f.write_bytes(base64.b64decode(r["audioContent"]))
        man["klipy"][text] = f"{a.hlas}/{k}.mp3"; nove += 1; znaky += len(text)
        print(f"  {text!r} -> {f.name}")
    if not a.len_ukazka:
        man["hlas"] = a.hlas
        man_p.write_text(json.dumps(man, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"HOTOVO: {nove} nových klipov, {znaky} znakov, spolu {len(man['klipy'])} klipov, hlas {a.hlas}")

if __name__ == "__main__":
    main()
