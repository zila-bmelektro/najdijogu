<?php
/* Predplatné: Partner 12 (288 €/rok) · Partner Pro (588 €/rok). Platba kartou / bankovým tlačidlom cez GoPay (lib/gopay.php),
   alebo prevodom (admin aktivuje ručne po úhrade). Po 12 mesiacoch: „neobnovovať“ = profil ostáva ako bezplatný listing. */
require_once __DIR__ . '/../lib/gopay.php';
$gopay = nj_gopay_zapnute();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_over_csrf();
    if (isset($_POST['neobnovovat'])) { $partner['neobnovovat'] = $_POST['neobnovovat'] === '1'; nj_partner_uloz($partner); nj_audit('predplatne_neobnovovat', ['partner' => $partner['id'], 'detail' => $partner['neobnovovat'] ? 'áno' : 'nie']); nj_sprava($partner['neobnovovat'] ? 'Predplatné sa po skončení neobnoví. Profil ostane ako bezplatný listing.' : 'Obnovovanie zapnuté.'); nj_presmeruj('predplatne'); }
    if (isset($_POST['balik']) && isset(NJ_BALIKY[$_POST['balik']]) && $_POST['balik'] !== 'listing') {
        $b = $_POST['balik']; $cena = NJ_BALIKY[$b]['cena'];
        if (($_POST['sposob'] ?? '') === 'gopay' && $gopay) {
            nj_rate_limit('gopay', 10, 3600);
            $url = nj_gopay_zaplat($partner, $b);
            if ($url) { header('Location: ' . $url); exit; }
            nj_sprava('Platobnú bránu sa nepodarilo otvoriť. Skúste to o chvíľu alebo zvoľte prevod.', 'chyba'); nj_presmeruj('predplatne');
        }
        $obj = ['id' => 'o' . date('ymd') . substr(bin2hex(random_bytes(3)), 0, 4), 'partner' => $partner['id'], 'balik' => $b, 'suma' => $cena, 'stav' => 'caka_prevod', 'sposob' => 'prevod', 'vytvorene' => date('c')];
        nj_uprav('objednavky', fn($o) => $o + [$obj['id'] => $obj]);
        nj_audit('objednavka_prevod', ['partner' => $partner['id'], 'detail' => "$b {$cena} €"]);
        nj_email($partner['email'], 'najdijogu: objednávka ' . NJ_BALIKY[$b]['nazov'], '<p>Ďakujeme za objednávku balíka <b>' . NJ_BALIKY[$b]['nazov'] . '</b> na 12 mesiacov, ' . $cena . ' € (bez DPH, faktúru pošleme po úhrade).</p><p>Úhrada prevodom: IBAN pošleme v potvrdzujúcom e-maile do 1 pracovného dňa, variabilný symbol <b>' . substr(preg_replace('/\D/', '', $obj['id'] . '1'), 0, 10) . '</b>. Po pripísaní platby balík aktivujeme.</p>');
        nj_email(NJ_ADMINI[0], 'najdijogu: nová objednávka prevodom — ' . $partner['nazov'], '<p>' . h($partner['nazov']) . ' objednal ' . NJ_BALIKY[$b]['nazov'] . ' (' . $cena . ' €) prevodom. Aktivovať v <a href="' . NJ_STUDIO . '/?p=admin_partner&id=' . $partner['id'] . '">admine</a> po úhrade.</p>');
        nj_sprava('Objednávka prijatá. Platobné údaje prídu e-mailom; po úhrade balík aktivujeme.'); nj_presmeruj('predplatne');
    }
}
if (!empty($_GET['gopay']) && $gopay) {
    /* návrat z brány: overíme stav priamo v GoPay (notifikácia to spraví aj bez tohto; toto je okamžitá odozva) */
    $oid = preg_replace('/[^a-z0-9]/', '', (string)$_GET['gopay']); $o = nj_citaj('objednavky', [])[$oid] ?? null;
    if ($o && $o['partner'] === $partner['id'] && !empty($o['gopay_id'])) {
        $stav = nj_gopay_over($o['gopay_id']); $partner = nj_partner($partner['id']);
        if ($stav === 'PAID') nj_sprava('Zaplatené — balík je aktívny. Ďakujeme!');
        elseif (in_array($stav, ['CANCELED', 'TIMEOUTED'], true)) nj_sprava('Platba bola zrušená. Môžete to skúsiť znova alebo zvoliť prevod.', 'chyba');
        else nj_sprava('Platbu banka ešte spracúva. Balík aktivujeme automaticky hneď po potvrdení (pošleme e-mail).');
        nj_presmeruj('predplatne');
    }
}
$akt = nj_partner_aktivny($partner); $obj = array_filter(nj_citaj('objednavky', []), fn($o) => $o['partner'] === $partner['id']);
?>
<h1>Predplatné</h1>
<div class="dlazdice"><div class="dl"><b><?= h(NJ_BALIKY[$partner['balik']]['nazov']) ?></b><span>aktuálny balík</span></div><div class="dl"><b><?= $akt ? nj_datum($partner['plati_do']) : '—' ?></b><span>platí do</span></div><div class="dl"><b><?= $akt ? ($partner['neobnovovat'] ? 'nie' : 'áno') : '—' ?></b><span>obnoviť po 12 mesiacoch</span></div></div>
<?php if ($akt): ?>
<form method="post" class="karta"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>"><p>Balík platí do <b><?= nj_datum($partner['plati_do']) ?></b>. 30 dní pred koncom vám pošleme pripomienku. Ak sa neobnoví, profil ostane na najdijogu.sk ako bezplatný listing (názov, adresa, hodnotenie z Google) — nič sa nemaže.</p>
<button class="btn" name="neobnovovat" value="<?= $partner['neobnovovat'] ? '0' : '1' ?>"><?= $partner['neobnovovat'] ? 'Zapnúť obnovovanie' : 'Po 12 mesiacoch neobnovovať' ?></button></form>
<?php endif; ?>
<div class="baliky">
<?php foreach (['partner12' => ['24 €/mes.', '288 € / 12 mesiacov', ['claimed profil so službami, cenami a akciami', 'zvýraznenie v zozname jogovní v okolí', '4 AI články mesačne pod vašou hlavičkou', 'štatistiky zobrazení a klikov', 'Google hodnotenia a recenzie v profile', '20 účtov Plus pre vašich klientov (cvičenie doma)']], 'pro' => ['49 €/mes.', '588 € / 12 mesiacov', ['všetko z Partner 12', '8 článkov mesačne + 2 videá na siete', 'vlastná sekvencia vašej jogovne v appke', 'top pozícia v meste', 'prednostná podpora']]] as $k => [$m, $r, $body]): ?>
<div class="karta balik-karta <?= $partner['balik'] === $k && $akt ? 'akt' : '' ?>"><h3><?= h(NJ_BALIKY[$k]['nazov']) ?> <small><?= $m ?></small></h3><p class="drobne"><?= $r ?>, bez viazanosti po uplynutí</p><ul><?php foreach ($body as $b): ?><li><?= h($b) ?></li><?php endforeach; ?></ul>
<?php if (!($partner['balik'] === $k && $akt)): ?><form method="post"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>"><input type="hidden" name="balik" value="<?= $k ?>"><div class="akcie"><?php if ($gopay): ?><button class="btn primar" name="sposob" value="gopay">Zaplatiť kartou / bankou (GoPay)</button><?php endif; ?><button class="btn" name="sposob" value="prevod">Objednať prevodom</button></div></form><?php endif; ?></div>
<?php endforeach; ?>
</div>
<?php if ($obj): ?><div class="karta"><h3>Objednávky a platby</h3><table class="tab"><?php foreach ($obj as $o): ?><tr><td><?= nj_datum($o['vytvorene']) ?></td><td><?= h(NJ_BALIKY[$o['balik']]['nazov']) ?></td><td><?= (int)$o['suma'] ?> €</td><td><?= ['caka_prevod' => 'čaká na úhradu prevodom', 'caka_gopay' => 'platba sa spracúva', 'zaplatene' => 'zaplatené', 'zrusene' => 'zrušené', 'vyprsane' => 'nedokončená platba'][$o['stav']] ?? h($o['stav']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php if ($gopay && !nj_gopay_ostra()): ?><p class="sprava chyba">Platobná brána je v testovacom režime — platby sú skúšobné.</p><?php endif; ?>
<p class="drobne">Platby kartou a bankovým prevodom spracúva GoPay. Ceny bez DPH. Faktúru dostanete e-mailom. Po 12 mesiacoch sa balík neobnovuje automaticky bez vášho súhlasu — pripomenieme sa 30 dní vopred.</p>
