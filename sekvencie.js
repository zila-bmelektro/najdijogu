/* Pózy (kľúčové body obrysovej postavy, bočný pohľad, viewBox 200×200, zem y=180)
   a sekvencie Surya Namaskara A / B podľa tradičného vinyasa countu
   (Pattabhi Jois / Sharath Jois; nepárne = nádych, párne = výdych). */

const POZY = {
  samasthiti:  { h:[100,38],  n:[100,52],  p:[100,105], eL:[91,80],   wL:[92,102],  eR:[109,80],  wR:[108,102], kL:[95,142],  fL:[92,180],  kR:[105,142], fR:[108,180] },
  urdhva_hast: { h:[103,38],  n:[100,52],  p:[100,105], eL:[92,30],   wL:[98,8],    eR:[112,30],  wR:[108,8],   kL:[95,142],  fL:[92,180],  kR:[105,142], fR:[108,180] },
  uttanasana:  { h:[118,166], n:[118,150], p:[100,105], eL:[112,166], wL:[108,180], eR:[122,168], wR:[118,180], kL:[97,142],  fL:[94,180],  kR:[103,142], fR:[106,180] },
  ardha_utt:   { h:[158,98],  n:[145,104], p:[100,105], eL:[128,140], wL:[110,164], eR:[136,142], wR:[118,166], kL:[97,142],  fL:[94,180],  kR:[103,142], fR:[106,180] },
  chaturanga:  { h:[172,148], n:[158,152], p:[90,155],  eL:[146,172], wL:[152,180], eR:[160,170], wR:[166,180], kL:[52,160],  fL:[18,176],  kR:[58,158],  fR:[24,176]  },
  urdhva_mukha:{ h:[168,98],  n:[160,116], p:[95,150],  eL:[156,150], wL:[156,180], eR:[166,150], wR:[168,180], kL:[52,162],  fL:[18,178],  kR:[58,160],  fR:[24,178]  },
  adho_mukha:  { h:[152,146], n:[140,130], p:[100,80],  eL:[150,156], wL:[164,180], eR:[160,154], wR:[174,180], kL:[66,132],  fL:[40,180],  kR:[74,130],  fR:[50,180]  },
  utkatasana:  { h:[110,56],  n:[105,70],  p:[95,120],  eL:[106,44],  wL:[112,20],  eR:[120,46],  wR:[122,22],  kL:[108,152], fL:[100,180], kR:[116,150], fR:[110,180] },
  vira_A:      { h:[100,43],  n:[100,58],  p:[100,110], eL:[94,34],   wL:[98,12],   eR:[108,34],  wR:[106,12],  kL:[70,150],  fL:[45,180],  kR:[130,145], fR:[135,180] },
};

/* Sanskritské číslovky: zápis + fonetický tvar pre slovenský hlas */
const COUNT = [
  null,
  ["ekam","ekam"], ["dve","dve"], ["trīni","tríni"], ["catvāri","čatvári"], ["pañca","panča"],
  ["ṣaṭ","šat"], ["sapta","sapta"], ["aṣṭau","aštau"], ["nava","nava"], ["daśa","daša"],
  ["ekādaśa","ékádaša"], ["dvādaśa","dvádaša"], ["trayodaśa","trajódaša"], ["caturdaśa","čaturdaša"],
  ["pañcadaśa","pančadaša"], ["ṣoḍaśa","šódaša"], ["saptadaśa","saptadaša"],
];

/* krok: c = vinyasa count, d = 'in'|'out', poza, san = sanskrit, sk = slovensky, dr = drishti, hold = počet dychov výdrže (0 = len jeden pohyb) */
const SEKVENCIE = {
  A: {
    nazov: "Surya Namaskara A", vinyas: 9,
    kroky: [
      { c:1, d:"in",  poza:"urdhva_hast",  san:"Ūrdhva Hastāsana",        sk:"ruky hore",                    dr:"palce" },
      { c:2, d:"out", poza:"uttanasana",   san:"Uttānāsana",              sk:"predklon",                     dr:"nos" },
      { c:3, d:"in",  poza:"ardha_utt",    san:"Ardha Uttānāsana",        sk:"hlava hore, rovný chrbát",     dr:"tretie oko" },
      { c:4, d:"out", poza:"chaturanga",   san:"Chaturaṅga Daṇḍāsana",    sk:"nízky klik",                   dr:"nos" },
      { c:5, d:"in",  poza:"urdhva_mukha", san:"Ūrdhva Mukha Śvānāsana",  sk:"pes hlavou hore",              dr:"tretie oko" },
      { c:6, d:"out", poza:"adho_mukha",   san:"Adho Mukha Śvānāsana",    sk:"pes hlavou dole",              dr:"pupok", hold:"pes" },
      { c:7, d:"in",  poza:"ardha_utt",    san:"Ardha Uttānāsana",        sk:"skok dopredu, hlava hore",     dr:"tretie oko" },
      { c:8, d:"out", poza:"uttanasana",   san:"Uttānāsana",              sk:"predklon",                     dr:"nos" },
      { c:9, d:"in",  poza:"urdhva_hast",  san:"Ūrdhva Hastāsana",        sk:"ruky hore",                    dr:"palce" },
      { c:0, d:"out", poza:"samasthiti",   san:"Samasthitiḥ",             sk:"stoj",                         dr:"nos" },
    ],
  },
  B: {
    nazov: "Surya Namaskara B", vinyas: 17,
    kroky: [
      { c:1,  d:"in",  poza:"utkatasana",   san:"Utkaṭāsana",             sk:"stolička, ruky hore",          dr:"palce" },
      { c:2,  d:"out", poza:"uttanasana",   san:"Uttānāsana",             sk:"predklon",                     dr:"nos" },
      { c:3,  d:"in",  poza:"ardha_utt",    san:"Ardha Uttānāsana",       sk:"hlava hore",                   dr:"tretie oko" },
      { c:4,  d:"out", poza:"chaturanga",   san:"Chaturaṅga Daṇḍāsana",   sk:"nízky klik",                   dr:"nos" },
      { c:5,  d:"in",  poza:"urdhva_mukha", san:"Ūrdhva Mukha Śvānāsana", sk:"pes hlavou hore",              dr:"tretie oko" },
      { c:6,  d:"out", poza:"adho_mukha",   san:"Adho Mukha Śvānāsana",   sk:"pes hlavou dole",              dr:"pupok" },
      { c:7,  d:"in",  poza:"vira_A",       san:"Vīrabhadrāsana A",       sk:"bojovník, pravá noha vpredu",  dr:"palce" },
      { c:8,  d:"out", poza:"chaturanga",   san:"Chaturaṅga Daṇḍāsana",   sk:"nízky klik",                   dr:"nos" },
      { c:9,  d:"in",  poza:"urdhva_mukha", san:"Ūrdhva Mukha Śvānāsana", sk:"pes hlavou hore",              dr:"tretie oko" },
      { c:10, d:"out", poza:"adho_mukha",   san:"Adho Mukha Śvānāsana",   sk:"pes hlavou dole",              dr:"pupok" },
      { c:11, d:"in",  poza:"vira_A",       san:"Vīrabhadrāsana A",       sk:"bojovník, ľavá noha vpredu",   dr:"palce", zrkadlo:true },
      { c:12, d:"out", poza:"chaturanga",   san:"Chaturaṅga Daṇḍāsana",   sk:"nízky klik",                   dr:"nos" },
      { c:13, d:"in",  poza:"urdhva_mukha", san:"Ūrdhva Mukha Śvānāsana", sk:"pes hlavou hore",              dr:"tretie oko" },
      { c:14, d:"out", poza:"adho_mukha",   san:"Adho Mukha Śvānāsana",   sk:"pes hlavou dole",              dr:"pupok", hold:"pes" },
      { c:15, d:"in",  poza:"ardha_utt",    san:"Ardha Uttānāsana",       sk:"skok dopredu, hlava hore",     dr:"tretie oko" },
      { c:16, d:"out", poza:"uttanasana",   san:"Uttānāsana",             sk:"predklon",                     dr:"nos" },
      { c:17, d:"in",  poza:"utkatasana",   san:"Utkaṭāsana",             sk:"stolička, ruky hore",          dr:"palce" },
      { c:0,  d:"out", poza:"samasthiti",   san:"Samasthitiḥ",            sk:"stoj",                         dr:"nos" },
    ],
  },
};
