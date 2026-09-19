<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_over_csrf();
    $sl = [];
    foreach ((array)($_POST['sl_nazov'] ?? []) as $i => $n) {
        $n = mb_substr(trim((string)$n), 0, 80); if ($n === '') continue;
        $sl[] = ['nazov' => $n, 'dlzka' => mb_substr(trim((string)($_POST['sl_dlzka'][$i] ?? '')), 0, 30), 'cena' => mb_substr(trim((string)($_POST['sl_cena'][$i] ?? '')), 0, 30), 'popis' => mb_substr(trim((string)($_POST['sl_popis'][$i] ?? '')), 0, 300)];
    }
    $ak = [];
    foreach ((array)($_POST['ak_nazov'] ?? []) as $i => $n) {
        $n = mb_substr(trim((string)$n), 0, 100); if ($n === '') continue;
        $ak[] = ['nazov' => $n, 'od' => mb_substr((string)($_POST['ak_od'][$i] ?? ''), 0, 10), 'do' => mb_substr((string)($_POST['ak_do'][$i] ?? ''), 0, 10), 'popis' => mb_substr(trim((string)($_POST['ak_popis'][$i] ?? '')), 0, 400)];
    }
    $partner['sluzby'] = array_slice($sl, 0, 30); $partner['akcie'] = array_slice($ak, 0, 10);
    $partner = nj_partner_uloz($partner); nj_audit('sluzby_uprava', ['partner' => $partner['id'], 'detail' => count($sl) . ' služieb, ' . count($ak) . ' akcií']);
    nj_sprava('Služby a akcie uložené.'); nj_presmeruj('sluzby');
}
$sl = $partner['sluzby'] ?: [[]]; $ak = $partner['akcie'] ?: [[]];
?>
<h1>Služby, ceny, akcie</h1>
<form method="post" class="formular">
<input type="hidden" name="csrf" value="<?= nj_csrf() ?>">
<h3>Služby a cenník</h3>
<table class="tab edit" id="t-sluzby"><thead><tr><th>Služba</th><th>Dĺžka</th><th>Cena</th><th>Popis</th><th></th></tr></thead><tbody>
<?php foreach ($sl as $s): ?><tr><td><input name="sl_nazov[]" value="<?= h($s['nazov'] ?? '') ?>" placeholder="Ashtanga Mysore ráno"></td><td><input name="sl_dlzka[]" value="<?= h($s['dlzka'] ?? '') ?>" placeholder="90 min" size="8"></td><td><input name="sl_cena[]" value="<?= h($s['cena'] ?? '') ?>" placeholder="12 €" size="8"></td><td><input name="sl_popis[]" value="<?= h($s['popis'] ?? '') ?>" placeholder="pre koho, čo si priniesť"></td><td><button type="button" class="btn mini zmaz">✕</button></td></tr><?php endforeach; ?>
</tbody></table>
<button type="button" class="btn mini pridaj" data-tab="t-sluzby">+ služba</button>
<h3>Akcie a novinky</h3>
<table class="tab edit" id="t-akcie"><thead><tr><th>Akcia</th><th>Od</th><th>Do</th><th>Popis</th><th></th></tr></thead><tbody>
<?php foreach ($ak as $a): ?><tr><td><input name="ak_nazov[]" value="<?= h($a['nazov'] ?? '') ?>" placeholder="Prvá lekcia zadarmo"></td><td><input type="date" name="ak_od[]" value="<?= h($a['od'] ?? '') ?>"></td><td><input type="date" name="ak_do[]" value="<?= h($a['do'] ?? '') ?>"></td><td><input name="ak_popis[]" value="<?= h($a['popis'] ?? '') ?>"></td><td><button type="button" class="btn mini zmaz">✕</button></td></tr><?php endforeach; ?>
</tbody></table>
<button type="button" class="btn mini pridaj" data-tab="t-akcie">+ akcia</button>
<div class="akcie"><button class="btn primar">Uložiť</button></div>
</form>
