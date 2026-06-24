<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== الحقول الخام الكاملة لرصيد سيرياتيل ===\n\n";

$cat = $_GET['cat'] ?? '473';
$content = fc_content($cat, true);

function scan($d, &$f) {
    if (!is_array($d)) return;
    foreach ($d as $v) if (is_array($v)) {
        if (isset($v['id']) && isset($v['name'])) $f[] = $v;
        else scan($v, $f);
    }
}
$prods = [];
scan($content, $prods);

// نطبع أول منتج بكل حقوله الخام (كامل بدون اختصار)
if ($prods) {
    echo "=== المنتج الأول كامل ===\n";
    echo json_encode($prods[0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    
    echo "=== كل المفاتيح (الحقول) الموجودة ===\n";
    foreach (array_keys($prods[0]) as $k) {
        $val = $prods[0][$k];
        $type = gettype($val);
        $preview = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string)$val;
        if (strlen($preview) > 100) $preview = substr($preview, 0, 100) . '...';
        echo "  • $k ($type): $preview\n";
    }
}
echo "\n=== ملاحظة ===\n";
echo "ندوّر على حقل فيه القيم: 1.92, 2.88, 3.84 ...\n";
echo "(قد يكون اسمه: qty_values / qty_list / steps / packages / options)\n";
