<?php
/* GoPay platobná brána (Payments REST API, https://doc.gopay.com/). Vzor: energie.bmelektro.sk/app/lib_gopay.php.
   Tajomstvá: data/gopay_goid.txt, data/gopay_client_id.txt, data/gopay_client_secret.txt; data/gopay_env.txt = sandbox|production.
   Stav platby NIKDY neberieme z URL — vždy sa pýtame GoPay API (notifikácia aj návrat). Aktivácia je idempotentná. */
require_once __DIR__ . '/predplatne.php';

function nj_gopay_zapnute(): bool { return nj_tajomstvo('gopay_goid') && nj_tajomstvo('gopay_client_id') && nj_tajomstvo('gopay_client_secret'); }
function nj_gopay_ostra(): bool { return nj_tajomstvo('gopay_env') === 'production'; }
function nj_gopay_base(): string { return nj_gopay_ostra() ? 'https://gate.gopay.cz/api' : 'https://gw.sandbox.gopay.com/api'; }

function nj_gopay_token(): ?string {
    static $tok = null; if ($tok !== null) return $tok ?: null;
    $ch = curl_init(nj_gopay_base() . '/oauth2/token');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials', 'scope' => 'payment-all']),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode(nj_tajomstvo('gopay_client_id') . ':' . nj_tajomstvo('gopay_client_secret'))]]);
    $d = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $tok = (string)($d['access_token'] ?? '');
    return $tok ?: null;
}
function nj_gopay_api(string $metoda, string $cesta, ?array $json = null): array {
    $tok = nj_gopay_token(); if (!$tok) return ['error' => 'token'];
    $ch = curl_init(nj_gopay_base() . $cesta); $hl = ['Accept: application/json', 'Authorization: Bearer ' . $tok];
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25];
    if ($metoda === 'POST') { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE); $hl[] = 'Content-Type: application/json'; }
    $opt[CURLOPT_HTTPHEADER] = $hl; curl_setopt_array($ch, $opt);
    $d = json_decode((string)curl_exec($ch), true); curl_close($ch);
    return is_array($d) ? $d : ['error' => 'odpoved'];
}
/* Vytvorí objednávku + platbu. Vráti gw_url (presmerovanie na bránu) alebo null. */
function nj_gopay_zaplat(array $partner, string $balik): ?string {
    $cena = NJ_BALIKY[$balik]['cena']; $centy = $cena * 100;
    $obj = ['id' => 'o' . date('ymd') . substr(bin2hex(random_bytes(3)), 0, 4), 'partner' => $partner['id'], 'balik' => $balik, 'suma' => $cena, 'stav' => 'caka_gopay', 'sposob' => 'gopay', 'vytvorene' => date('c')];
    $nazov = 'najdijogu.sk - ' . NJ_BALIKY[$balik]['nazov'] . ' (12 mesiacov)';
    $d = nj_gopay_api('POST', '/payments/payment', [
        'payer' => ['contact' => ['email' => $partner['email']]],
        'target' => ['type' => 'ACCOUNT', 'goid' => (int)nj_tajomstvo('gopay_goid')],
        'amount' => $centy, 'currency' => 'EUR', 'order_number' => $obj['id'], 'order_description' => $nazov,
        'items' => [['name' => $nazov, 'amount' => $centy, 'count' => 1]],
        'callback' => ['return_url' => NJ_STUDIO . '/?p=predplatne&gopay=' . $obj['id'], 'notification_url' => NJ_STUDIO . '/api/gopay-notify.php'],
        'lang' => 'SK']);
    if (empty($d['id']) || empty($d['gw_url'])) { nj_audit('gopay_chyba', ['partner' => $partner['id'], 'detail' => mb_substr(json_encode($d['errors'] ?? $d, JSON_UNESCAPED_UNICODE), 0, 300)]); return null; }
    $obj['gopay_id'] = (string)$d['id'];
    nj_uprav('objednavky', fn($o) => $o + [$obj['id'] => $obj]);
    nj_audit('gopay_platba', ['partner' => $partner['id'], 'detail' => "$balik {$cena} € · {$obj['gopay_id']}" . (nj_gopay_ostra() ? '' : ' (sandbox)')]);
    return (string)$d['gw_url'];
}
/* Overí stav v GoPay a pri PAID idempotentne aktivuje balík. Vráti stav (PAID/CANCELED/…) alebo null. */
function nj_gopay_over(string $gopayId): ?string {
    if (!ctype_digit($gopayId)) return null;
    $d = nj_gopay_api('GET', '/payments/payment/' . $gopayId); $stav = $d['state'] ?? null; if (!$stav) return null;
    $obj = null; foreach (nj_citaj('objednavky', []) as $o) if (($o['gopay_id'] ?? '') === $gopayId) { $obj = $o; break; }
    if (!$obj) return $stav;
    if ($stav === 'PAID' && $obj['stav'] !== 'zaplatene') {
        $prvy = false;
        nj_uprav('objednavky', function ($all) use ($obj, &$prvy) { if (($all[$obj['id']]['stav'] ?? '') !== 'zaplatene') { $all[$obj['id']]['stav'] = 'zaplatene'; $all[$obj['id']]['zaplatene'] = date('c'); $prvy = true; } return $all; });
        if ($prvy) {
            nj_aktivuj_balik($obj['partner'], $obj['balik'], 'gopay:' . $gopayId);
            $p = nj_partner($obj['partner']);
            nj_email(NJ_ADMINI[0], 'najdijogu: GoPay úhrada — ' . ($p['nazov'] ?? '?'), '<p>' . h($p['nazov'] ?? '?') . ' zaplatil ' . NJ_BALIKY[$obj['balik']]['nazov'] . ' (' . (int)$obj['suma'] . ' €) cez GoPay, platba ' . $gopayId . (nj_gopay_ostra() ? '' : ' <b>(sandbox — test)</b>') . '. Vystaviť faktúru.</p>');
        }
    } elseif (in_array($stav, ['CANCELED', 'TIMEOUTED'], true) && $obj['stav'] === 'caka_gopay') {
        nj_uprav('objednavky', function ($all) use ($obj, $stav) { $all[$obj['id']]['stav'] = $stav === 'CANCELED' ? 'zrusene' : 'vyprsane'; return $all; });
    }
    return $stav;
}
