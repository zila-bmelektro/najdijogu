<?php
// najdijogu.sk — CRON na WebSupport (panel, každých 5 min, PHP 8.5): nasadí najnovší `main` z GitHubu do docrootu.
// Umiestnenie na serveri: ~/najdijogu.sk/sub/deploy.php (mimo docrootu). Repo je verejné, žiadne heslo.
// Log: ~/najdijogu.sk/sub/deploy.log (posledných 200 riadkov). Výstup len pri chybe (cron e-mail „iba ak nie je prázdny").
$web = dirname(__DIR__) . '/web';
$log = __DIR__ . '/deploy.log';
$o = []; $rc = -1; $chyba = '';
if (!function_exists('exec')) $chyba = 'exec() je vypnuté (disable_functions=' . ini_get('disable_functions') . ')';
elseif (!is_dir($web . '/.git')) $chyba = 'chýba ' . $web . '/.git';
else {
    putenv('PATH=/usr/local/bin:/usr/bin:/bin'); putenv('HOME=' . dirname(__DIR__));
    $v = @exec('cd ' . escapeshellarg($web) . ' && /usr/bin/git rev-parse --short HEAD && /usr/bin/git fetch -q origin main 2>&1 && /usr/bin/git reset -q --hard origin/main 2>&1 && /usr/bin/git rev-parse --short HEAD', $o, $rc);
    if ($v === false) { $e = error_get_last(); $chyba = 'exec zlyhal: ' . ($e['message'] ?? '?') . ' sapi=' . PHP_SAPI . ' php=' . PHP_VERSION; }
}
$r = date('Y-m-d H:i:s') . ' rc=' . $rc . ' ' . implode(' | ', $o) . ($chyba ? ' !! ' . $chyba : '') . PHP_EOL;
$stare = file_exists($log) ? array_slice(file($log), -199) : [];
file_put_contents($log, implode('', $stare) . $r);
if ($rc !== 0) echo $r;
