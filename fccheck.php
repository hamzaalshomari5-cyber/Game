<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== مقارنة الأسعار: cost vs price ===\n";
echo "سعر الصرف: " . usd_rate() . " | الربح: " . setting('profit_percent', DEFAULT_PROFIT) . "%\n\n";

$cats = ['473', '1392']; // رصيد سيرياتيل + التطبيقات
$shown = 0;

foreach ($cats as $cat) {
    $content = fc_content($cat, true);
    $prods = [];
    function fp($d, &$f) {
        if (!is_array($d)) return;
        foreach ($d as $v) if (is_array($v)) {
            if (isset($v['id']) && isset($v['name'])) $f[] = $v;
            else fp($v, $f);
        }
    }
    fp($content, $prods);

    echo "===== القسم $cat =====\n";
    $c = 0;
    foreach ($prods as $p) {
        if ($c++ >= 3) break;
        $rate = usd_rate();
        $profit = (float)setting('profit_percent', DEFAULT_PROFIT);
        $byCost = round((float)($p['cost'] ?? 0) * $rate * (1+$profit/100));
        $byPrice = round((float)($p['price'] ?? 0) * $rate * (1+$profit/100));
        echo "🔹 " . $p['name'] . " (نوع: " . ($p['product_type'] ?? $p['type'] ?? '?') . ")\n";
        echo "   cost=" . ($p['cost'] ?? '?') . " | price=" . ($p['price'] ?? '?') . "\n";
        echo "   لو حسبنا بـ cost = " . number_format($byCost) . " ل.س\n";
        echo "   لو حسبنا بـ price = " . number_format($byPrice) . " ل.س\n\n";
    }
}
