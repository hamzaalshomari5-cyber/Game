<?php
require_once __DIR__ . '/fastcard_web.php';

$player  = trim((string)($_GET['player'] ?? ''));
$product = (int)($_GET['product'] ?? 0);
$dbg     = isset($_GET['debug']) ? [] : null;

if ($player === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'أدخل ID اللاعب أولاً'], JSON_UNESCAPED_UNICODE); exit;
}

// منتج التحقق المخصص بموقع FastCard (نفس المستخدم بالبوت)
define('VERIFY_PRODUCT_ID', 7816);

$res = fcw_check_player($player, $product ?: VERIFY_PRODUCT_ID, $dbg);

// إذا فشل على منتجك → جرّب منتج التحقق المخصص
if (!$res['ok'] && empty($res['soft']) && $product && $product != VERIFY_PRODUCT_ID) {
    if (is_array($dbg)) $dbg[] = "--- retry with verify product " . VERIFY_PRODUCT_ID . " ---";
    $res = fcw_check_player($player, VERIFY_PRODUCT_ID, $dbg);
}

if (is_array($dbg)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "RESULT: " . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n\n" . implode("\n\n", $dbg);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($res, JSON_UNESCAPED_UNICODE);
