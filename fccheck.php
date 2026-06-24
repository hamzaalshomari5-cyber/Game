<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== فحص قسم رصيد سيرياتيل ===\n\n";

$cat = $_GET['cat'] ?? '473';
$content = fc_content($cat, true);

// نطبع الرد الخام الكامل من فاست كارد (أول 2 منتج)
echo "=== الرد الخام من فاست كارد (cat=$cat) ===\n";
$raw = is_array($content['data'] ?? null) ? $content['data'] : $content;
echo substr(json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 0, 3000) . "\n\n";

echo str_repeat("=", 50) . "\n";
echo "=== المنتجات بعد معالجة الكود (store_products) ===\n\n";

$prods = store_products(true);
$found = 0;
foreach ($prods as $p) {
    // نعرض منتجات هذا القسم فقط (أو كل اللي اسمها فيه رصيد/سيرياتيل)
    if (stripos($p['name'], 'SYRIATEL') !== false || stripos($p['name'], 'رصيد') !== false || stripos($p['name'], 'سيرياتيل') !== false) {
        $found++;
        echo "🔹 " . $p['name'] . "\n";
        echo "   ID: " . $p['id'] . "\n";
        echo "   النوع: " . ($p['type'] ?: 'عادي') . "\n";
        echo "   السعر: " . $p['price'] . "\n";
        echo "   qty_min: " . $p['qty_min'] . " | qty_max: " . ($p['qty_max'] ?: 'بلا حد') . "\n";
        echo "   params: " . json_encode($p['params'], JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}
if (!$found) echo "ما لقيت منتجات رصيد بهذا القسم.\n";
