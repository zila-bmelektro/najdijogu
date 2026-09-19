<?php
require_once __DIR__ . '/../lib/ai.php';
$c = nj_clanok((string)($_GET['id'] ?? ''));
if (!$c || $c['partner'] !== $partner['id']) { nj_sprava('Článok sa nenašiel.', 'chyba'); nj_presmeruj('clanky'); }
if (!empty($_GET['generuj']) && $c['stav'] === 'namet') {
    $k = nj_ai_koncept($partner, $c);
    if ($k) { $c = array_merge($c, $k, ['stav' => 'koncept']); $c['slug'] = nj_slug($k['nadpis']); nj_clanok_uloz($c); nj_audit('clanok_koncept', ['partner' => $partner['id'], 'clanok' => $c['id']]); nj_sprava('Koncept je napísaný. Prečítajte, upravte, čo treba, a schváľte.'); }
    else nj_sprava('Koncept sa nepodarilo napísať (AI nedostupná). Skúste o chvíľu tlačidlom „Napísať znova“.', 'chyba');
    header('Location: ' . NJ_STUDIO . '/?p=clanok&id=' . $c['id']); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_over_csrf();
    if (isset($_POST['uloz']) || isset($_POST['schval'])) {
        $c['nadpis'] = mb_substr(strip_tags(trim((string)$_POST['nadpis'])), 0, 120);
        $c['perex'] = mb_substr(strip_tags(trim((string)$_POST['perex'])), 0, 300);
        $c['html'] = strip_tags((string)$_POST['html'], '<p><h2><h3><ul><ol><li><strong><em><br><blockquote><a>');
        $c['html'] = preg_replace('/<a\s[^>]*?(href="https?:\/\/[^"]+")[^>]*>/i', '<a $1 rel="nofollow">', $c['html']);
        $c['meta'] = mb_substr(strip_tags(trim((string)($_POST['meta'] ?? ''))), 0, 160);
        if (!$c['slug']) $c['slug'] = nj_slug($c['nadpis']);
        if (isset($_POST['schval'])) { $c['stav'] = 'schvalene'; $c['schvalene_cas'] = date('c'); nj_audit('clanok_schvalenie', ['partner' => $partner['id'], 'clanok' => $c['id']]); nj_email(NJ_ADMINI[0], 'najdijogu: článok na publikovanie — ' . $partner['nazov'], '<p>Partner <b>' . h($partner['nazov']) . '</b> schválil článok <b>' . h($c['nadpis']) . '</b>.</p><p><a href="' . NJ_STUDIO . '/?p=admin_clanky">Otvoriť fronty článkov</a></p>'); nj_sprava('Článok je schválený. Publikujeme ho spravidla do 24 hodín.'); }
        else { nj_audit('clanok_uprava', ['partner' => $partner['id'], 'clanok' => $c['id']]); nj_sprava('Uložené.'); }
        nj_clanok_uloz($c);
    }
    if (isset($_POST['znova'])) { $c['stav'] = 'namet'; $c['poznamka'] = mb_substr(trim((string)($_POST['poznamka'] ?? '')), 0, 600); nj_clanok_uloz($c); header('Location: ' . NJ_STUDIO . '/?p=clanok&id=' . $c['id'] . '&generuj=1'); exit; }
    if (isset($_POST['zmaz']) && $c['stav'] !== 'publikovany') { @unlink(nj_data_dir() . '/clanky/' . $c['id'] . '.json'); nj_audit('clanok_zmazanie', ['partner' => $partner['id'], 'clanok' => $c['id']]); nj_sprava('Článok zmazaný.'); nj_presmeruj('clanky'); }
    header('Location: ' . NJ_STUDIO . '/?p=clanok&id=' . $c['id']); exit;
}
$st = nj_stat_citaj($partner['id'], 365)['spolu_objekty'] ?? [];
?>
<p><a href="/?p=clanky">‹ Články</a></p>
<h1><?= h($c['nadpis'] ?: $c['namet']) ?> <small class="stav stav-<?= h($c['stav']) ?>"><?= h($c['stav']) ?></small></h1>
<?php if ($c['stav'] === 'namet'): ?>
<div class="karta"><p>Námet: <b><?= h($c['namet']) ?></b></p><form method="post"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>"><div class="akcie"><a class="btn primar" href="/?p=clanok&id=<?= h($c['id']) ?>&generuj=1">Napísať koncept (AI, ~20 s)</a> <button class="btn" name="zmaz" value="1">Zmazať</button></div></form></div>
<?php elseif ($c['stav'] === 'publikovany'): ?>
<div class="dlazdice"><div class="dl"><b><?= (int)($st['clanok_zobrazenie:' . $c['id']] ?? 0) ?></b><span>zobrazení</span></div><div class="dl"><b><?= (int)($st['clanok_docitanie:' . $c['id']] ?? 0) ?></b><span>dočítaní do konca</span></div><div class="dl"><b><?= (int)($st['clanok_klik_jogovna:' . $c['id']] ?? 0) ?></b><span>klikov na jogovňu z článku</span></div></div>
<p>Publikovaný <?= nj_datum($c['publikovane']) ?>: <a href="<?= NJ_WEB ?>/jogovne/<?= h($partner['slug']) ?>/clanky/<?= h($c['slug']) ?>" target="_blank"><?= NJ_WEB ?>/jogovne/<?= h($partner['slug']) ?>/clanky/<?= h($c['slug']) ?> ↗</a></p>
<article class="nahlad"><h2><?= h($c['nadpis']) ?></h2><p class="perex"><?= h($c['perex']) ?></p><?= $c['html'] ?></article>
<?php else: ?>
<form method="post" class="formular"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>">
<label>Nadpis <input name="nadpis" value="<?= h($c['nadpis']) ?>" maxlength="120" required></label>
<label>Perex (1–2 vety) <textarea name="perex" rows="2" maxlength="300"><?= h($c['perex']) ?></textarea></label>
<label>Text článku (HTML: odseky, nadpisy h2, zoznamy) <textarea name="html" rows="22" id="html-editor"><?= h($c['html']) ?></textarea></label>
<label>Popis pre vyhľadávače (do 155 znakov) <input name="meta" value="<?= h($c['meta']) ?>" maxlength="160"></label>
<div class="akcie">
<?php if ($c['stav'] === 'koncept'): ?><button class="btn primar" name="schval" value="1">Schváliť na publikovanie</button><?php endif; ?>
<button class="btn" name="uloz" value="1">Uložiť úpravy</button>
<?php if ($c['stav'] === 'schvalene'): ?><span class="drobne">Schválené <?= nj_datum($c['schvalene_cas'] ?? null) ?> · čaká na publikovanie (do 24 h).</span><?php endif; ?>
<button class="btn" name="zmaz" value="1" onclick="return confirm('Zmazať článok?')">Zmazať</button>
</div>
<details class="karta"><summary>Nepáči sa mi, napísať znova s poznámkou</summary><label>Čo zmeniť <input name="poznamka" maxlength="600" placeholder="kratšie, viac o začiatočníkoch, spomeň naše ranné kurzy"></label><button class="btn" name="znova" value="1">Napísať znova</button></details>
</form>
<h3>Náhľad</h3><article class="nahlad"><h2><?= h($c['nadpis']) ?></h2><p class="perex"><?= h($c['perex']) ?></p><?= $c['html'] ?></article>
<?php endif; ?>
