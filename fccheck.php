<?php
error_reporting(E_ALL); ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/fastcard_api.php';

echo "=== تشخيص FastCard ===\n\n";

// التوكن
$token = fastcard_token();
echo "1) التوكن: " . ($token !== '' && $token !== 'ضع_توكن_FASTCARD_هنا' ? 'موجود (طول ' . strlen($token) . ')' : 'فارغ ❌') . "\n\n";

// البروفايل (يختبر التوكن)
echo "2) اختبار الرصيد:\n";
$prof = fc_request('profile');
echo "   HTTP: " . ($prof['code'] ?? '?') . "\n";
echo "   الرد: " . mb_substr($prof['raw'] ?? '(فارغ)', 0, 400) . "\n\n";

// اختبار طلب
$pid = $_GET['pid'] ?? '';
$player = $_GET['player'] ?? '';
$qty = (int)($_GET['qty'] ?? 1);
if ($pid !== '') {
    echo "3) اختبار طلب:\n";
    echo "   pid=$pid qty=$qty player=$player\n";
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
    $r = fc_new_order($pid, $qty, $player, $uuid);
    echo "   HTTP: " . ($r['code'] ?? '?') . "\n";
    echo "   الرد:\n   " . str_replace("\n", "\n   ", mb_substr($r['raw'] ?? '(فارغ)', 0, 600)) . "\n";
} else {
    echo "3) لاختبار طلب: ?pid=2832&player=51851037296&qty=1\n";
}
echo "\n=== انتهى ===\n";
