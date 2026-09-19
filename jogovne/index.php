<?php
/* najdijogu.sk/jogovne/ — verejný adresár: zoznam podľa polohy + profil + články + registrácia.
   Cesty (cez .htaccess): /jogovne/ · /jogovne/pridat · /jogovne/<slug> · /jogovne/<slug>/clanky/<clanok> · /jogovne/udalost (POST štatistika) */
require __DIR__ . '/../studio/lib/jadro.php';
$cesta = trim((string)($_GET['c'] ?? ''), '/'); $casti = $cesta === '' ? [] : explode('/', $cesta);
$view = 'zoznam'; $partner = null; $clanok = null;
if (($casti[0] ?? '') === 'pridat') $view = 'pridat';
elseif (($casti[0] ?? '') === 'udalost') $view = 'udalost';
elseif ($casti) { $partner = nj_partner_podla_slugu($casti[0]); if (!$partner || !in_array($partner['stav'], ['schvalena'], true)) { http_response_code(404); $view = '404'; } elseif (($casti[1] ?? '') === 'clanky' && !empty($casti[2])) { foreach (nj_clanky($partner['id']) as $c) if ($c['slug'] === $casti[2] && $c['stav'] === 'publikovany') $clanok = $c; $view = $clanok ? 'clanok' : '404'; if (!$clanok) http_response_code(404); } else $view = 'profil'; }

/* ---- štatistika (POST z prehliadača, bez cookies; kokpit sa nepočíta) ---- */
if ($view === 'udalost') {
    header('Content-Type: text/plain'); $u = preg_replace('/[^a-z_]/', '', $_POST['u'] ?? ''); $pid = preg_replace('/[^a-z0-9]/', '', $_POST['p'] ?? ''); $o = preg_replace('/[^a-z0-9]/', '', $_POST['o'] ?? '');
    if ($pid && in_array($u, ['klik_web', 'klik_telefon', 'klik_rezervacia', 'klik_mapa', 'clanok_docitanie', 'clanok_klik_jogovna'], true) && nj_partner($pid)) nj_stat_zapis($pid, $u, $o);
    exit('ok');
}
/* ---- registrácia jogovne ---- */
$chyby = []; $odoslane = false;
if ($view === 'pridat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_rate_limit('registracia', 5, 3600);
    if (!empty($_POST['web2'])) exit('ok'); // honeypot
    $z = fn($k, $m = 200) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $m);
    $d = ['nazov' => $z('nazov', 80), 'email' => mb_strtolower($z('email', 120)), 'telefon' => $z('telefon', 40), 'web' => $z('web', 200), 'mesto' => $z('mesto', 60), 'adresa' => $z('adresa', 160), 'popis' => $z('popis', 1500), 'styly' => array_values(array_intersect(NJ_STYLY, (array)($_POST['styly'] ?? [])))];
    if (mb_strlen($d['nazov']) < 2) $chyby[] = 'Napíšte názov jogovne.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $chyby[] = 'Napíšte platný e-mail.';
    if (mb_strlen($d['mesto']) < 2) $chyby[] = 'Napíšte mesto.';
    if (empty($_POST['suhlas'])) $chyby[] = 'Potvrďte súhlas so zverejnením profilu.';
    if (!$chyby && nj_partner_podla_emailu($d['email'])) $chyby[] = 'Tento e-mail už jogovňu má — prihláste sa do Studia.';
    if (!$chyby) {
        if ($d['web'] && !preg_match('~^https?://~', $d['web'])) $d['web'] = 'https://' . $d['web'];
        $p = nj_novy_partner($d);
        $tok = bin2hex(random_bytes(12)); nj_uprav('reg_tokeny', fn($t) => $t + [$tok => ['partner' => $p['id'], 'do' => time() + 7 * 86400]]);
        nj_audit('registracia', ['partner' => $p['id'], 'detail' => $p['nazov'] . ' · ' . $p['mesto']]);
        nj_email($d['email'], 'najdijogu: dokončite registráciu jogovne ' . $d['nazov'], '<p>Ďakujeme! Jogovňa <b>' . h($d['nazov']) . '</b> je zapísaná. Registráciu dokončíte prihlásením cez Google (stačí kliknúť a vybrať účet):</p><p><a href="' . NJ_STUDIO . '/api/registracia.php?t=' . $tok . '" style="display:inline-block;background:#1f6f5a;color:#fff;padding:10px 18px;border-radius:999px;text-decoration:none;font-weight:600">Dokončiť registráciu</a></p><p>Potom si v Studiu doplníte popis, fotky, služby a ceny. Profil zverejníme po krátkej kontrole (do 1 pracovného dňa).</p><p style="color:#5d6963;font-size:13px">Odkaz platí 7 dní. Ak ste jogovňu neprihlasovali vy, tento e-mail ignorujte.</p>');
        nj_email(NJ_ADMINI[0], 'najdijogu: nová jogovňa — ' . $d['nazov'] . ' (' . $d['mesto'] . ')', '<p>' . h($d['nazov']) . ', ' . h($d['mesto']) . ', ' . h($d['email']) . '</p><p><a href="' . NJ_STUDIO . '/?p=admin_partner&id=' . $p['id'] . '">Otvoriť v admine</a></p>');
        $odoslane = true;
    }
}
/* ---- zoznam: poloha + filtre ---- */
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null; $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$okruh = max(2, min(200, (int)($_GET['km'] ?? 25))); $styl = in_array($_GET['styl'] ?? '', NJ_STYLY, true) ? $_GET['styl'] : ''; $minR = (float)($_GET['min'] ?? 0); $radenie = $_GET['r'] ?? 'vzdialenost'; $mestoQ = mb_strtolower(trim((string)($_GET['mesto'] ?? '')));
$zoznam = [];
if ($view === 'zoznam') {
    foreach (nj_partneri() as $p) {
        if ($p['stav'] !== 'schvalena') continue;
        if ($styl && !in_array($styl, $p['styly'], true)) continue;
        if ($minR && (float)($p['google_rating'] ?? 0) < $minR) continue;
        if ($mestoQ && !str_contains(mb_strtolower($p['mesto'] . ' ' . $p['adresa']), $mestoQ)) continue;
        $p['km'] = ($lat && $lng && $p['lat']) ? nj_vzdialenost($lat, $lng, (float)$p['lat'], (float)$p['lng']) : null;
        if ($lat && $lng && $p['km'] !== null && $p['km'] > $okruh) continue;
        $p['skore'] = ($p['google_rating'] ?? 0) * log(1 + ($p['google_pocet'] ?? 0)); // rating × váha počtu recenzií
        $zoznam[] = $p;
    }
    usort($zoznam, function ($a, $b) use ($radenie) {
        $pa = nj_partner_aktivny($a) ? 1 : 0; $pb = nj_partner_aktivny($b) ? 1 : 0; if ($pa !== $pb) return $pb <=> $pa; // partneri hore
        if ($radenie === 'hodnotenie') return $b['skore'] <=> $a['skore'];
        if ($a['km'] !== null && $b['km'] !== null) return $a['km'] <=> $b['km'];
        return strcmp($a['nazov'], $b['nazov']);
    });
    foreach ($zoznam as $p) nj_stat_zapis($p['id'], 'zobrazenie_v_zozname');
}
if ($view === 'profil') nj_stat_zapis($partner['id'], 'zobrazenie_profilu');
if ($view === 'clanok') nj_stat_zapis($partner['id'], 'clanok_zobrazenie', $clanok['id']);
$titul = ['zoznam' => 'Jogovne v tvojom okolí', 'pridat' => 'Prihlásiť jogovňu', 'profil' => $partner['nazov'] ?? '', 'clanok' => $clanok['nadpis'] ?? '', '404' => 'Nenašli sme'][$view];
$popisMeta = ['zoznam' => 'Nájdi jogovňu podľa polohy, štýlu a hodnotenia z Google. Kurzy pre začiatočníkov, Mysore, skupinové lekcie na Slovensku a v Česku.', 'pridat' => 'Prihláste svoju jogovňu na najdijogu.sk — bezplatný profil, hodnotenie z Google, články a štatistiky.', 'profil' => $partner ? mb_substr($partner['popis'] ?: ($partner['nazov'] . ', ' . $partner['mesto']), 0, 155) : '', 'clanok' => $clanok['meta'] ?? '', '404' => ''][$view];
?><!DOCTYPE html>
<html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titul) ?> · najdijogu.sk</title><meta name="description" content="<?= h($popisMeta) ?>">
<link rel="icon" href="/ikona.svg"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/jogovne/jogovne.css">
<?php if ($view === 'profil'): ?><link rel="canonical" href="<?= NJ_WEB ?>/jogovne/<?= h($partner['slug']) ?>"><script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'ExerciseGym', 'name' => $partner['nazov'], 'url' => NJ_WEB . '/jogovne/' . $partner['slug'], 'telephone' => $partner['telefon'], 'address' => ['@type' => 'PostalAddress', 'streetAddress' => $partner['adresa'], 'addressLocality' => $partner['mesto'], 'addressCountry' => 'SK'], 'geo' => $partner['lat'] ? ['@type' => 'GeoCoordinates', 'latitude' => $partner['lat'], 'longitude' => $partner['lng']] : null, 'aggregateRating' => $partner['google_rating'] ? ['@type' => 'AggregateRating', 'ratingValue' => $partner['google_rating'], 'reviewCount' => $partner['google_pocet']] : null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script><?php endif; ?>
<?php if ($view === 'clanok'): ?><link rel="canonical" href="<?= NJ_WEB ?>/jogovne/<?= h($partner['slug']) ?>/clanky/<?= h($clanok['slug']) ?>"><script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $clanok['nadpis'], 'description' => $clanok['perex'], 'datePublished' => $clanok['publikovane'], 'author' => ['@type' => 'Organization', 'name' => $partner['nazov']], 'publisher' => ['@type' => 'Organization', 'name' => 'najdijogu.sk']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script><?php endif; ?>
</head><body class="jogovne-web">
<header class="hlavicka"><a class="logo" href="/"><svg class="logo-znak" viewBox="0 0 112 112" aria-hidden="true"><path d="M56 6c-24 0-40 17-40 39 0 28 40 60 40 60s40-32 40-60c0-22-16-39-40-39z" fill="#1f6f5a"/><g fill="none" stroke="#f3efe4" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"><circle cx="56" cy="22" r="6.5" fill="#f3efe4" stroke="none"/><path d="M56 31v30M56 40l-12-13M56 40l12-13M56 61v24M56 61l11 8-11 7"/></g></svg>najdi<b>jogu</b><span class="tld">.sk</span></a><nav class="hlavicka-nav"><a href="/#dych">Cvičiť doma</a><a href="/jogovne/">Jogovne</a><a href="/jogovne/pridat">Pre jogovne</a></nav></header>
<main class="jg">
<?php if ($view === 'zoznam'): ?>
<h1>Jogovne v tvojom okolí</h1>
<p class="perex">Doma je skvelý začiatok. Keď chceš učiteľa, ktorý ti opraví Chaturangu, alebo ľudí, ktorých joga baví ako teba — tu nájdeš, kam ísť.</p>
<form class="filtre" method="get" id="f-filtre">
  <input type="hidden" name="lat" id="lat" value="<?= h((string)$lat) ?>"><input type="hidden" name="lng" id="lng" value="<?= h((string)$lng) ?>">
  <button type="button" class="btn primar" id="btn-poloha"><?= $lat ? '📍 moja poloha' : '📍 Použiť moju polohu' ?></button>
  <input name="mesto" value="<?= h($mestoQ) ?>" placeholder="alebo mesto…" class="pole">
  <select name="km" class="pole"><?php foreach ([5, 10, 25, 50, 100, 200] as $k): ?><option value="<?= $k ?>" <?= $k === $okruh ? 'selected' : '' ?>>do <?= $k ?> km</option><?php endforeach; ?></select>
  <select name="styl" class="pole"><option value="">všetky štýly</option><?php foreach (NJ_STYLY as $s): ?><option <?= $s === $styl ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
  <select name="min" class="pole"><option value="0">akékoľvek hodnotenie</option><option value="4" <?= $minR == 4 ? 'selected' : '' ?>>4,0 ★ a viac</option><option value="4.5" <?= $minR == 4.5 ? 'selected' : '' ?>>4,5 ★ a viac</option></select>
  <select name="r" class="pole"><option value="vzdialenost" <?= $radenie === 'vzdialenost' ? 'selected' : '' ?>>najbližšie</option><option value="hodnotenie" <?= $radenie === 'hodnotenie' ? 'selected' : '' ?>>najlepšie hodnotené</option></select>
  <button class="btn">Hľadať</button>
</form>
<?php if (!$zoznam): ?><div class="karta prazdne"><p><?= nj_partneri() ? 'V tomto okruhu sme nenašli žiadnu jogovňu. Skús väčší okruh alebo iné mesto.' : 'Prvé jogovne práve pridávame. Máš jogovňu? <a href="/jogovne/pridat">Prihlás ju</a> — je to zadarmo.' ?></p></div><?php endif; ?>
<div class="zoznam">
<?php foreach ($zoznam as $p): ?>
<a class="jog <?= nj_partner_aktivny($p) ? 'partner' : '' ?>" href="/jogovne/<?= h($p['slug']) ?>">
  <div class="foto"><?php if ($p['fotky']): ?><img src="/obrazky/jogovne/<?= h($p['fotky'][0]) ?>" alt="" loading="lazy"><?php else: ?><svg viewBox="0 0 112 112"><path d="M56 6c-24 0-40 17-40 39 0 28 40 60 40 60s40-32 40-60c0-22-16-39-40-39z" fill="#dfe8e2"/></svg><?php endif; ?></div>
  <div class="info"><h3><?= h($p['nazov']) ?><?= nj_partner_aktivny($p) ? ' <span class="odznak">partner</span>' : '' ?></h3>
  <p class="meta"><?= h($p['mesto']) ?><?= $p['km'] !== null ? ' · ' . number_format($p['km'], 1, ',', '') . ' km' : '' ?><?= $p['google_rating'] ? ' · <b class="hviezdy">' . number_format((float)$p['google_rating'], 1, ',', '') . ' ★</b> <span class="tlm">(' . (int)$p['google_pocet'] . ')</span>' : '' ?></p>
  <p class="styly"><?= h(implode(' · ', array_slice($p['styly'], 0, 4))) ?></p>
  <?php if ($p['akcie']): ?><p class="akcia">🎁 <?= h($p['akcie'][0]['nazov']) ?></p><?php endif; ?></div>
</a>
<?php endforeach; ?>
</div>
<p class="drobne">Hodnotenia poskytuje Google. Partneri sú zvýraznení. <a href="/jogovne/pridat">Chýba tu tvoja jogovňa?</a></p>

<?php elseif ($view === 'profil'): $g = null; if ($partner['google_place_id']) { require_once __DIR__ . '/../studio/lib/google.php'; $g = nj_places_detail($partner['google_place_id']); } $cl = array_filter(nj_clanky($partner['id']), fn($c) => $c['stav'] === 'publikovany'); ?>
<p><a href="/jogovne/">‹ Jogovne</a></p>
<article class="profil" data-p="<?= h($partner['id']) ?>">
<header><h1><?= h($partner['nazov']) ?><?= nj_partner_aktivny($partner) ? ' <span class="odznak">partner najdijogu</span>' : '' ?></h1>
<p class="meta"><?= h($partner['adresa'] ?: $partner['mesto']) ?><?= $partner['google_rating'] ? ' · <b class="hviezdy">' . number_format((float)$partner['google_rating'], 1, ',', '') . ' ★</b> ' . (int)$partner['google_pocet'] . ' recenzií na Google' : '' ?></p>
<p class="styly"><?= h(implode(' · ', $partner['styly'])) ?></p></header>
<?php if ($partner['fotky']): ?><div class="galeria"><?php foreach ($partner['fotky'] as $f): ?><img src="/obrazky/jogovne/<?= h($f) ?>" alt="<?= h($partner['nazov']) ?>" loading="lazy"><?php endforeach; ?></div><?php endif; ?>
<div class="dvojstlpec">
<div>
<?php if ($partner['popis']): ?><section><h2>O jogovni</h2><?= nl2br(h($partner['popis'])) ?></section><?php endif; ?>
<?php if ($partner['akcie']): ?><section><h2>Akcie</h2><?php foreach ($partner['akcie'] as $a): ?><div class="akcia-box"><b><?= h($a['nazov']) ?></b><?= $a['od'] || $a['do'] ? ' <span class="tlm">' . ($a['od'] ? nj_datum($a['od']) : '') . ($a['do'] ? ' – ' . nj_datum($a['do']) : '') . '</span>' : '' ?><?= $a['popis'] ? '<br>' . h($a['popis']) : '' ?></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($partner['sluzby']): ?><section><h2>Služby a ceny</h2><table class="cennik"><?php foreach ($partner['sluzby'] as $s): ?><tr><td><b><?= h($s['nazov']) ?></b><?= $s['popis'] ? '<br><span class="tlm">' . h($s['popis']) . '</span>' : '' ?></td><td><?= h($s['dlzka']) ?></td><td class="cena"><?= h($s['cena']) ?></td></tr><?php endforeach; ?></table></section><?php endif; ?>
<?php if ($g && $g['recenzie']): ?><section><h2>Čo hovoria ľudia (Google)</h2><div class="recenzie"><?php foreach ($g['recenzie'] as $r): ?><blockquote class="recenzia"><b><?= str_repeat('★', (int)$r['hviezdy']) ?></b> <?= h(mb_substr($r['text'], 0, 300)) ?><?= mb_strlen($r['text']) > 300 ? '…' : '' ?><footer><a href="<?= h($r['autor_url']) ?>" rel="nofollow noopener" target="_blank"><?= h($r['autor']) ?></a> · <?= h($r['kedy']) ?></footer></blockquote><?php endforeach; ?></div><p class="drobne">Recenzie a hodnotenie poskytuje Google · <a href="<?= h($g['maps']) ?>" target="_blank" rel="noopener">všetky recenzie v Google Mapách ↗</a></p></section><?php endif; ?>
<?php if ($cl): ?><section><h2>Články z jogovne</h2><ul class="clanky-zoznam"><?php foreach ($cl as $c): ?><li><a href="/jogovne/<?= h($partner['slug']) ?>/clanky/<?= h($c['slug']) ?>"><?= h($c['nadpis']) ?></a> <span class="tlm"><?= nj_datum($c['publikovane']) ?></span><br><span class="tlm"><?= h($c['perex']) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
</div>
<aside class="kontakt karta">
<?php if ($partner['rezervacie_url']): ?><a class="btn primar" href="<?= h($partner['rezervacie_url']) ?>" target="_blank" rel="noopener" data-u="klik_rezervacia">Rezervovať lekciu ↗</a><?php endif; ?>
<?php if ($partner['telefon']): ?><a class="btn" href="tel:<?= h(preg_replace('/\s+/', '', $partner['telefon'])) ?>" data-u="klik_telefon">📞 <?= h($partner['telefon']) ?></a><?php endif; ?>
<?php if ($partner['web']): ?><a class="btn" href="<?= h($partner['web']) ?>" target="_blank" rel="noopener" data-u="klik_web">🌐 web jogovne ↗</a><?php endif; ?>
<?php if ($partner['adresa']): ?><a class="btn" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($partner['adresa'] . ', ' . $partner['mesto']) ?><?= $partner['google_place_id'] ? '&query_place_id=' . h($partner['google_place_id']) : '' ?>" target="_blank" rel="noopener" data-u="klik_mapa">🗺 mapa ↗</a><?php endif; ?>
<?php if ($partner['hodiny']): ?><p><b>Hodiny</b><br><?= h($partner['hodiny']) ?></p><?php endif; ?>
<?php if (!empty($partner['instagram']) || !empty($partner['facebook'])): ?><p><?= !empty($partner['instagram']) ? '<a href="' . h($partner['instagram']) . '" target="_blank" rel="noopener">Instagram</a> ' : '' ?><?= !empty($partner['facebook']) ? '<a href="' . h($partner['facebook']) . '" target="_blank" rel="noopener">Facebook</a>' : '' ?></p><?php endif; ?>
<p class="drobne">Začni doma s <a href="/#dych">dychovým tréningom</a> a pozdravmi slnku — a potom príď sem.</p>
</aside>
</div>
</article>

<?php elseif ($view === 'clanok'): ?>
<p><a href="/jogovne/<?= h($partner['slug']) ?>">‹ <?= h($partner['nazov']) ?></a></p>
<article class="clanok" data-p="<?= h($partner['id']) ?>" data-c="<?= h($clanok['id']) ?>">
<h1><?= h($clanok['nadpis']) ?></h1><p class="meta"><?= nj_datum($clanok['publikovane']) ?> · <a href="/jogovne/<?= h($partner['slug']) ?>" data-u="clanok_klik_jogovna"><?= h($partner['nazov']) ?></a>, <?= h($partner['mesto']) ?></p>
<p class="perex"><?= h($clanok['perex']) ?></p>
<div class="telo"><?= $clanok['html'] ?></div>
<footer class="karta" id="koniec-clanku"><b><?= h($partner['nazov']) ?></b> — <?= h($partner['mesto']) ?><?= $partner['google_rating'] ? ' · ' . number_format((float)$partner['google_rating'], 1, ',', '') . ' ★ Google' : '' ?><br><a class="btn primar" href="/jogovne/<?= h($partner['slug']) ?>" data-u="clanok_klik_jogovna">Pozrieť jogovňu a rozvrh</a></footer>
</article>

<?php elseif ($view === 'pridat'): ?>
<h1>Prihláste svoju jogovňu</h1>
<?php if ($odoslane): ?><div class="karta"><h2>Hotovo — pozrite si e-mail</h2><p>Poslali sme vám odkaz na dokončenie registrácie (prihlásenie cez Google). Potom si v Studiu doplníte fotky, služby a ceny. Profil zverejníme po krátkej kontrole.</p></div>
<?php else: ?>
<div class="dvojstlpec">
<form method="post" class="formular karta"><?php if ($chyby): ?><div class="sprava chyba"><?= h(implode(' ', $chyby)) ?></div><?php endif; ?>
<label>Názov jogovne <input name="nazov" required maxlength="80" value="<?= h($_POST['nazov'] ?? '') ?>"></label>
<div class="dvojica"><label>Mesto <input name="mesto" required maxlength="60" value="<?= h($_POST['mesto'] ?? '') ?>"></label><label>Adresa <input name="adresa" maxlength="160" value="<?= h($_POST['adresa'] ?? '') ?>"></label></div>
<div class="dvojica"><label>E-mail (bude vaše prihlásenie) <input name="email" type="email" required maxlength="120" value="<?= h($_POST['email'] ?? '') ?>"></label><label>Telefón <input name="telefon" maxlength="40" value="<?= h($_POST['telefon'] ?? '') ?>"></label></div>
<label>Web <input name="web" maxlength="200" placeholder="https://" value="<?= h($_POST['web'] ?? '') ?>"></label>
<input name="web2" style="display:none" tabindex="-1" autocomplete="off">
<fieldset><legend>Štýly jogy</legend><div class="chipy"><?php foreach (NJ_STYLY as $s): ?><label class="chip"><input type="checkbox" name="styly[]" value="<?= h($s) ?>" <?= in_array($s, (array)($_POST['styly'] ?? []), true) ? 'checked' : '' ?>> <?= h($s) ?></label><?php endforeach; ?></div></fieldset>
<label>Pár viet o jogovni <textarea name="popis" rows="4" maxlength="1500"><?= h($_POST['popis'] ?? '') ?></textarea></label>
<label class="prepinac"><input type="checkbox" name="suhlas" value="1" required> Súhlasím so zverejnením profilu jogovne na najdijogu.sk a so spracovaním kontaktných údajov na účely registrácie.</label>
<button class="btn primar">Prihlásiť jogovňu (zadarmo)</button>
</form>
<aside class="karta"><h2>Čo získate</h2><ul><li><b>Zadarmo:</b> profil jogovne v zozname podľa okolia, hodnotenie z Google, odkaz na web a rezervácie.</li><li><b>Partner 12 (24 €/mes., 288 €/rok):</b> zvýraznenie, služby a cenník, akcie, 4 AI články mesačne pod vašou hlavičkou, štatistiky zobrazení a klikov, recenzie z Google, 20 účtov Plus pre klientov.</li><li><b>Partner Pro (49 €/mes.):</b> + vlastná sekvencia v appke, top pozícia v meste, videá na siete.</li></ul><p class="drobne">Bez viazanosti po uplynutí 12 mesiacov — profil ostáva ako bezplatný listing. Ceny bez DPH.</p></aside>
</div>
<?php endif; ?>

<?php else: ?><h1>Nenašli sme</h1><p>Táto jogovňa alebo článok tu nie je. <a href="/jogovne/">Zoznam jogovní</a></p><?php endif; ?>
</main>
<footer class="pata">© <?= date('Y') ?> najdijogu.sk · Slovensko · Česko · <a href="/jogovne/pridat">Pre jogovne</a> · <a href="mailto:info@najdijogu.sk">info@najdijogu.sk</a></footer>
<script src="/jogovne/jogovne.js"></script>
</body></html>
