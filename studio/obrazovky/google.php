<?php
require_once __DIR__ . '/../lib/google.php';
$kluc = (bool)nj_tajomstvo('google_places_key'); $vysledky = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_over_csrf();
    if (isset($_POST['hladaj'])) { $vysledky = nj_places_hladaj(mb_substr(trim((string)$_POST['hladaj']), 0, 120)); if (!$vysledky) nj_sprava('Nič sa nenašlo. Skúste názov + mesto presne ako v Google Mapách.', 'chyba'); }
    if (isset($_POST['pripoj'])) {
        $pid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_POST['pripoj']);
        $d = nj_places_detail($pid, true);
        if ($d) { $partner['google_place_id'] = $pid; $partner['google_rating'] = $d['rating']; $partner['google_pocet'] = $d['pocet']; if (!$partner['lat'] && $d['lat']) { $partner['lat'] = $d['lat']; $partner['lng'] = $d['lng']; } if (!$partner['adresa'] && $d['adresa']) $partner['adresa'] = $d['adresa']; nj_partner_uloz($partner); nj_audit('google_pripojenie', ['partner' => $partner['id'], 'detail' => $d['nazov']]); nj_sprava('Google profil pripojený: ' . $d['nazov'] . ($d['rating'] ? ', ' . $d['rating'] . ' ★' : '')); }
        else nj_sprava('Profil sa nepodarilo načítať.', 'chyba');
        nj_presmeruj('google');
    }
    if (isset($_POST['odpoj'])) { $partner['google_place_id'] = ''; $partner['google_rating'] = null; $partner['google_pocet'] = null; nj_partner_uloz($partner); nj_audit('google_odpojenie', ['partner' => $partner['id']]); nj_presmeruj('google'); }
    if (isset($_POST['obnov'])) { $d = nj_places_detail($partner['google_place_id'], true); if ($d) { $partner['google_rating'] = $d['rating']; $partner['google_pocet'] = $d['pocet']; nj_partner_uloz($partner); nj_sprava('Hodnotenie obnovené.'); } nj_presmeruj('google'); }
}
$d = $partner['google_place_id'] ? nj_places_detail($partner['google_place_id']) : null;
?>
<h1>Google profil</h1>
<?php if (!$kluc): ?><div class="sprava chyba">Prepojenie s Google Mapami sa práve nastavuje — o pár dní tu pripojíte svoj profil a hodnotenie sa zobrazí automaticky.</div><?php endif; ?>
<?php if ($d): ?>
<div class="karta"><h3><?= h($d['nazov']) ?> <small class="drobne"><?= h($d['adresa']) ?></small></h3>
<div class="dlazdice"><div class="dl"><b><?= $d['rating'] ? number_format((float)$d['rating'], 1, ',', '') . ' ★' : '—' ?></b><span>hodnotenie Google</span></div><div class="dl"><b><?= (int)$d['pocet'] ?></b><span>recenzií</span></div><div class="dl"><b><?= nj_datum($d['stiahnute']) ?></b><span>naposledy obnovené (automaticky každých 30 dní)</span></div></div>
<?php if ($d['recenzie']): ?><h3>Posledné recenzie (z Google)</h3><div class="recenzie"><?php foreach ($d['recenzie'] as $r): ?><blockquote class="recenzia"><b><?= str_repeat('★', (int)$r['hviezdy']) ?></b> <?= h(mb_substr($r['text'], 0, 400)) ?><footer><a href="<?= h($r['autor_url']) ?>" rel="nofollow noopener" target="_blank"><?= h($r['autor']) ?></a> · <?= h($r['kedy']) ?></footer></blockquote><?php endforeach; ?></div><p class="drobne">Recenzie a hodnotenia poskytuje Google. <a href="<?= h($d['maps']) ?>" target="_blank" rel="noopener">Zobraziť v Google Mapách ↗</a></p><?php endif; ?>
<form method="post" class="akcie"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>"><button class="btn" name="obnov" value="1">Obnoviť teraz</button><button class="btn" name="odpoj" value="1" onclick="return confirm('Odpojiť Google profil?')">Odpojiť</button></form></div>
<?php else: ?>
<div class="karta"><p>Nájdite svoju jogovňu v Google Mapách a pripojte ju. V profile sa potom zobrazí hodnotenie a posledné recenzie, obnovujeme ich automaticky.</p>
<form method="post" class="formular"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>"><label>Názov a mesto ako v Google Mapách <input name="hladaj" value="<?= h($_POST['hladaj'] ?? ($partner['nazov'] . ' ' . $partner['mesto'])) ?>" maxlength="120"></label><button class="btn primar" <?= $kluc ? '' : 'disabled' ?>>Hľadať</button></form>
<?php if ($vysledky): ?><table class="tab"><?php foreach ($vysledky as $v): ?><tr><td><b><?= h($v['nazov']) ?></b><br><span class="tlm"><?= h($v['adresa']) ?></span></td><td><form method="post"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>"><button class="btn mini primar" name="pripoj" value="<?= h($v['id']) ?>">Toto je moja jogovňa</button></form></td></tr><?php endforeach; ?></table><?php endif; ?>
</div>
<?php endif; ?>
<div class="karta"><h3>Pripojenie vlastného Google Business Profile</h3><p class="drobne">V ďalšom kroku pribudne prihlásenie priamo do vášho Google Business Profile — všetky recenzie, nielen 5 posledných, a odpovedanie na ne z tohto kokpitu. Čaká na schválenie prístupu Googlom.</p></div>
