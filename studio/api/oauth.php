<?php
/* Google prihlásenie (OAuth 2.0 authorization code). GET bez code → presmeruje na Google; GET s code → overí, založí session.
   Scope len openid email profile (bez overovania appky u Googlu). */
require __DIR__ . '/../lib/jadro.php';
nj_session();
$cid = nj_tajomstvo('google_oauth_client_id'); $sec = nj_tajomstvo('google_oauth_client_secret');
$redirect = NJ_STUDIO . '/api/oauth.php';
if (!$cid || !$sec) { http_response_code(503); exit('Google prihlásenie ešte nie je nastavené (chýba OAuth klient).'); }

if (empty($_GET['code'])) {
    nj_rate_limit('oauth', 20, 600);
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    $_SESSION['oauth_spat'] = $_GET['spat'] ?? '/';
    $q = http_build_query(['client_id' => $cid, 'redirect_uri' => $redirect, 'response_type' => 'code', 'scope' => 'openid email profile', 'state' => $_SESSION['oauth_state'], 'prompt' => 'select_account']);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $q); exit;
}
if (($_GET['state'] ?? '') !== ($_SESSION['oauth_state'] ?? 'x')) { http_response_code(400); exit('Neplatný stav prihlásenia, skús znova.'); }
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
    CURLOPT_POSTFIELDS => http_build_query(['code' => $_GET['code'], 'client_id' => $cid, 'client_secret' => $sec, 'redirect_uri' => $redirect, 'grant_type' => 'authorization_code'])]);
$tok = json_decode((string)curl_exec($ch), true); curl_close($ch);
if (empty($tok['id_token'])) { http_response_code(400); exit('Google neposlal token. ' . h($tok['error_description'] ?? '')); }
$casti = explode('.', $tok['id_token']);
$info = json_decode(base64_decode(strtr($casti[1] ?? '', '-_', '+/')), true) ?: [];
// overenie id_tokenu cez tokeninfo (jednoduché a spoľahlivé bez knižníc)
$over = json_decode((string)file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($tok['id_token'])), true) ?: [];
if (($over['aud'] ?? '') !== $cid || empty($over['email']) || ($over['email_verified'] ?? 'false') !== 'true') { http_response_code(400); exit('Prihlásenie sa nepodarilo overiť.'); }
session_regenerate_id(true);
$_SESSION['ja'] = ['email' => mb_strtolower($over['email']), 'meno' => $over['name'] ?? ($info['name'] ?? ''), 'obrazok' => $over['picture'] ?? '', 'cas' => date('c')];
unset($_SESSION['oauth_state']);
// registrácia čakajúca na tento e-mail? (partner vyplnil formulár a klikol v e-maile)
if (!empty($_SESSION['reg_partner'])) {
    $p = nj_partner($_SESSION['reg_partner']);
    if ($p && !in_array($_SESSION['ja']['email'], $p['google_emaily'] ?? [], true)) { $p['google_emaily'][] = $_SESSION['ja']['email']; if ($p['stav'] === 'nova') $p['stav'] = 'registrovana'; nj_partner_uloz($p); }
    unset($_SESSION['reg_partner']);
}
nj_audit('prihlasenie', ['partner' => nj_moj_partner()['id'] ?? '']);
$spat = $_SESSION['oauth_spat'] ?? '/'; unset($_SESSION['oauth_spat']);
header('Location: ' . NJ_STUDIO . (str_starts_with($spat, '/') ? $spat : '/')); exit;
