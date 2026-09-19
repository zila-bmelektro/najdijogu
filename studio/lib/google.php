<?php
/* M-GOOGLE: Places API (New). Kľúč data/google_places_key.txt. Cache 30 dní (pravidlá Google: place_id natrvalo, ostatné max 30 dní). */
require_once __DIR__ . '/jadro.php';

function nj_places_hladaj(string $text): array {
    $key = nj_tajomstvo('google_places_key'); if (!$key) return [];
    $ch = curl_init('https://places.googleapis.com/v1/places:searchText');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Goog-Api-Key: ' . $key, 'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.location'],
        CURLOPT_POSTFIELDS => json_encode(['textQuery' => $text, 'languageCode' => 'sk', 'regionCode' => 'SK', 'maxResultCount' => 5])]);
    $r = json_decode((string)curl_exec($ch), true); curl_close($ch);
    return array_map(fn($p) => ['id' => $p['id'], 'nazov' => $p['displayName']['text'] ?? '', 'adresa' => $p['formattedAddress'] ?? '', 'lat' => $p['location']['latitude'] ?? null, 'lng' => $p['location']['longitude'] ?? null], $r['places'] ?? []);
}
/* rating + počet + 5 recenzií; cache 30 dní v data/cache/place-<id>.json */
function nj_places_detail(string $placeId, bool $vynut = false): ?array {
    $f = nj_data_dir() . '/cache/place-' . preg_replace('/[^A-Za-z0-9_-]/', '', $placeId) . '.json';
    if (!$vynut && is_file($f) && filemtime($f) > time() - 30 * 86400) return json_decode((string)file_get_contents($f), true);
    $key = nj_tajomstvo('google_places_key'); if (!$key) return is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    $ch = curl_init('https://places.googleapis.com/v1/places/' . rawurlencode($placeId) . '?languageCode=sk');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['X-Goog-Api-Key: ' . $key, 'X-Goog-FieldMask: id,displayName,rating,userRatingCount,googleMapsUri,reviews,formattedAddress,location,websiteUri,nationalPhoneNumber,currentOpeningHours']]);
    $r = json_decode((string)curl_exec($ch), true); curl_close($ch);
    if (empty($r['id'])) return is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    $d = ['id' => $r['id'], 'nazov' => $r['displayName']['text'] ?? '', 'rating' => $r['rating'] ?? null, 'pocet' => $r['userRatingCount'] ?? null, 'maps' => $r['googleMapsUri'] ?? '', 'adresa' => $r['formattedAddress'] ?? '', 'lat' => $r['location']['latitude'] ?? null, 'lng' => $r['location']['longitude'] ?? null, 'web' => $r['websiteUri'] ?? '', 'telefon' => $r['nationalPhoneNumber'] ?? '', 'hodiny' => $r['currentOpeningHours']['weekdayDescriptions'] ?? [], 'stiahnute' => date('c'),
        'recenzie' => array_map(fn($x) => ['autor' => $x['authorAttribution']['displayName'] ?? '', 'autor_url' => $x['authorAttribution']['uri'] ?? '', 'foto' => $x['authorAttribution']['photoUri'] ?? '', 'hviezdy' => $x['rating'] ?? null, 'text' => $x['text']['text'] ?? '', 'kedy' => $x['relativePublishTimeDescription'] ?? ''], $r['reviews'] ?? [])];
    file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); chmod($f, 0600);
    return $d;
}
/* denný refresh ratingov všetkých partnerov s place_id (CRON) — len tie staršie ako 30 dní */
function nj_places_denne(): int {
    $n = 0;
    foreach (nj_partneri() as $p) {
        if (empty($p['google_place_id'])) continue;
        $f = nj_data_dir() . '/cache/place-' . preg_replace('/[^A-Za-z0-9_-]/', '', $p['google_place_id']) . '.json';
        if (is_file($f) && filemtime($f) > time() - 30 * 86400) continue;
        $d = nj_places_detail($p['google_place_id'], true); if (!$d) continue;
        $p['google_rating'] = $d['rating']; $p['google_pocet'] = $d['pocet']; nj_partner_uloz($p); $n++;
    }
    return $n;
}
