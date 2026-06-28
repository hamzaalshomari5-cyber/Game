<?php
// fastcard_api.php

// تأمين جلب ملفات النظام الأساسية وقاعدة البيانات إذا لم تكن مدمجة تلقائياً
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} elseif (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
}

// دالة جلب الإعدادات من قاعدة البيانات
if (!function_exists('setting')) {
    function setting($key, $default = '') {
        try {
            if (function_exists('db')) {
                $stmt = db()->prepare("SELECT val FROM settings WHERE json_key = ? LIMIT 1");
                $stmt->execute([$key]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                return $res ? $res['val'] : $default;
            }
            return $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// دالة جلب المنتجات من موقع FastCard وعمل الحسبة والفلترة لها
function get_fastcard_products() {
    $profit_percent = (float)setting('profit_percent', 5);
    
    $cached = function_exists('cache_get') ? cache_get('fc_all_products') : null;
    if ($cached && is_array($cached)) {
        return $cached;
    }

    $api_url = "https://api.fastcard-provider.com/v1/products"; 
    
    // جلب مفتاح الـ API تلقائياً وبأمان من متغيرات البيئة في Railway
    $api_key = getenv('FASTCARD_API_KEY') ?: getenv('FASTCARD_TOKEN') ?: "";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $api_key,
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return [];
    }

    $data = json_decode($response, true);
    if (!isset($data['products']) || !is_array($data['products'])) {
        return [];
    }

    $mapped_products = [];
    foreach ($data['products'] as $p) {
        $mapped_products[] = _map_product($p, $profit_percent);
    }

    if (function_exists('cache_set')) {
        cache_set('fc_all_products', $mapped_products, 3600);
    }
    return $mapped_products;
}

/**
 * دالة الحسبة والتصفية الذكية لفصل أسعار الألعاب عن باقات الرصيد وشام كاش
 */
function _map_product($p, $profitPercent) {
    $usd_rate_general = (float)setting('usd_rate', 15000);
    $usd_rate_sham = (float)setting('usd_rate_sham', 15000); 
    
    $pname = isset($p['name']) ? (string)$p['name'] : '';
    $pname_lower = mb_strtolower($pname, 'UTF-8');

    // فحص تصنيف المنتج لتحديد سعر الصرف الخاص به
    $is_sham_or_balance = (
        strpos($pname_lower, 'رصيد') !== false ||
        strpos($pname_lower, 'شام') !== false ||
        strpos($pname_lower, 'متابعين') !== false ||
        strpos($pname_lower, 'لايكات') !== false ||
        strpos($pname_lower, 'انستغرام') !== false ||
        strpos($pname_lower, 'فيسبوك') !== false ||
        strpos($pname_lower, 'تيك') !== false ||
        strpos($pname_lower, 'تواصل') !== false ||
        strpos($pname_lower, 'سوشيال') !== false
    );

    $current_rate = $is_sham_or_balance ? $usd_rate_sham : $usd_rate_general;

    $costUsd = isset($p['price']) ? (float)$p['price'] : 0.0;
    $priceSyp = $costUsd * $current_rate;
    $finalSyp = ceil($priceSyp * (1 + $profitPercent / 100));

    $type = 'normal';
    if ($is_sham_or_balance) {
        $type = (strpos($pname_lower, 'باقات') !== false || strpos($pname_lower, 'رصيد غالي') !== false || strpos($pname_lower, 'كاش') !== false) 
            ? 'specificPackage' 
            : 'amount';
    }

    return [
        'id'       => isset($p['id']) ? (string)$p['id'] : '',
        'name'     => $pname,
        'price'    => $finalSyp,
        'cost_usd' => $costUsd,
        'desc'     => isset($p['description']) ? (string)$p['description'] : '',
        'oos'      => isset($p['available']) ? !$p['available'] : false,
        'qmin'     => isset($p['min_quantity']) ? (int)$p['min_quantity'] : 1,
        'qmax'     => isset($p['max_quantity']) ? (int)$p['max_quantity'] : 0,
        'param'    => isset($p['required_parameter']) ? (string)$p['required_parameter'] : '',
        'verify'   => isset($p['requires_verification']) ? ((string)$p['requires_verification'] === '1' ? '1' : '0') : '0',
        'type'     => $type
    ];
}

// دالة تنفيذ طلب شحن عبر الـ API لـ FastCard
function place_fastcard_order($productId, $qty, $playerId) {
    $api_url = "https://api.fastcard-provider.com/v1/orders";
    $api_key = getenv('FASTCARD_API_KEY') ?: getenv('FASTCARD_TOKEN') ?: "";

    $post_data = [
        'product_id' => $productId,
        'quantity'   => $qty,
        'parameter'  => $playerId
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $api_key,
        "Content-Type: application/json",
        "Accept: application/json"
    ]);
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 || $http_code === 201) {
        $resData = json_decode($response, true);
        return [
            'success'  => true,
            'order_id' => $resData['order_id'] ?? '',
            'status'   => $resData['status'] ?? 'completed'
        ];
    }

    return [
        'success' => false,
        'error'   => 'تعذر إرسال الطلب تلقائياً للمورد عبر الـ API'
    ];
}
 
