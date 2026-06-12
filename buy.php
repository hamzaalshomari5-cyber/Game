<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: application/json; charset=utf-8');

function out($ok, $msg, $extra = []) {
    echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE); exit;
}

$U = current_user();
if (!$U) out(false, 'سجّل دخول أولاً', ['login' => true]);

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$pid    = (string)($in['product_id'] ?? '');
$qty    = max(1, (int)($in['qty'] ?? 1));
$player = trim((string)($in['player_id'] ?? ''));

$p = store_product($pid);
if (!$p) out(false, 'المنتج غير موجود');
if (!$p['available']) out(false, 'المنتج غير متوفر حالياً ❌');

$total = $p['price'] * $qty;
if ($U['balance'] < $total) out(false, 'رصيد محفظتك غير كافٍ — المطلوب ' . number_format($total) . ' ل.س. اشحن محفظتك أولاً.');

$uuid = bin2hex(random_bytes(16));

// خصم الرصيد وحجز الطلب
db()->beginTransaction();
db()->prepare("UPDATE users SET balance = balance - ? WHERE id=? AND balance >= ?")
    ->execute([$total, $U['id'], $total]);
$st = db()->prepare("INSERT INTO orders (user_id,product_id,product_name,qty,player_id,price,total,uuid)
    VALUES (?,?,?,?,?,?,?,?)");
$st->execute([$U['id'], $pid, $p['name'], $qty, $player, $p['price'], $total, $uuid]);
$orderId = db()->lastInsertId();
db()->commit();

// إرسال الطلب لـ FastCard
$r = fc_new_order($pid, $qty, $player, $uuid);
$success = $r['code'] >= 200 && $r['code'] < 300 && is_array($r['data'])
           && (($r['data']['status'] ?? '') !== 'error') && empty($r['data']['error']);

if (!$success) {
    // فشل الإرسال → إعادة المبلغ
    db()->beginTransaction();
    db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$total, $U['id']]);
    db()->prepare("UPDATE orders SET status='reject', updated_at=datetime('now') WHERE id=?")->execute([$orderId]);
    db()->commit();
    out(false, 'تعذّر تنفيذ الطلب وتمت إعادة المبلغ لمحفظتك. حاول لاحقاً أو تواصل مع الدعم.');
}

$fcId = $r['data']['order_id'] ?? $r['data']['id'] ?? ($r['data']['order']['id'] ?? null);
if ($fcId) db()->prepare("UPDATE orders SET fc_order_id=? WHERE id=?")->execute([$fcId, $orderId]);

out(true, 'تم إرسال طلبك بنجاح ✅ — تابع حالته من صفحة "طلباتي"', ['order_id' => $orderId]);
