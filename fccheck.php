<?php
require_once __DIR__ . '/fastcard_api.php';
require_login();
$U = current_user();
if (($U['role'] ?? '') !== 'admin') { http_response_code(403); exit('للأدمن فقط'); }

header('Content-Type: text/plain; charset=utf-8');

echo "=== تشخيص اتصال FastCard ===\n\n";

// 1) التوكن
$token = fastcard_token();
echo "1) التوكن: " . ($token !== '' && $token !== 'ضع_توكن_FASTCARD_هنا' ? 'موجود (طول ' . strlen($token) . ')' : 'فارغ أو غير مضبوط ❌') . "\n\n";

// 2) رصيد الحساب (يختبر صحة التوكن)
echo "2) اختبار البروفايل/الرصيد:\n";
$prof = fc_request('profile');
echo "   HTTP: " . $prof['code'] . "\n";
echo "   الرد: " . mb_substr($prof['raw'] ?? '', 0, 300) . "\n\n";

// 3) اختبار طلب فعلي (إن مُرر product_id و player)
$pid = $_GET['pid'] ?? '';
$player = $_GET['player'] ?? '';
$qty = (int)($_GET['qty'] ?? 1);
if ($pid !== '') {
    echo "3) اختبار إرسال طلب:\n";
    echo "   product_id: $pid | qty: $qty | player: $player\n";
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
    echo "   uuid: $uuid\n";
    $r = fc_new_order($pid, $qty, $player, $uuid);
    echo "   HTTP: " . $r['code'] . "\n";
    echo "   الرد الخام:\n   " . str_replace("\n", "\n   ", $r['raw'] ?? '(فارغ)') . "\n\n";
    echo "   ملاحظة: إذا نجح، هذا طلب حقيقي! تحقق منه.\n";
} else {
    echo "3) لاختبار طلب فعلي، أضف للرابط:\n";
    echo "   ?pid=PRODUCT_ID&player=PLAYER_ID&qty=1\n";
    echo "   مثال: fccheck.php?pid=2832&player=51851037296&qty=1\n";
}
