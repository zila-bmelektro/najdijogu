<?php /* CRON denne 06:10: expirácie + pripomienky predplatného, obnova Google ratingov, čistenie rate-limit cache. Chránené tokenom (data/cron_token.txt) alebo CLI. */
require __DIR__ . '/../lib/predplatne.php'; require __DIR__ . '/../lib/google.php';
if (PHP_SAPI !== 'cli' && ($_GET['t'] ?? '') !== nj_tajomstvo('cron_token')) { http_response_code(403); exit; }
$v = nj_predplatne_denne(); $g = nj_places_denne();
foreach (glob(nj_data_dir() . '/cache/rl-*.json') ?: [] as $f) if (filemtime($f) < time() - 86400) @unlink($f);
echo date('c') . ' predplatne: ' . implode(', ', $v) . " | google obnovene: $g\n";
