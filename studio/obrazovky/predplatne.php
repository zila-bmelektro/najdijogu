<?php
/* Predplatné: Partner 12 (288 €/rok) · Partner Pro (588 €/rok). Platba kartou cez Stripe Checkout (kľúč data/stripe_secret.txt),
   alebo prevodom (admin aktivuje ručne po úhrade). Po 12 mesiacoch: „neobnovovať“ = profil ostáva ako bezplatný listing. */
$stripe = nj_tajomstvo('stripe_secret');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_over_csrf();
    if (isset($_POST['neobnovovat'])) { $partner['neobnovovat'] = $_POST['neobnovovat'] === '1'; nj_partner_uloz($partner); nj_audit('predplatne_neobnovovat', ['partner' => $partner['id'], 'detail' => $partner['neobnovovat'] ? 'áno' : 'nie']); nj_sprava($partner['neobnovovat'] ? 'Predplatné sa po skončení neobnoví. Profil ostane ako bezplatný listing.' : 'Obnovovanie zapnuté.'); nj_presmeruj('predplatne'); }
    if (isset($_POST['balik']) && isset(NJ_BALIKY[$_POST['balik']]) && $_POST['balik'] !== 'listing') {
        $b = $_POST['balik']; $cena = NJ_BALIKY[$b]['cena'];
        if (($_POST['sposob'] ?? '') === 'prevod' || !$stripe) {
            $obj = ['id' => 'o' . date('ymd') . substr(bin2hex(random_bytes(3)), 0, 4), 'partner' => $partner['id'], 'balik' => $b, 'suma' => $cena, 'stav' => 'caka_prevod', 'vytvorene' => date('c')];
            nj_uprav('objednavky', fn($o) => $o + [$obj['id'] => $obj]);
            nj_audit('objednavka_prevod', ['partner' => $partner['id'], 'detail' => "$b {$cena} €"]);
            nj_email($partner['email'], 'najdijogu: objednávka ' . NJ_BALIKY[$b]['nazov'], '<p>Ďakujeme za objednávku balíka <b>' . NJ_BALIKY[$b]['nazov'] . '</b> na 12 mesiacov, ' . $cena . ' € (bez DPH, faktúru pošleme po úhrade).</p><p>Úhrada prevodom: IBAN pošleme v potvrdzujúcom e-maile do 1 pracovného dňa, variabilný symbol <b>' . substr(preg_replace('/\D/', '', $obj['id'] . '1'), 0, 10) . '</b>. Po pripísaní platby balík aktivujeme.</p>');
            nj_email(NJ_ADMINI[0], 'najdijogu: nová objednávka prevodom — ' . $partner['nazov'], '<p>' . h($partner['nazov']) . ' objednal ' . NJ_BALIKY[$b]['nazov'] . ' (' . $cena . ' €) prevodom. Aktivovať v <a href="' . NJ_STUDIO . '/?p=admin_partner&id=' . $partner['id'] . '">admine</a> po úhrade.</p>');
            nj_sprava('Objednávka prijatá. Platobné údaje prídu e-mailom; po úhrade balík aktivujeme.'); nj_presmeruj('predplatne');
        }
        // Stripe Checkout (jednorazová platba na 12 mesiacov, faktúra Stripe)
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        $poli = ['mode' => 'payment', 'success_url' => NJ_STUDIO . '/?p=predplatne&ok=1&sid={CHECKOUT_SESSION_ID}', 'cancel_url' => NJ_STUDIO . '/?p=predplatne', 'customer_email' => $partner['email'], 'invoice_creation[enabled]' => 'true', 'locale' => 'sk',
            'line_items[0][quantity]' => 1, 'line_items[0][price_data][currency]' => 'eur', 'line_items[0][price_data][unit_amount]' => $cena * 100, 'line_items[0][price_data][product_data][name]' => 'najdijogu.sk — ' . NJ_BALIKY[$b]['nazov'] . ' (12 mesiacov)',
            'metadata[partner]' => $partner['id'], 'metadata[balik]' => $b];
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $stripe . ':', CURLOPT_POSTFIELDS => http_build_query($poli), CURLOPT_TIMEOUT => 20]);
        $s = json_decode((string)curl_exec($ch), true); curl_close($ch);
        if (!empty($s['url'])) { nj_audit('stripe_checkout', ['partner' => $partner['id'], 'detail' => $b]); header('Location: ' . $s['url']); exit; }
        nj_sprava('Platobnú bránu sa nepodarilo otvoriť. Skúste prevod.', 'chyba'); nj_presmeruj('predplatne');
    }
}
if (!empty($_GET['ok']) && !empty($_GET['sid']) && $stripe) {
    // návrat z Checkoutu: over session a aktivuj (webhook to spraví aj bez tohto, toto je pre okamžitú odozvu)
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($_GET['sid'])); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $stripe . ':', CURLOPT_TIMEOUT => 20]);
    $s = json_decode((string)curl_exec($ch), true); curl_close($ch);
    if (($s['payment_status'] ?? '') === 'paid' && ($s['metadata']['partner'] ?? '') === $partner['id']) { require_once __DIR__ . '/../lib/predplatne.php'; nj_aktivuj_balik($partner['id'], $s['metadata']['balik'], 'stripe:' . $s['id']); $partner = nj_partner($partner['id']); nj_sprava('Zaplatené — balík je aktívny. Ďakujeme!'); }
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
<?php if (!($partner['balik'] === $k && $akt)): ?><form method="post"><input type="hidden" name="csrf" value="<?= nj_csrf() ?>"><input type="hidden" name="balik" value="<?= $k ?>"><div class="akcie"><?php if ($stripe): ?><button class="btn primar" name="sposob" value="karta">Zaplatiť kartou</button><?php endif; ?><button class="btn" name="sposob" value="prevod">Objednať prevodom</button></div></form><?php endif; ?></div>
<?php endforeach; ?>
</div>
<?php if ($obj): ?><div class="karta"><h3>Objednávky a platby</h3><table class="tab"><?php foreach ($obj as $o): ?><tr><td><?= nj_datum($o['vytvorene']) ?></td><td><?= h(NJ_BALIKY[$o['balik']]['nazov']) ?></td><td><?= (int)$o['suma'] ?> €</td><td><?= ['caka_prevod' => 'čaká na úhradu prevodom', 'zaplatene' => 'zaplatené', 'zrusene' => 'zrušené'][$o['stav']] ?? h($o['stav']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<p class="drobne">Ceny bez DPH. Faktúru dostanete e-mailom. Po 12 mesiacoch sa balík neobnovuje automaticky bez vášho súhlasu — pripomenieme sa 30 dní vopred.</p>
