<?php require_once __DIR__ . '/../lib/predplatne.php';
$pp = nj_partner((string)($_GET['id'] ?? '')); if (!$pp) { nj_sprava('Partner sa nenašiel.', 'chyba'); nj_presmeruj('admin'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_over_csrf();
    if (isset($_POST['stav']) && in_array($_POST['stav'], ['nova', 'registrovana', 'schvalena', 'pozastavena'], true)) { $pp['stav'] = $_POST['stav']; $pp['schvalene'] = $pp['stav'] === 'schvalena'; nj_partner_uloz($pp); nj_audit('admin_stav', ['partner' => $pp['id'], 'detail' => $pp['stav']]); if ($pp['stav'] === 'schvalena') nj_email($pp['email'], 'najdijogu: vaša jogovňa je schválená', '<p>Jogovňa <b>' . h($pp['nazov']) . '</b> je na najdijogu.sk schválená a viditeľná: <a href="' . NJ_WEB . '/jogovne/' . $pp['slug'] . '">' . NJ_WEB . '/jogovne/' . $pp['slug'] . '</a></p><p>Spravovať ju môžete v <a href="' . NJ_STUDIO . '/">Studiu</a>.</p>'); nj_sprava('Stav zmenený.'); }
    if (isset($_POST['aktivuj']) && isset(NJ_BALIKY[$_POST['aktivuj']])) { nj_aktivuj_balik($pp['id'], $_POST['aktivuj'], 'admin:' . $ja['email']); nj_uprav('objednavky', function ($o) use ($pp) { foreach ($o as &$x) if ($x['partner'] === $pp['id'] && $x['stav'] === 'caka_prevod') $x['stav'] = 'zaplatene'; return $o; }); nj_sprava('Balík aktivovaný na 12 mesiacov.'); }
    if (isset($_POST['poznamka'])) { $pp['poznamka_admin'] = mb_substr((string)$_POST['poznamka'], 0, 1000); nj_partner_uloz($pp); nj_sprava('Poznámka uložená.'); }
    if (isset($_POST['pridaj_email'])) { $e = mb_strtolower(trim((string)$_POST['pridaj_email'])); if (filter_var($e, FILTER_VALIDATE_EMAIL)) { $pp['google_emaily'][] = $e; $pp['google_emaily'] = array_values(array_unique($pp['google_emaily'])); nj_partner_uloz($pp); nj_audit('admin_email_priradenie', ['partner' => $pp['id'], 'detail' => $e]); nj_sprava('E-mail priradený.'); } }
    if (isset($_POST['pozerat_ako'])) { $_SESSION['partner_id'] = $pp['id']; nj_presmeruj('prehlad'); }
    header('Location: ' . NJ_STUDIO . '/?p=admin_partner&id=' . $pp['id']); exit;
}
$st = nj_stat_citaj($pp['id'], 30)['spolu']; $cl = nj_clanky($pp['id']);
?>
<p><a href="/?p=admin">‹ Partneri</a></p>
<h1><?= h($pp['nazov']) ?> <small class="stav stav-<?= h($pp['stav']) ?>"><?= h($pp['stav']) ?></small></h1>
<div class="dlazdice"><div class="dl"><b><?= h(NJ_BALIKY[$pp['balik']]['nazov']) ?></b><span>balík · platí do <?= nj_datum($pp['plati_do']) ?></span></div><div class="dl"><b><?= (int)($st['zobrazenie_profilu'] ?? 0) ?></b><span>zobrazení profilu 30 d</span></div><div class="dl"><b><?= count($cl) ?></b><span>článkov</span></div><div class="dl"><b><?= $pp['google_rating'] ? $pp['google_rating'] . ' ★' : '—' ?></b><span>Google</span></div></div>
<div class="karta"><table class="tab"><tr><td>E-mail</td><td><?= h($pp['email']) ?> · Google účty: <?= h(implode(', ', $pp['google_emaily']) ?: '—') ?></td></tr><tr><td>Kontakt</td><td><?= h($pp['telefon']) ?> · <?= h($pp['web']) ?></td></tr><tr><td>Adresa</td><td><?= h($pp['adresa']) ?>, <?= h($pp['mesto']) ?> · poloha <?= $pp['lat'] ? round($pp['lat'], 4) . ', ' . round($pp['lng'], 4) : 'chýba' ?></td></tr><tr><td>Verejný profil</td><td><a href="<?= NJ_WEB ?>/jogovne/<?= h($pp['slug']) ?>" target="_blank"><?= NJ_WEB ?>/jogovne/<?= h($pp['slug']) ?></a></td></tr><tr><td>Vytvorené</td><td><?= nj_datum($pp['vytvorene']) ?> · upravené <?= nj_datum($pp['upravene']) ?></td></tr></table></div>
<form method="post" class="karta formular"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>">
<h3>Akcie</h3>
<div class="akcie"><?php foreach (['schvalena' => 'Schváliť', 'pozastavena' => 'Pozastaviť', 'registrovana' => 'Vrátiť na schválenie'] as $k => $t): if ($pp['stav'] !== $k): ?><button class="btn mini" name="stav" value="<?= $k ?>"><?= $t ?></button><?php endif; endforeach; ?>
<button class="btn mini" name="aktivuj" value="partner12" onclick="return confirm('Aktivovať Partner 12 na 12 mesiacov (po úhrade prevodom)?')">Aktivovať Partner 12</button><button class="btn mini" name="aktivuj" value="pro" onclick="return confirm('Aktivovať Partner Pro?')">Aktivovať Pro</button>
<button class="btn mini" name="pozerat_ako" value="1">Pozerať Studio ako partner</button></div>
<label>Priradiť ďalší Google e-mail (partner sa prihlási iným účtom) <input name="pridaj_email" type="email" placeholder="meno@gmail.com"> <button class="btn mini">Priradiť</button></label>
<label>Poznámka admina <textarea name="poznamka" rows="2"><?= h($pp['poznamka_admin']) ?></textarea> <button class="btn mini">Uložiť poznámku</button></label>
</form>
<div class="karta"><h3>Články</h3><table class="tab"><?php foreach ($cl as $c): ?><tr><td><?= h($c['nadpis'] ?: $c['namet']) ?></td><td><span class="stav stav-<?= h($c['stav']) ?>"><?= h($c['stav']) ?></span></td><td><a href="/?p=admin_clanky&id=<?= h($c['id']) ?>">otvoriť</a></td></tr><?php endforeach; ?></table></div>
<div class="karta"><h3>Aktivity</h3><table class="tab"><?php foreach (nj_audit_citaj(40, $pp['id']) as $a): ?><tr><td><?= date('j.n. H:i', strtotime($a['t'])) ?></td><td><?= h($a['kto']) ?></td><td><?= h($a['akcia']) ?></td><td class="tlm"><?= h($a['detail'] ?? '') ?></td></tr><?php endforeach; ?></table></div>
