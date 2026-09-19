<?php
require_once __DIR__ . '/jadro.php';
/* Aktivuje balík na 12 mesiacov (od dnes alebo od konca aktuálneho, ak ešte platí). */
function nj_aktivuj_balik(string $partnerId, string $balik, string $zdroj): void {
    nj_uprav('partneri', function ($all) use ($partnerId, $balik, $zdroj) {
        if (empty($all[$partnerId])) return $all;
        $p = &$all[$partnerId];
        $od = (!empty($p['plati_do']) && strtotime($p['plati_do']) > time()) ? strtotime($p['plati_do']) : time();
        $p['balik'] = $balik; $p['plati_do'] = date('Y-m-d', strtotime('+12 months', $od)); $p['neobnovovat'] = false;
        if ($p['stav'] === 'registrovana' || $p['stav'] === 'nova') $p['stav'] = 'schvalena';
        $p['platby'][] = ['kedy' => date('c'), 'balik' => $balik, 'zdroj' => $zdroj, 'plati_do' => $p['plati_do']];
        $p['upravene'] = date('c');
        return $all;
    });
    $p = nj_partner($partnerId);
    nj_audit('predplatne_aktivacia', ['partner' => $partnerId, 'detail' => "$balik do {$p['plati_do']} ($zdroj)"]);
    if ($p) nj_email($p['email'], 'najdijogu: balík ' . NJ_BALIKY[$balik]['nazov'] . ' je aktívny', '<p>Balík <b>' . NJ_BALIKY[$balik]['nazov'] . '</b> pre <b>' . h($p['nazov']) . '</b> je aktívny do <b>' . nj_datum($p['plati_do']) . '</b>.</p><p><a href="' . NJ_STUDIO . '/">Otvoriť Studio</a></p>');
}
/* Denná kontrola (CRON): pripomienky 30 a 7 dní pred koncom, expirácia → listing. */
function nj_predplatne_denne(): array {
    $dnes = date('Y-m-d'); $vysl = [];
    foreach (nj_partneri() as $p) {
        if (($p['balik'] ?? 'listing') === 'listing' || empty($p['plati_do'])) continue;
        $dni = (int)floor((strtotime($p['plati_do']) - strtotime($dnes)) / 86400);
        if (in_array($dni, [30, 7], true) && empty($p['pripomienka_' . $dni])) {
            nj_email($p['email'], "najdijogu: predplatné končí o $dni dní", '<p>Balík <b>' . NJ_BALIKY[$p['balik']]['nazov'] . '</b> pre <b>' . h($p['nazov']) . '</b> platí do <b>' . nj_datum($p['plati_do']) . '</b>. ' . ($p['neobnovovat'] ? 'Máte nastavené neobnovovať — profil ostane ako bezplatný listing.' : 'Obnoviť ho môžete v Studiu → Predplatné.') . '</p><p><a href="' . NJ_STUDIO . '/?p=predplatne">Otvoriť predplatné</a></p>');
            $p['pripomienka_' . $dni] = $dnes; nj_partner_uloz($p); $vysl[] = "{$p['id']} pripomienka $dni";
        }
        if ($dni < 0) { $p['balik'] = 'listing'; $p['expirovalo'] = $dnes; nj_partner_uloz($p); nj_audit('predplatne_expiracia', ['partner' => $p['id']]); $vysl[] = "{$p['id']} expirácia"; }
    }
    return $vysl;
}
