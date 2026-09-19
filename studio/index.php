<?php
/* studio.najdijogu.sk — kokpit partnera (jogovne) + admin. Jeden router, obrazovky v obrazovky/*.php */
require __DIR__ . '/lib/jadro.php';
nj_session();
$p = preg_replace('/[^a-z_]/', '', $_GET['p'] ?? 'prehlad');
$ja = nj_ja();
if ($p === 'odhlasenie') { nj_audit('odhlasenie'); session_destroy(); header('Location: ' . NJ_STUDIO . '/'); exit; }
if ($p === 'prihlasenie' || !$ja) { $p = 'prihlasenie'; }
$partner = $ja ? nj_moj_partner() : null;
$admin = nj_je_admin($ja);
if ($ja && $p !== 'prihlasenie' && !$partner && !$admin) $p = 'bez_partnera';
if (str_starts_with($p, 'admin') && !$admin) { http_response_code(403); $p = 'prehlad'; }
$obrazovky = ['prihlasenie', 'bez_partnera', 'prehlad', 'profil', 'sluzby', 'clanky', 'clanok', 'statistiky', 'predplatne', 'google', 'admin', 'admin_partner', 'admin_clanky', 'admin_aktivity'];
if (!in_array($p, $obrazovky, true)) $p = 'prehlad';
$titul = ['prehlad' => 'Prehľad', 'profil' => 'Profil jogovne', 'sluzby' => 'Služby, ceny, akcie', 'clanky' => 'Články', 'clanok' => 'Článok', 'statistiky' => 'Štatistiky', 'predplatne' => 'Predplatné', 'google' => 'Google profil', 'admin' => 'Admin — partneri', 'admin_partner' => 'Admin — partner', 'admin_clanky' => 'Admin — články', 'admin_aktivity' => 'Admin — aktivity', 'prihlasenie' => 'Prihlásenie', 'bez_partnera' => 'Účet bez jogovne'][$p];
$sprava = $_SESSION['sprava'] ?? null; unset($_SESSION['sprava']);
function nj_sprava(string $s, string $typ = 'ok'): void { $_SESSION['sprava'] = ['t' => $s, 'typ' => $typ]; }
function nj_presmeruj(string $kam): void { header('Location: ' . NJ_STUDIO . '/?p=' . $kam); exit; }
?><!DOCTYPE html>
<html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titul) ?> · Studio najdijogu</title><meta name="robots" content="noindex">
<link rel="icon" href="https://najdijogu.sk/ikona.svg"><link rel="stylesheet" href="/studio.css">
</head><body class="<?= $ja ? 'prihlaseny' : '' ?>">
<?php if ($ja && $p !== 'prihlasenie'): ?>
<header class="hl">
  <a class="logo" href="/"><svg viewBox="0 0 112 112" aria-hidden="true"><path d="M56 6c-24 0-40 17-40 39 0 28 40 60 40 60s40-32 40-60c0-22-16-39-40-39z" fill="#1f6f5a"/><g fill="none" stroke="#f3efe4" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"><circle cx="56" cy="22" r="6.5" fill="#f3efe4" stroke="none"/><path d="M56 31v30M56 40l-12-13M56 40l12-13M56 61v24M56 61l11 8-11 7"/></g></svg>najdi<b>jogu</b> <span>studio</span></a>
  <nav>
    <?php if ($partner): ?><a href="/?p=prehlad" class="<?= $p==='prehlad'?'akt':'' ?>">Prehľad</a><a href="/?p=profil" class="<?= $p==='profil'?'akt':'' ?>">Profil</a><a href="/?p=sluzby" class="<?= $p==='sluzby'?'akt':'' ?>">Služby</a><a href="/?p=clanky" class="<?= in_array($p,['clanky','clanok'])?'akt':'' ?>">Články</a><a href="/?p=statistiky" class="<?= $p==='statistiky'?'akt':'' ?>">Štatistiky</a><a href="/?p=google" class="<?= $p==='google'?'akt':'' ?>">Google</a><a href="/?p=predplatne" class="<?= $p==='predplatne'?'akt':'' ?>">Predplatné</a><?php endif; ?>
    <?php if ($admin): ?><a href="/?p=admin" class="adm <?= str_starts_with($p,'admin')?'akt':'' ?>">Admin</a><?php endif; ?>
  </nav>
  <div class="ja"><?php if ($partner): ?><a href="<?= NJ_WEB ?>/jogovne/<?= h($partner['slug']) ?>" target="_blank" title="verejný profil">↗ <?= h($partner['nazov']) ?></a><?php endif; ?><span title="<?= h($ja['email']) ?>"><?= h($ja['meno'] ?: $ja['email']) ?></span><a href="/?p=odhlasenie">Odhlásiť</a></div>
</header>
<?php endif; ?>
<main class="obsah">
<?php if ($sprava): ?><div class="sprava <?= h($sprava['typ']) ?>"><?= h($sprava['t']) ?></div><?php endif; ?>
<?php require __DIR__ . "/obrazovky/$p.php"; ?>
</main>
<footer class="pata">Studio najdijogu.sk · <a href="https://najdijogu.sk/pre-jogovne">pre jogovne</a> · <a href="mailto:info@najdijogu.sk">info@najdijogu.sk</a></footer>
<script src="/studio.js"></script>
</body></html>
