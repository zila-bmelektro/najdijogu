<?php require __DIR__ . '/../lib/jadro.php'; nj_session(); if (!nj_je_admin()) { http_response_code(403); exit; }
header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="najdijogu-partneri-' . date('Y-m-d') . '.csv"');
$o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['id', 'nazov', 'mesto', 'email', 'telefon', 'web', 'stav', 'balik', 'plati_do', 'google_rating', 'vytvorene'], ';');
foreach (nj_partneri() as $p) fputcsv($o, [$p['id'], $p['nazov'], $p['mesto'], $p['email'], $p['telefon'], $p['web'], $p['stav'], $p['balik'], $p['plati_do'], $p['google_rating'], $p['vytvorene']], ';');
