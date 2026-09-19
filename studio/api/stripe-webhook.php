<?php /* Stripe webhook: checkout.session.completed → aktivácia balíka. Podpis: data/stripe_webhook_secret.txt */
require __DIR__ . '/../lib/predplatne.php';
$telo = file_get_contents('php://input'); $sec = nj_tajomstvo('stripe_webhook_secret');
if ($sec) { $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''; preg_match('/t=(\d+)/', $sig, $t); preg_match('/v1=([a-f0-9]+)/', $sig, $v);
  if (!$t || !$v || !hash_equals(hash_hmac('sha256', $t[1] . '.' . $telo, $sec), $v[1]) || abs(time() - (int)$t[1]) > 300) { http_response_code(400); exit('bad signature'); } }
$e = json_decode($telo, true);
if (($e['type'] ?? '') === 'checkout.session.completed') { $s = $e['data']['object']; if (($s['payment_status'] ?? '') === 'paid' && !empty($s['metadata']['partner'])) nj_aktivuj_balik($s['metadata']['partner'], $s['metadata']['balik'] ?? 'partner12', 'stripe:' . $s['id']); }
echo 'ok';
