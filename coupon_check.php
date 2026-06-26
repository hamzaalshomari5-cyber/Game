<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: application/json; charset=utf-8');

function cc_out($ok, $msg, $extra = []) {
    echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE); exit;
}

$U = current_user();
if (!$U) cc_out(false, 'سجّل دخول أولاً', ['login' => true]);

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$pid    = (string)($in['product_id'] ?? '');
$qty    = max(1, (float)($in['qty'] ?? 1));
$player = trim((string)($in['player_id'] ?? ''));
$code   = (string)($in['code'] ?? '');

$p = store_product($pid);
if (!$p) cc_out(false, 'المنتج غير موجود');

// نفس حساب الإجمالي المستخدم في buy.php (مع خصم العرض المؤقت إن وجد)
$unitPrice = $p['price'];
$disc = promo_discount_pct();
if ($disc > 0) $unitPrice = $p['price'] * (1 - $disc / 100);
$total = round($unitPrice * $qty);

$r = check_price_coupon($code, $player, $total);
if (!$r['ok']) cc_out(false, $r['msg']);

cc_out(true, 'تم تطبيق كود الخصم ✅', [
    'discount'  => $r['discount'],
    'new_total' => $r['new_total'],
    'old_total' => (int)$total,
]);
