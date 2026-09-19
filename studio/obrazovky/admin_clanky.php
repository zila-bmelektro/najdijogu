<?php
$id = (string)($_GET['id'] ?? ''); $c = $id ? nj_clanok($id) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $c) {
    nj_over_csrf(); $pp = nj_partner($c['partner']);
    if (isset($_POST['publikuj'])) { $c['stav'] = 'publikovany'; $c['publikovane'] = $c['publikovane'] ?? date('c'); if (!$c['slug']) $c['slug'] = nj_slug($c['nadpis']); nj_clanok_uloz($c); nj_audit('clanok_publikovanie', ['partner' => $c['partner'], 'clanok' => $c['id']]); if ($pp) nj_email($pp['email'], 'najdijogu: článok je publikovaný', '<p>Článok <b>' . h($c['nadpis']) . '</b> je zverejnený: <a href="' . NJ_WEB . '/jogovne/' . $pp['slug'] . '/clanky/' . $c['slug'] . '">' . NJ_WEB . '/jogovne/' . $pp['slug'] . '/clanky/' . $c['slug'] . '</a></p>'); nj_sprava('Publikované.'); }
    if (isset($_POST['vrat'])) { $c['stav'] = 'koncept'; $c['poznamka_admin'] = mb_substr((string)($_POST['dovod'] ?? ''), 0, 500); nj_clanok_uloz($c); nj_audit('clanok_vratenie', ['partner' => $c['partner'], 'clanok' => $c['id']]); if ($pp) nj_email($pp['email'], 'najdijogu: článok vrátený na úpravu', '<p>Článok <b>' . h($c['nadpis']) . '</b> sme vrátili na úpravu. Dôvod: ' . h($c['poznamka_admin']) . '</p><p><a href="' . NJ_STUDIO . '/?p=clanok&id=' . $c['id'] . '">Otvoriť v Studiu</a></p>'); nj_sprava('Vrátené partnerovi.'); }
    if (isset($_POST['stiahni'])) { $c['stav'] = 'schvalene'; nj_clanok_uloz($c); nj_audit('clanok_stiahnutie', ['partner' => $c['partner'], 'clanok' => $c['id']]); nj_sprava('Stiahnuté z webu.'); }
    header('Location: ' . NJ_STUDIO . '/?p=admin_clanky&id=' . $c['id']); exit;
}
if ($c): $pp = nj_partner($c['partner']); ?>
<p><a href="/?p=admin_clanky">‹ Fronta článkov</a></p>
<h1><?= h($c['nadpis'] ?: $c['namet']) ?> <small class="stav stav-<?= h($c['stav']) ?>"><?= h($c['stav']) ?></small></h1>
<p class="drobne">Jogovňa: <a href="/?p=admin_partner&id=<?= h($c['partner']) ?>"><?= h($pp['nazov'] ?? '?') ?></a> · námet: <?= h($c['namet']) ?> · schválené partnerom <?= nj_datum($c['schvalene_cas'] ?? null) ?></p>
<form method="post" class="akcie"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>">
<?php if ($c['stav'] === 'schvalene' || $c['stav'] === 'koncept'): ?><button class="btn primar" name="publikuj" value="1">Publikovať</button><?php endif; ?>
<?php if ($c['stav'] === 'publikovany'): ?><a class="btn" target="_blank" href="<?= NJ_WEB ?>/jogovne/<?= h($pp['slug'] ?? '') ?>/clanky/<?= h($c['slug']) ?>">Zobraziť ↗</a><button class="btn" name="stiahni" value="1">Stiahnuť z webu</button><?php endif; ?>
<input name="dovod" placeholder="dôvod vrátenia" maxlength="500" style="max-width:320px"><button class="btn" name="vrat" value="1">Vrátiť na úpravu</button></form>
<article class="nahlad"><h2><?= h($c['nadpis']) ?></h2><p class="perex"><?= h($c['perex']) ?></p><?= $c['html'] ?></article>
<?php else: $vs = nj_clanky(); ?>
<h1>Admin — články</h1>
<table class="tab"><thead><tr><th>Článok</th><th>Jogovňa</th><th>Stav</th><th>Zmenené</th></tr></thead><tbody>
<?php foreach ($vs as $x): $pp = nj_partner($x['partner']); ?><tr class="<?= $x['stav'] === 'schvalene' ? 'zvyrazni' : '' ?>"><td><a href="/?p=admin_clanky&id=<?= h($x['id']) ?>"><?= h($x['nadpis'] ?: $x['namet']) ?></a></td><td><?= h($pp['nazov'] ?? '?') ?></td><td><span class="stav stav-<?= h($x['stav']) ?>"><?= h($x['stav']) ?></span></td><td><?= nj_datum($x['upravene']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
