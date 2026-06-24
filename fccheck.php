<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== تشخيص منتج FastCard محدد ===\n\n";

$pid = $_GET['pid'] ?? '';
if ($pid === '') {
    echo "أضف رقم المنتج: fccheck.php?pid=رقم_المنتج\n";
    echo "(رقم المنتج = product_id، بتلاقيه برابط الشراء)\n";
    exit;
}

$p = store_product($pid);
if (!$p) {
    echo "❌ المنتج $pid غير موجود.\n";
    exit;
}

echo "=== تفاصيل المنتج (بعد المعالجة) ===\n";
echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "=== التحليل ===\n";
echo "الاسم: " . $p['name'] . "\n";
echo "النوع (type): " . ($p['type'] ?: '(فارغ)') . "\n";
echo "سعر الوحدة: " . $p['price'] . " ل.س\n";
echo "أقل كمية (qty_min): " . $p['qty_min'] . "\n";
echo "أكبر كمية (qty_max): " . ($p['qty_max'] > 0 ? $p['qty_max'] : 'بلا حد') . "\n\n";

$testQty = (int)($_GET['qty'] ?? 110);
echo "=== فحص الكمية المطلوبة: $testQty ===\n";
if ($testQty < $p['qty_min']) {
    echo "❌ الكمية $testQty أقل من الحد الأدنى (" . $p['qty_min'] . ")\n";
    echo "💡 هذا سبب 'quantity not allowed'!\n";
} elseif ($p['qty_max'] > 0 && $testQty > $p['qty_max']) {
    echo "❌ الكمية $testQty أكبر من الحد الأقصى (" . $p['qty_max'] . ")\n";
} else {
    echo "✅ الكمية $testQty ضمن الحدود المسموحة.\n";
    echo "إذاً سبب الرفض شي ثاني (مضاعفات معينة؟ صيغة؟).\n";
}
