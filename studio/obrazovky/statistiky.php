<?php $dni = max(7, min(365, (int)($_GET['dni'] ?? 30))); $st = nj_stat_citaj($partner['id'], $dni); $s = $st['spolu']; $cl = nj_clanky($partner['id']);
$rad = ['zobrazenie_v_zozname' => 'Zobrazenia mena v zozname jogovní', 'zobrazenie_profilu' => 'Zobrazenia profilu', 'klik_web' => 'Kliky na web', 'klik_telefon' => 'Kliky na telefón', 'klik_rezervacia' => 'Kliky na rezervácie', 'klik_mapa' => 'Kliky na mapu', 'clanok_zobrazenie' => 'Zobrazenia článkov', 'clanok_docitanie' => 'Dočítania článkov', 'clanok_klik_jogovna' => 'Kliky na jogovňu z článkov'];
$max = 1; foreach ($st['dni'] as $d) $max = max($max, ($d['zobrazenie_profilu'] ?? 0) + ($d['zobrazenie_v_zozname'] ?? 0));
?>
<h1>Štatistiky <small class="drobne">posledných <?= $dni ?> dní · <a href="/?p=statistiky&dni=7">7</a> · <a href="/?p=statistiky&dni=30">30</a> · <a href="/?p=statistiky&dni=90">90</a> · <a href="/?p=statistiky&dni=365">365</a></small></h1>
<div class="dlazdice"><?php foreach ($rad as $k => $n): ?><div class="dl"><b><?= (int)($s[$k] ?? 0) ?></b><span><?= h($n) ?></span></div><?php endforeach; ?></div>
<div class="karta"><h3>Zobrazenia po dňoch (profil + zoznam)</h3>
<div class="graf" role="img" aria-label="stĺpcový graf zobrazení po dňoch"><?php foreach ($st['dni'] as $d => $v): $n = ($v['zobrazenie_profilu'] ?? 0) + ($v['zobrazenie_v_zozname'] ?? 0); ?><div class="stlpec" style="height:<?= round(100 * $n / $max) ?>%" title="<?= date('j.n.', strtotime($d)) ?>: <?= $n ?>"></div><?php endforeach; ?></div>
<p class="drobne">Počítame bez cookies a bez sledovania osôb — len anonymné počty udalostí. Kokpit sa do štatistík nezapočítava.</p></div>
<?php if ($cl): ?><div class="karta"><h3>Články</h3><table class="tab"><thead><tr><th>Článok</th><th>Zobrazenia</th><th>Dočítania</th><th>Kliky na jogovňu</th></tr></thead><tbody>
<?php foreach ($cl as $c): if ($c['stav'] !== 'publikovany') continue; $o = $st['spolu_objekty'] ?? []; ?><tr><td><a href="/?p=clanok&id=<?= h($c['id']) ?>"><?= h($c['nadpis']) ?></a></td><td><?= (int)($o['clanok_zobrazenie:' . $c['id']] ?? 0) ?></td><td><?= (int)($o['clanok_docitanie:' . $c['id']] ?? 0) ?></td><td><?= (int)($o['clanok_klik_jogovna:' . $c['id']] ?? 0) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
