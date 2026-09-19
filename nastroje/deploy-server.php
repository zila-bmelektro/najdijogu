<?php
// najdijogu.sk — CRON na WebSupport (panel, každých 5 min, „Spustenie PHP 8.5 súboru"): nasadí najnovší `main` z GitHubu do docrootu.
// Umiestnenie na serveri: ~/najdijogu.sk/sub/deploy.php (mimo docrootu). Repo je verejné, žiadne heslo.
// CRON sandbox WebSupportu nedovolí spúšťať procesy (exec → „Unable to fork"), preto BEZ gitu:
//   1. GitHub API: sha posledného commitu na main; ak je rovnaké ako v sub/deploy-stav.json → koniec (1 malý request).
//   2. Stiahne zip archív vetvy, rozbalí do sub/tmp/, prekopíruje do web/ a zmaže súbory, ktoré v repe už nie sú
//      (chránené: obrazky/ (nahrané fotky), .git/, .htaccess-local).
// Log: ~/najdijogu.sk/sub/deploy.log (posledných 200 riadkov). Výstup len pri chybe (cron e-mail „iba ak nie je prázdny").
date_default_timezone_set('Europe/Bratislava');
$repo = 'zila-bmelektro/najdijogu'; $vetva = 'main';
$web = dirname(__DIR__) . '/web'; $sub = __DIR__; $log = $sub . '/deploy.log'; $stavF = $sub . '/deploy-stav.json';
$chranene = ['obrazky', '.git', '.htaccess-local'];

function stiahni(string $url): string|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'najdijogu-deploy', CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json']]);
    $r = curl_exec($ch); $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($r !== false && $kod === 200) ? $r : false;
}
function rmr(string $d): void { foreach (array_diff(scandir($d), ['.', '..']) as $f) { $p = "$d/$f"; is_dir($p) && !is_link($p) ? rmr($p) : unlink($p); } rmdir($d); }
function zapisLog(string $log, string $r): void { $stare = file_exists($log) ? array_slice(file($log), -199) : []; file_put_contents($log, implode('', $stare) . $r); }

$stav = is_file($stavF) ? json_decode((string)file_get_contents($stavF), true) : [];
$j = stiahni("https://api.github.com/repos/$repo/commits/$vetva");
$info = $j ? json_decode($j, true) : null; $sha = $info['sha'] ?? '';
if (!$sha) { $r = date('Y-m-d H:i:s') . " chyba: GitHub API nedostupné\n"; zapisLog($log, $r); echo $r; exit(1); }
if (($stav['sha'] ?? '') === $sha) exit(0);  // nič nové – ticho

$zip = stiahni("https://github.com/$repo/archive/refs/heads/$vetva.zip");
if (!$zip) { $r = date('Y-m-d H:i:s') . " chyba: zip archív sa nestiahol\n"; zapisLog($log, $r); echo $r; exit(1); }
$tmp = $sub . '/tmp'; if (is_dir($tmp)) rmr($tmp); mkdir($tmp, 0700, true);
file_put_contents("$tmp/repo.zip", $zip);
$za = new ZipArchive(); if ($za->open("$tmp/repo.zip") !== true) { $r = date('Y-m-d H:i:s') . " chyba: zip sa neotvoril\n"; zapisLog($log, $r); echo $r; exit(1); }
$za->extractTo($tmp); $za->close(); unlink("$tmp/repo.zip");
$koren = glob("$tmp/*", GLOB_ONLYDIR)[0] ?? null;
if (!$koren) { $r = date('Y-m-d H:i:s') . " chyba: prázdny archív\n"; zapisLog($log, $r); echo $r; exit(1); }

// 1) skopíruj všetko z archívu do web/
$nove = []; $n = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($koren, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $p) {
    $rel = substr($p->getPathname(), strlen($koren) + 1); $ciel = "$web/$rel";
    if ($p->isDir()) { if (!is_dir($ciel)) mkdir($ciel, 0755, true); continue; }
    $nove[$rel] = true;
    if (!is_file($ciel) || md5_file($ciel) !== md5_file($p->getPathname())) { copy($p->getPathname(), $ciel); $n++; }
}
// 2) zmaž, čo v repe už nie je (okrem chránených)
$z = 0;
$it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($web, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it2 as $p) {
    $rel = substr($p->getPathname(), strlen($web) + 1); $prvy = explode('/', $rel)[0];
    if (in_array($prvy, $chranene, true)) continue;
    if ($p->isDir()) { if (!glob($p->getPathname() . '/*')) rmdir($p->getPathname()); continue; }
    if (empty($nove[$rel])) { unlink($p->getPathname()); $z++; }
}
rmr($tmp);
file_put_contents($stavF, json_encode(['sha' => $sha, 'kedy' => date('c'), 'sprava' => mb_substr($info['commit']['message'] ?? '', 0, 80)], JSON_UNESCAPED_UNICODE));
zapisLog($log, date('Y-m-d H:i:s') . ' ok ' . substr($sha, 0, 7) . " zmenené=$n zmazané=$z | " . strtok($info['commit']['message'] ?? '', "\n") . PHP_EOL);
