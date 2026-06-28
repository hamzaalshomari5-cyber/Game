<?php
// وظائف مساعدة ناقصة
require_once __DIR__ . '/db.php';

if (!function_exists('fc_content')) {
    function fc_content($cat = 0) {
        $products = get_fastcard_products();
        $categories = [];
        $filtered = [];

        foreach ($products as $p) {
            if (!isset($p['id'])) continue;
            $filtered[] = $p;
            $parts = preg_split('/[\\|>\\/]/', $p['name']);
            $name = trim($parts[0] ?? '');
            if ($name !== '') {
                $categories[$name] = [
                    'id' => $name,
                    'name' => $name
                ];
            }
        }

        if ($cat && $cat !== 0) {
            $filtered = array_values(array_filter($filtered, function($p) use ($cat) {
                return strpos((string)$p['name'], (string)$cat) !== false;
            }));
        }

        return [
            'categories' => array_values($categories),
            'products' => $filtered
        ];
    }
}

if (!function_exists('product_card')) {
    function product_card($p, $favs = []) {
        echo '<div class="card product-card">';
        echo '<div class="product-name">'.e($p['name'] ?? '').'</div>';
        echo '<div class="product-price">'.e($p['price'] ?? 0).' ل.س</div>';
        if (!empty($p['oos'])) echo '<div class="empty">غير متوفر</div>';
        echo '</div>';
    }
}
