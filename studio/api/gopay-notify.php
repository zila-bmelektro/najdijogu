<?php
/* GoPay server-to-server notifikácia: gopay-notify.php?id=<payment_id>. Stav si overíme v GoPay API. GoPay čaká HTTP 200, inak opakuje. */
require __DIR__ . '/../lib/gopay.php';
http_response_code(200); header('Content-Type: text/plain; charset=utf-8');
$id = (string)($_GET['id'] ?? '');
if (nj_gopay_zapnute() && ctype_digit($id)) { try { nj_gopay_over($id); } catch (Throwable $e) { nj_audit('gopay_notify_chyba', ['detail' => mb_substr($e->getMessage(), 0, 200)]); } }
echo 'OK';
