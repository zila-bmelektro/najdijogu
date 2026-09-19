<?php
/* AI koncept článku (Anthropic Messages API, kľúč data/anthropic_api.txt). Model: claude-sonnet (rýchly, lacný pre 700 slov).
   Vráti ['nadpis','perex','html','meta'] alebo null. Volá sa synchrónne z kokpitu (10–25 s) — partner vidí spinner. */
require_once __DIR__ . '/jadro.php';

function nj_ai_koncept(array $partner, array $clanok): ?array {
    $key = nj_tajomstvo('anthropic_api'); if (!$key) return null;
    $fakty = "Jogovňa: {$partner['nazov']}, {$partner['mesto']}. Štýly: " . implode(', ', $partner['styly'] ?: ['joga']) . ". Popis: " . mb_substr($partner['popis'], 0, 800)
        . ". Služby: " . implode('; ', array_map(fn($s) => $s['nazov'] . ($s['cena'] ? " ({$s['cena']})" : ''), array_slice($partner['sluzby'], 0, 8)));
    $system = "Si redaktor portálu najdijogu.sk (joga na Slovensku a v Česku). Píšeš po slovensky, prirodzene, bez fráz a bez marketingového balastu, v tóne skúseného učiteľa jogy, ktorý hovorí s bežným človekom. Článok je publikovaný pod hlavičkou jogovne (autor = jogovňa), preto píš v 1. osobe množného čísla („u nás v jogovni…“) len tam, kde to dáva zmysel, inak vecne. Nevymýšľaj fakty o jogovni, ktoré nie sú v podkladoch; ak niečo nevieš, vynechaj. Žiadne zdravotné sľuby. Dĺžka 600–900 slov. Štruktúra: úvod (2–3 vety, prečo to čitateľa zaujíma), 3–5 sekcií s nadpismi h2, praktické rady, záver s pozvaním do jogovne (1–2 vety, bez nátlaku). Výstup VÝHRADNE ako JSON: {\"nadpis\": \"…\", \"perex\": \"1–2 vety\", \"html\": \"<p>…</p><h2>…</h2>…\", \"meta\": \"SEO popis do 155 znakov\"}.";
    $user = "Námet od jogovne: " . mb_substr($clanok['namet'], 0, 600) . "\n\nPodklady o jogovni: $fakty" . (!empty($clanok['poznamka']) ? "\n\nPoznámka partnera: " . mb_substr($clanok['poznamka'], 0, 600) : '');
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode(['model' => 'claude-sonnet-4-5', 'max_tokens' => 3000, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]]])]);
    $r = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $txt = $r['content'][0]['text'] ?? '';
    if (!$txt) { nj_audit('ai_chyba', ['detail' => mb_substr(json_encode($r['error'] ?? $r), 0, 300)]); return null; }
    if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
    $j = json_decode($txt, true);
    if (!$j || empty($j['html']) || empty($j['nadpis'])) return null;
    // povolené značky — zvyšok preč
    $j['html'] = strip_tags($j['html'], '<p><h2><h3><ul><ol><li><strong><em><br><blockquote>');
    return ['nadpis' => mb_substr(strip_tags($j['nadpis']), 0, 120), 'perex' => mb_substr(strip_tags($j['perex'] ?? ''), 0, 300), 'html' => $j['html'], 'meta' => mb_substr(strip_tags($j['meta'] ?? ''), 0, 160)];
}
function nj_ai_namety(array $partner): array {
    $key = nj_tajomstvo('anthropic_api'); if (!$key) return [];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode(['model' => 'claude-sonnet-4-5', 'max_tokens' => 600, 'messages' => [['role' => 'user', 'content' => "Navrhni 5 námetov na články pre jogovňu {$partner['nazov']} ({$partner['mesto']}, štýly: " . implode(', ', $partner['styly'] ?: ['joga']) . "). Cieľ: prilákať začiatočníkov a ľudí z okolia, SEO na slovenské vyhľadávanie. Každý námet = 1 riadok, max 12 slov, po slovensky, bez číslovania a bez úvodu."]]])]);
    $r = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $riadky = array_filter(array_map('trim', explode("\n", $r['content'][0]['text'] ?? '')));
    return array_slice(array_map(fn($x) => ltrim($x, "-•*0123456789. "), $riadky), 0, 5);
}
