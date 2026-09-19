<?php
/* najdijogu.sk — M-PARTNERI jadro: konfigurácia, dáta (flat-file JSON), session, CSRF, audit, štatistiky.
   Dáta žijú MIMO docrootu: ~/najdijogu.sk/data/ (partneri.json, clanky/, stat/, audit.log, cache/).
   Tajomstvá: data/*.txt (google_oauth_client_id, google_oauth_client_secret, google_places_key, stripe_*). Nikdy v gite. */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Bratislava');

const NJ_DOMENA   = 'najdijogu.sk';
const NJ_STUDIO   = 'https://studio.najdijogu.sk';
const NJ_WEB      = 'https://najdijogu.sk';
const NJ_ADMINI   = ['zila@bmelektro.sk', 'info@bmelektro.sk'];
const NJ_BALIKY   = ['listing' => ['nazov' => 'Listing', 'cena' => 0], 'partner12' => ['nazov' => 'Partner 12', 'cena' => 288], 'pro' => ['nazov' => 'Partner Pro', 'cena' => 588]];
const NJ_STYLY    = ['Ashtanga', 'Vinyasa', 'Hatha', 'Yin', 'Power', 'Iyengar', 'Kundalini', 'Hot joga', 'Jóga pre tehotné', 'Detská joga', 'Meditácia', 'Pránajáma'];

function nj_data_dir(): string {
    static $d = null;
    if ($d === null) {
        // web/studio/lib → web/studio → web → najdijogu.sk → data
        $d = dirname(__DIR__, 3) . '/data';
        if (!is_dir($d)) { mkdir($d, 0700, true); }
        foreach (['clanky', 'stat', 'cache', 'obrazky'] as $p) { if (!is_dir("$d/$p")) mkdir("$d/$p", 0700, true); }
    }
    return $d;
}
function nj_tajomstvo(string $nazov): string {
    $f = nj_data_dir() . "/$nazov.txt";
    return is_file($f) ? trim((string)file_get_contents($f)) : '';
}
/* ---------- JSON úložisko so zámkom ---------- */
function nj_citaj(string $nazov, $default = []) {
    $f = nj_data_dir() . "/$nazov.json";
    if (!is_file($f)) return $default;
    $j = json_decode((string)file_get_contents($f), true);
    return is_array($j) ? $j : $default;
}
function nj_zapis(string $nazov, $data): void {
    $f = nj_data_dir() . "/$nazov.json";
    $tmp = $f . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $f);
    chmod($f, 0600);
}
function nj_uprav(string $nazov, callable $fn, $default = []) {
    $f = nj_data_dir() . "/$nazov.lock";
    $h = fopen($f, 'c'); flock($h, LOCK_EX);
    $d = nj_citaj($nazov, $default); $d = $fn($d); nj_zapis($nazov, $d);
    flock($h, LOCK_UN); fclose($h);
    return $d;
}
/* ---------- partneri ---------- */
function nj_partneri(): array { return nj_citaj('partneri', []); }
function nj_partner(string $id): ?array { $p = nj_partneri(); return $p[$id] ?? null; }
function nj_partner_podla_emailu(string $email): ?array {
    foreach (nj_partneri() as $p) if (mb_strtolower($p['email'] ?? '') === mb_strtolower($email) || in_array(mb_strtolower($email), array_map('mb_strtolower', $p['google_emaily'] ?? []), true)) return $p;
    return null;
}
function nj_partner_podla_slugu(string $slug): ?array { foreach (nj_partneri() as $p) if (($p['slug'] ?? '') === $slug) return $p; return null; }
function nj_slug(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á'=>'a','ä'=>'a','č'=>'c','ď'=>'d','é'=>'e','í'=>'i','ĺ'=>'l','ľ'=>'l','ň'=>'n','ó'=>'o','ô'=>'o','ŕ'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ý'=>'y','ž'=>'z','ř'=>'r','ě'=>'e','ů'=>'u']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'jogovna';
}
function nj_partner_uloz(array $p): array {
    return nj_uprav('partneri', function ($all) use ($p) { $p['upravene'] = date('c'); $all[$p['id']] = $p; return $all; })[$p['id']];
}
function nj_novy_partner(array $z): array {
    $id = 'p' . substr(bin2hex(random_bytes(6)), 0, 10);
    $slug = nj_slug($z['nazov']); $i = 1; $base = $slug;
    while (nj_partner_podla_slugu($slug)) $slug = $base . '-' . (++$i);
    $p = [
        'id' => $id, 'slug' => $slug, 'nazov' => $z['nazov'], 'email' => mb_strtolower($z['email']), 'google_emaily' => [],
        'telefon' => $z['telefon'] ?? '', 'web' => $z['web'] ?? '', 'mesto' => $z['mesto'] ?? '', 'adresa' => $z['adresa'] ?? '',
        'lat' => null, 'lng' => null, 'popis' => $z['popis'] ?? '', 'styly' => $z['styly'] ?? [], 'rezervacie_url' => '',
        'hodiny' => '', 'sluzby' => [], 'akcie' => [], 'fotky' => [], 'google_place_id' => '', 'google_rating' => null, 'google_pocet' => null,
        'stav' => 'nova', 'balik' => 'listing', 'plati_do' => null, 'neobnovovat' => false, 'vytvorene' => date('c'), 'upravene' => date('c'),
        'schvalene' => false, 'poznamka_admin' => '',
    ];
    nj_uprav('partneri', function ($all) use ($p) { $all[$p['id']] = $p; return $all; });
    return $p;
}
function nj_partner_aktivny(array $p): bool {
    return ($p['balik'] ?? 'listing') !== 'listing' && !empty($p['plati_do']) && strtotime($p['plati_do']) >= time();
}
/* ---------- session, auth ---------- */
function nj_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    session_name('njstudio'); session_start();
}
function nj_ja(): ?array { nj_session(); return $_SESSION['ja'] ?? null; }
function nj_je_admin(?array $ja = null): bool { $ja = $ja ?? nj_ja(); return $ja && in_array(mb_strtolower($ja['email']), NJ_ADMINI, true); }
function nj_vyzaduj_login(): array {
    $ja = nj_ja(); if ($ja) return $ja;
    header('Location: ' . NJ_STUDIO . '/?p=prihlasenie&spat=' . urlencode($_SERVER['REQUEST_URI'] ?? '/')); exit;
}
function nj_moj_partner(): ?array {
    $ja = nj_ja(); if (!$ja) return null;
    if (!empty($_SESSION['partner_id']) && nj_je_admin($ja)) return nj_partner($_SESSION['partner_id']); // admin „pozerá ako“
    return nj_partner_podla_emailu($ja['email']);
}
function nj_csrf(): string { nj_session(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function nj_over_csrf(): void { nj_session(); if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? 'x')) { http_response_code(403); exit('Neplatný formulár (CSRF). Obnov stránku a skús znova.'); } }
/* ---------- rate limit (login, formuláre) ---------- */
function nj_rate_limit(string $kluc, int $max, int $sekund): void {
    $f = nj_data_dir() . '/cache/rl-' . md5($kluc . ($_SERVER['REMOTE_ADDR'] ?? '')) . '.json';
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    $d = array_values(array_filter($d ?: [], fn($t) => $t > time() - $sekund));
    if (count($d) >= $max) { http_response_code(429); exit('Príliš veľa pokusov, skús o chvíľu.'); }
    $d[] = time(); file_put_contents($f, json_encode($d));
}
/* ---------- audit aktivít ---------- */
function nj_audit(string $akcia, array $detail = []): void {
    $ja = nj_ja();
    $r = ['t' => date('c'), 'kto' => $ja['email'] ?? 'anonym', 'ip' => substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'nj'), 0, 12), 'akcia' => $akcia] + $detail;
    file_put_contents(nj_data_dir() . '/audit.log', json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}
function nj_audit_citaj(int $n = 200, ?string $partner = null): array {
    $f = nj_data_dir() . '/audit.log'; if (!is_file($f)) return [];
    $r = array_reverse(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $out = [];
    foreach ($r as $l) { $j = json_decode($l, true); if (!$j) continue; if ($partner && ($j['partner'] ?? '') !== $partner) continue; $out[] = $j; if (count($out) >= $n) break; }
    return $out;
}
/* ---------- štatistiky (M-STATISTIKY): denné počítadlá bez cookies ----------
   udalosti: zobrazenie_profilu, zobrazenie_v_zozname, klik_web, klik_telefon, klik_rezervacia, klik_mapa, clanok_zobrazenie, clanok_docitanie, clanok_klik_jogovna */
/* jednoduchý filter botov pre štatistiky (M-STATISTIKY §5) */
function nj_je_bot(): bool { return (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|curl|wget|python|headless/i', $_SERVER['HTTP_USER_AGENT'] ?? 'x'); }
function nj_stat_zapis(string $partnerId, string $udalost, string $objekt = ''): void {
    if (nj_je_bot()) return;
    $d = date('Y-m-d'); $f = nj_data_dir() . "/stat/$d.json";
    $h = fopen($f . '.lock', 'c'); flock($h, LOCK_EX);
    $j = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $k = $objekt ? "$udalost:$objekt" : $udalost;
    $j[$partnerId][$k] = ($j[$partnerId][$k] ?? 0) + 1;
    file_put_contents($f, json_encode($j)); flock($h, LOCK_UN); fclose($h);
}
function nj_stat_citaj(string $partnerId, int $dni = 30): array {
    $out = ['dni' => [], 'spolu' => []];
    for ($i = $dni - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day")); $f = nj_data_dir() . "/stat/$d.json";
        $j = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
        $den = $j[$partnerId] ?? [];
        $out['dni'][$d] = $den;
        foreach ($den as $k => $v) { $kk = explode(':', $k)[0]; $out['spolu'][$kk] = ($out['spolu'][$kk] ?? 0) + $v; $out['spolu_objekty'][$k] = ($out['spolu_objekty'][$k] ?? 0) + $v; }
    }
    return $out;
}
/* ---------- články ---------- */
function nj_clanky(?string $partnerId = null): array {
    $out = [];
    foreach (glob(nj_data_dir() . '/clanky/*.json') ?: [] as $f) { $c = json_decode((string)file_get_contents($f), true); if (!$c) continue; if ($partnerId && ($c['partner'] ?? '') !== $partnerId) continue; $out[$c['id']] = $c; }
    uasort($out, fn($a, $b) => strcmp($b['upravene'] ?? '', $a['upravene'] ?? ''));
    return $out;
}
function nj_clanok(string $id): ?array { $f = nj_data_dir() . '/clanky/' . preg_replace('/[^a-z0-9]/', '', $id) . '.json'; return is_file($f) ? json_decode((string)file_get_contents($f), true) : null; }
function nj_clanok_uloz(array $c): array { $c['upravene'] = date('c'); file_put_contents(nj_data_dir() . '/clanky/' . $c['id'] . '.json', json_encode($c, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); return $c; }
/* ---------- pomôcky ---------- */
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nj_datum(?string $iso): string { return $iso ? date('j.n.Y', strtotime($iso)) : '—'; }
function nj_vzdialenost(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371; $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}
function nj_email(string $komu, string $predmet, string $html): bool {
    $hl = "From: Najdi jogu <info@najdijogu.sk>\r\nReply-To: info@najdijogu.sk\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $telo = '<div style="font-family:Segoe UI,system-ui,sans-serif;max-width:600px;margin:0 auto;color:#1b2420"><div style="padding:18px 0;border-bottom:2px solid #1f6f5a"><b style="font-size:20px">najdi<span style="color:#1f6f5a">jogu</span>.sk</b></div><div style="padding:20px 0;line-height:1.55">' . $html . '</div><div style="border-top:1px solid #ddd;padding:14px 0;font-size:12px;color:#5d6963">najdijogu.sk · joga v tempe dychu · <a href="https://najdijogu.sk" style="color:#1f6f5a">najdijogu.sk</a> · <a href="mailto:info@najdijogu.sk" style="color:#1f6f5a">info@najdijogu.sk</a></div></div>';
    $predmetK = '=?UTF-8?B?' . base64_encode($predmet) . '?=';
    /* SMTP cez schránku info@najdijogu.sk (data/smtp.txt: host;port;user;heslo) — mail() na WebSupporte bez schránky nedoručí. Bez smtp.txt → mail(). */
    $smtp = nj_tajomstvo('smtp');
    if ($smtp) { $ok = nj_smtp_posli($smtp, $komu, $predmetK, $hl, $telo); nj_audit('email', ['komu' => $komu, 'predmet' => $predmet, 'ok' => $ok]); return $ok; }
    return @mail($komu, $predmetK, $telo, $hl);
}
/* minimálny SMTP klient (SSL 465 alebo STARTTLS 587), AUTH LOGIN; bez závislostí */
function nj_smtp_posli(string $konf, string $komu, string $predmet, string $hl, string $telo): bool {
    [$host, $port, $user, $heslo] = array_pad(explode(';', trim($konf), 4), 4, ''); $port = (int)$port ?: 465;
    $ssl = $port === 465; $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'SNI_enabled' => true]]);
    $f = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$f) return false;
    stream_set_timeout($f, 15);
    $cit = function () use ($f) { $r = ''; while (($l = fgets($f, 515)) !== false) { $r .= $l; if (!isset($l[3]) || $l[3] !== '-') break; } return $r; };
    $pis = function (string $c) use ($f, $cit) { fwrite($f, $c . "\r\n"); return $cit(); };
    $cit();
    $pis('EHLO najdijogu.sk');
    if (!$ssl) { if (substr($pis('STARTTLS'), 0, 3) !== '220' || !stream_socket_enable_crypto($f, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($f); return false; } $pis('EHLO najdijogu.sk'); }
    $pis('AUTH LOGIN'); $pis(base64_encode($user)); if (substr($pis(base64_encode($heslo)), 0, 3) !== '235') { fclose($f); return false; }
    $pis('MAIL FROM:<info@najdijogu.sk>'); if (substr($pis('RCPT TO:<' . $komu . '>'), 0, 3) !== '250') { fclose($f); return false; }
    $pis('DATA');
    $sprava = "To: $komu\r\nSubject: $predmet\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@najdijogu.sk>\r\n" . rtrim($hl) . "\r\n\r\n" . str_replace("\n.", "\n..", $telo);
    $r = $pis($sprava . "\r\n."); $pis('QUIT'); fclose($f);
    return substr($r, 0, 3) === '250';
}
