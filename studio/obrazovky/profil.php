<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    nj_over_csrf();
    $z = fn($k, $max = 500) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $partner['nazov'] = $z('nazov', 80) ?: $partner['nazov'];
    foreach (['telefon' => 40, 'web' => 200, 'rezervacie_url' => 200, 'mesto' => 60, 'adresa' => 160, 'hodiny' => 400, 'instagram' => 120, 'facebook' => 120] as $k => $m) $partner[$k] = $z($k, $m);
    $partner['popis'] = $z('popis', 2500);
    $partner['styly'] = array_values(array_intersect(NJ_STYLY, (array)($_POST['styly'] ?? [])));
    foreach (['web', 'rezervacie_url', 'instagram', 'facebook'] as $k) if ($partner[$k] && !preg_match('~^https?://~', $partner[$k])) $partner[$k] = 'https://' . $partner[$k];
    $lat = (float)($_POST['lat'] ?? 0); $lng = (float)($_POST['lng'] ?? 0);
    if ($lat && $lng) { $partner['lat'] = $lat; $partner['lng'] = $lng; }
    // fotky: nahranie (max 5, jpg/png/webp, 4 MB)
    if (!empty($_FILES['fotka']['tmp_name']) && is_uploaded_file($_FILES['fotka']['tmp_name'])) {
        $info = @getimagesize($_FILES['fotka']['tmp_name']);
        if ($info && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) && $_FILES['fotka']['size'] <= 4 * 1024 * 1024 && count($partner['fotky']) < 5) {
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime']];
            $meno = $partner['id'] . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
            $ciel = dirname(__DIR__, 2) . '/obrazky/jogovne'; if (!is_dir($ciel)) mkdir($ciel, 0755, true);
            // zmenšenie na max 1600 px (šetrí miesto aj načítanie)
            $src = $info['mime'] === 'image/png' ? imagecreatefrompng($_FILES['fotka']['tmp_name']) : ($info['mime'] === 'image/webp' ? imagecreatefromwebp($_FILES['fotka']['tmp_name']) : imagecreatefromjpeg($_FILES['fotka']['tmp_name']));
            $w = imagesx($src); $hh = imagesy($src); $k = min(1, 1600 / max($w, $hh)); $dst = imagescale($src, (int)($w * $k), (int)($hh * $k));
            imagejpeg($dst, "$ciel/" . preg_replace('/\.\w+$/', '.jpg', $meno), 82); $meno = preg_replace('/\.\w+$/', '.jpg', $meno);
            $partner['fotky'][] = $meno;
        } else nj_sprava('Fotka sa nenahrala (jpg/png/webp do 4 MB, max 5 fotiek).', 'chyba');
    }
    if (!empty($_POST['zmaz_fotku'])) { $partner['fotky'] = array_values(array_diff($partner['fotky'], [$_POST['zmaz_fotku']])); @unlink(dirname(__DIR__, 2) . '/obrazky/jogovne/' . basename($_POST['zmaz_fotku'])); }
    $partner = nj_partner_uloz($partner);
    nj_audit('profil_uprava', ['partner' => $partner['id']]);
    if (empty($_SESSION['sprava'])) nj_sprava('Profil uložený.');
    nj_presmeruj('profil');
}
?>
<h1>Profil jogovne</h1>
<form method="post" enctype="multipart/form-data" class="formular" id="f-profil">
<input type="hidden" name="csrf" value="<?= nj_csrf() ?>">
<div class="dvojica">
  <label>Názov jogovne <input name="nazov" value="<?= h($partner['nazov']) ?>" required maxlength="80"></label>
  <label>Mesto <input name="mesto" value="<?= h($partner['mesto']) ?>" maxlength="60"></label>
</div>
<label>Adresa (ulica, číslo, PSČ, mesto) <input name="adresa" id="adresa" value="<?= h($partner['adresa']) ?>" maxlength="160"> <button type="button" class="btn mini" id="btn-geokod">Nájsť na mape</button> <span id="geo-stav" class="drobne"><?= $partner['lat'] ? 'poloha: ' . round($partner['lat'], 4) . ', ' . round($partner['lng'], 4) : 'poloha ešte nie je určená — bez nej sa jogovňa nezobrazí vo vyhľadávaní podľa okolia' ?></span></label>
<input type="hidden" name="lat" id="lat" value="<?= h((string)$partner['lat']) ?>"><input type="hidden" name="lng" id="lng" value="<?= h((string)$partner['lng']) ?>">
<label>Popis (čo u vás človek nájde, pre koho, atmosféra) <textarea name="popis" rows="6" maxlength="2500"><?= h($partner['popis']) ?></textarea></label>
<fieldset><legend>Štýly jogy</legend><div class="chipy"><?php foreach (NJ_STYLY as $s): ?><label class="chip"><input type="checkbox" name="styly[]" value="<?= h($s) ?>" <?= in_array($s, $partner['styly'], true) ? 'checked' : '' ?>> <?= h($s) ?></label><?php endforeach; ?></div></fieldset>
<div class="dvojica">
  <label>Telefón <input name="telefon" value="<?= h($partner['telefon']) ?>" maxlength="40"></label>
  <label>Web <input name="web" value="<?= h($partner['web']) ?>" maxlength="200" placeholder="https://"></label>
  <label>Rezervačný systém (odkaz) <input name="rezervacie_url" value="<?= h($partner['rezervacie_url']) ?>" maxlength="200" placeholder="https://"></label>
  <label>Otváracie hodiny / rozvrh (text) <input name="hodiny" value="<?= h($partner['hodiny']) ?>" maxlength="400" placeholder="Po–Pi 7:00–20:00, So 9:00–12:00"></label>
  <label>Instagram <input name="instagram" value="<?= h($partner['instagram'] ?? '') ?>" maxlength="120"></label>
  <label>Facebook <input name="facebook" value="<?= h($partner['facebook'] ?? '') ?>" maxlength="120"></label>
</div>
<fieldset><legend>Fotky (max 5, prvá je hlavná)</legend>
<div class="fotky"><?php foreach ($partner['fotky'] as $f): ?><figure><img src="<?= NJ_WEB ?>/obrazky/jogovne/<?= h($f) ?>" alt=""><button type="submit" name="zmaz_fotku" value="<?= h($f) ?>" class="btn mini" title="zmazať">✕</button></figure><?php endforeach; ?></div>
<?php if (count($partner['fotky']) < 5): ?><label>Pridať fotku <input type="file" name="fotka" accept="image/jpeg,image/png,image/webp"></label><?php endif; ?>
</fieldset>
<div class="akcie"><button class="btn primar">Uložiť</button> <a class="btn" href="<?= NJ_WEB ?>/jogovne/<?= h($partner['slug']) ?>" target="_blank">Náhľad verejného profilu ↗</a></div>
<p class="drobne">Verejná adresa profilu: <?= NJ_WEB ?>/jogovne/<?= h($partner['slug']) ?></p>
</form>
