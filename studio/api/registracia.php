<?php /* odkaz z e-mailu: uloží partnera do session a pošle na Google prihlásenie */
require __DIR__ . '/../lib/jadro.php'; nj_session();
$t = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? ''); $tok = nj_citaj('reg_tokeny', []);
if (!$t || empty($tok[$t]) || $tok[$t]['do'] < time()) { http_response_code(410); exit('Odkaz je neplatný alebo vypršal. Napíšte nám na info@najdijogu.sk.'); }
$_SESSION['reg_partner'] = $tok[$t]['partner'];
nj_uprav('reg_tokeny', function ($x) use ($t) { unset($x[$t]); return $x; });
header('Location: ' . NJ_STUDIO . '/api/oauth.php?spat=/?p=profil'); exit;
