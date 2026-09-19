"""Nasadenie najdijogu.sk na WebSupport hosting `bmelektro` (docroot /najdijogu.sk/web).

Beží na RUDY 2 (má velenie na G: a ~/bm-secrets):
    python nastroje\\deploy.py            # nahrá všetko zmenené (podľa md5 v .deploy-stav.json)
    python nastroje\\deploy.py --vsetko   # nahrá všetko
Spojenie a heslo: `00-VELENIE/SCRIPTS/_spojenie.py` + `bm_creds.tajomstvo("sftp_claude_bot_bmelektro")`
(kánon hesiel sa neotvára, hodnota sa nevypisuje). Po nahratí overí HTTP 200 + md5 troch súborov.
"""
import argparse, hashlib, json, sys, urllib.request
from pathlib import Path
KOREN = Path(__file__).resolve().parent.parent
sys.path.insert(0, r"G:\Spoločné disky\CLAUDE\00-VELENIE\SCRIPTS")
from _spojenie import Server
import bm_creds

REMOTE = "/najdijogu.sk/web"
VYNECHAJ = {".git", ".github", "nastroje", "navrhy", "README.md", "CNAME", ".deploy-stav.json", "node_modules"}
STAV = KOREN / ".deploy-stav.json"

def md5(p): return hashlib.md5(p.read_bytes()).hexdigest()

def subory():
    for p in sorted(KOREN.rglob("*")):
        if not p.is_file(): continue
        rel = p.relative_to(KOREN)
        if any(part in VYNECHAJ for part in rel.parts): continue
        yield rel, p

def main():
    ap = argparse.ArgumentParser(); ap.add_argument("--vsetko", action="store_true"); a = ap.parse_args()
    stav = json.loads(STAV.read_text()) if STAV.exists() and not a.vsetko else {}
    s = Server("bmelektro.sk", "claude-bot.bmelektro.sk", bm_creds.tajomstvo("sftp_claude_bot_bmelektro"), port=22)
    n = 0
    for rel, p in subory():
        h = md5(p); k = rel.as_posix()
        if stav.get(k) == h: continue
        s.put(str(p), f"{REMOTE}/{k}")
        stav[k] = h; n += 1
        print("  ↑", k)
    STAV.write_text(json.dumps(stav, indent=0))
    print(f"nahraných {n} súborov")
    # overenie
    for k in ["index.html", "app.js", "hlas/manifest.json"]:
        try:
            r = urllib.request.urlopen(urllib.request.Request(f"https://najdijogu.sk/{k}", headers={"Cache-Control": "no-cache"}), timeout=20)
            ok = hashlib.md5(r.read()).hexdigest() == md5(KOREN / k)
            print(f"  {k}: HTTP {r.status}, md5 {'sedí' if ok else 'NESEDÍ (cache alebo DNS ešte ukazuje inam)'}")
        except Exception as e:
            print(f"  {k}: {e}")

if __name__ == "__main__":
    main()
