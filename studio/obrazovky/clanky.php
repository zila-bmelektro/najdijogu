<?php
require_once __DIR__ . '/../lib/ai.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_over_csrf();
    if (isset($_POST['novy_namet'])) {
        $n = mb_substr(trim((string)$_POST['namet']), 0, 600);
        if ($n === '') { nj_sprava('Napíšte námet.', 'chyba'); nj_presmeruj('clanky'); }
        $c = ['id' => 'c' . substr(bin2hex(random_bytes(6)), 0, 10), 'partner' => $partner['id'], 'namet' => $n, 'poznamka' => mb_substr(trim((string)($_POST['poznamka'] ?? '')), 0, 600), 'stav' => 'namet', 'vytvorene' => date('c'), 'nadpis' => '', 'perex' => '', 'html' => '', 'meta' => '', 'slug' => '', 'publikovane' => null];
        nj_clanok_uloz($c); nj_audit('clanok_namet', ['partner' => $partner['id'], 'clanok' => $c['id'], 'detail' => $n]);
        header('Location: ' . NJ_STUDIO . '/?p=clanok&id=' . $c['id'] . '&generuj=1'); exit;
    }
    if (isset($_POST['navrhni'])) {
        $_SESSION['navrhy_nametov'] = nj_ai_namety($partner); nj_audit('clanok_navrhy', ['partner' => $partner['id']]); nj_presmeruj('clanky');
    }
}
$cl = nj_clanky($partner['id']); $navrhy = $_SESSION['navrhy_nametov'] ?? [];
$stavy = ['namet' => 'námet', 'koncept' => 'koncept — na úpravu a schválenie', 'schvalene' => 'schválené vami · čaká na publikovanie', 'publikovany' => 'publikovaný', 'zamietnuty' => 'zamietnutý'];
$aktivny = nj_partner_aktivny($partner); $mesiac = count(array_filter($cl, fn($c) => ($c['vytvorene'] ?? '') >= date('Y-m-01')));
$limit = $partner['balik'] === 'pro' ? 8 : ($aktivny ? 4 : 1);
?>
<h1>Články</h1>
<p class="drobne">Tento mesiac: <?= $mesiac ?> z <?= $limit ?> námetov<?= $aktivny ? '' : ' (bezplatný listing má 1 článok mesačne, Partner 12 štyri)' ?>. Postup: námet → AI napíše koncept → vy ho upravíte a schválite → my publikujeme pod hlavičkou vašej jogovne na najdijogu.sk.</p>
<div class="karta">
<form method="post" class="formular"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>">
<label>Nový námet (o čom má článok byť) <input name="namet" maxlength="600" placeholder="Ako začať s jogou po štyridsiatke" <?= $mesiac >= $limit ? 'disabled' : '' ?> list="navrhy"></label>
<datalist id="navrhy"><?php foreach ($navrhy as $n): ?><option value="<?= h($n) ?>"><?php endforeach; ?></datalist>
<label>Poznámka pre autora (nepovinné) <input name="poznamka" maxlength="600" placeholder="spomeň náš ranný Mysore kurz od októbra"></label>
<div class="akcie"><button class="btn primar" name="novy_namet" value="1" <?= $mesiac >= $limit ? 'disabled' : '' ?>>Napísať koncept</button> <button class="btn" name="navrhni" value="1">Navrhni mi témy</button></div>
<?php if ($navrhy): ?><p class="drobne">Návrhy: <?php foreach ($navrhy as $n): ?><a href="#" class="navrh" data-t="<?= h($n) ?>"><?= h($n) ?></a> · <?php endforeach; ?></p><?php endif; ?>
</form></div>
<table class="tab"><thead><tr><th>Článok</th><th>Stav</th><th>Zobrazenia</th><th>Zmenené</th></tr></thead><tbody>
<?php $st = nj_stat_citaj($partner['id'], 365); foreach ($cl as $c): ?>
<tr><td><a href="/?p=clanok&id=<?= h($c['id']) ?>"><?= h($c['nadpis'] ?: $c['namet']) ?></a></td><td><span class="stav stav-<?= h($c['stav']) ?>"><?= h($stavy[$c['stav']] ?? $c['stav']) ?></span></td><td><?= (int)($st['spolu_objekty']['clanok_zobrazenie:' . $c['id']] ?? 0) ?></td><td><?= nj_datum($c['upravene']) ?></td></tr>
<?php endforeach; if (!$cl): ?><tr><td colspan="4" class="tlm">Zatiaľ žiadny článok. Začnite námetom hore.</td></tr><?php endif; ?>
</tbody></table>
