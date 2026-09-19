<?php $st = nj_stat_citaj($partner['id'], 30); $s = $st['spolu']; $cl = nj_clanky($partner['id']);
$kroky = [];
if (empty($partner['popis']) || empty($partner['adresa'])) $kroky[] = ['profil', 'Doplňte popis a adresu jogovne'];
if (empty($partner['sluzby'])) $kroky[] = ['sluzby', 'Pridajte služby a ceny'];
if (empty($partner['google_place_id'])) $kroky[] = ['google', 'Pripojte Google profil, aby sa zobrazilo hodnotenie'];
if (!$cl) $kroky[] = ['clanky', 'Zadajte prvý námet na článok'];
if (!nj_partner_aktivny($partner)) $kroky[] = ['predplatne', 'Aktivujte Partner 12 — zvýraznenie, články, štatistiky'];
?>
<h1><?= h($partner['nazov']) ?> <small class="stav stav-<?= h($partner['stav']) ?>"><?= ['nova'=>'nová','registrovana'=>'čaká na schválenie','schvalena'=>'schválená','pozastavena'=>'pozastavená'][$partner['stav']] ?? h($partner['stav']) ?></small> <small class="balik"><?= h(NJ_BALIKY[$partner['balik']]['nazov']) ?><?= nj_partner_aktivny($partner) ? ' do ' . nj_datum($partner['plati_do']) : '' ?></small></h1>
<div class="dlazdice">
  <div class="dl"><b><?= (int)($s['zobrazenie_profilu'] ?? 0) ?></b><span>zobrazení profilu · 30 dní</span></div>
  <div class="dl"><b><?= (int)($s['zobrazenie_v_zozname'] ?? 0) ?></b><span>zobrazení v zozname jogovní</span></div>
  <div class="dl"><b><?= (int)(($s['klik_web'] ?? 0) + ($s['klik_telefon'] ?? 0) + ($s['klik_rezervacia'] ?? 0) + ($s['klik_mapa'] ?? 0)) ?></b><span>klikov (web <?= (int)($s['klik_web'] ?? 0) ?> · tel <?= (int)($s['klik_telefon'] ?? 0) ?> · rezervácie <?= (int)($s['klik_rezervacia'] ?? 0) ?> · mapa <?= (int)($s['klik_mapa'] ?? 0) ?>)</span></div>
  <div class="dl"><b><?= (int)($s['clanok_zobrazenie'] ?? 0) ?></b><span>zobrazení článkov (<?= count($cl) ?> <?= count($cl) === 1 ? 'článok' : 'článkov' ?>)</span></div>
  <div class="dl"><b><?= $partner['google_rating'] !== null ? number_format((float)$partner['google_rating'], 1, ',', '') . ' ★' : '—' ?></b><span>Google hodnotenie<?= $partner['google_pocet'] ? ' · ' . (int)$partner['google_pocet'] . ' recenzií' : '' ?></span></div>
</div>
<?php if ($kroky): ?><div class="karta"><h3>Ďalšie kroky</h3><ul class="kroky"><?php foreach ($kroky as [$k, $t]): ?><li><a href="/?p=<?= $k ?>"><?= h($t) ?> ›</a></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="karta"><h3>Posledná aktivita</h3><table class="tab"><?php foreach (array_slice(nj_audit_citaj(100, $partner['id']), 0, 8) as $a): ?><tr><td><?= date('j.n. H:i', strtotime($a['t'])) ?></td><td><?= h($a['akcia']) ?></td><td class="tlm"><?= h($a['detail'] ?? '') ?></td></tr><?php endforeach; ?></table></div>
