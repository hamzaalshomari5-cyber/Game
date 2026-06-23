<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== تشخيص أسعار منتجات FastCard ===\n\n";

// نجيب المنتجات الخام من الكاش/API
$cat = $_GET['cat'] ?? '0';
echo "القسم: $cat\n\n";

$content = fc_content($cat, true); // force = نتجاهل الكاش
$raw = $content['raw'] ?? $content;

// نطبع أول منتج خام كامل لنشوف كل الحقول
function find_products($data, &$found) {
    if (!is_array($data)) return;
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            if (isset($v['id']) && (isset($v['name']) || isset($v['price']) || isset($v['product_type']))) {
                $found[] = $v;
            } else {
                find_products($v, $found);
            }
        }
    }
}

$products = [];
find_products($content, $products);

echo "عدد المنتجات الملقاة: " . count($products) . "\n\n";

// نطبع المنتجات اللي سعرها 0 أو فاضي
echo "=== المنتجات اللي سعرها صفر/مفقود ===\n";
$zeroCount = 0;
foreach ($products as $p) {
    $price = $p['price'] ?? null;
    if ($price === null || (float)$price == 0) {
        $zeroCount++;
        if ($zeroCount <= 3) {
            echo "\n--- منتج سعره صفر ---\n";
            echo "الاسم: " . ($p['name'] ?? '?') . "\n";
            echo "كل الحقول:\n";
            echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
    }
}
echo "\nإجمالي المنتجات بسعر صفر: $zeroCount\n\n";

// نطبع منتج عادي (سعره موجود) للمقارنة
echo "=== مثال منتج سعره موجود (للمقارنة) ===\n";
foreach ($products as $p) {
    if (isset($p['price']) && (float)$p['price'] > 0) {
        echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        break;
    }
}
