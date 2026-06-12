<?php
require_once __DIR__ . '/db.php';

function fc_request($path, $params = []) {
    $url = rtrim(FASTCARD_BASE, '/') . '/' . ltrim($path, '/');
    if ($params) $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'api-token: ' . fastcard_token(),
            'Accept: application/json',
        ],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($res, true);
    return ['code' => $code, 'data' => $json, 'raw' => $res];
}

/** رصيد حسابك عند FastCard */
function fc_profile() {
    $r = fc_request('profile');
    return $r['data'] ?? null;
}

/** كل المنتجات من FastCard مع كاش */
function fc_products($force = false) {
    if (!$force) {
        $c = cache_get('fc_products');
        if ($c !== null) return $c;
    }
    $r = fc_request('products');
    $data = $r['data'];
    $list = [];
    if (is_array($data)) {
        // يدعم الشكلين: مصفوفة مباشرة أو {products:[...]} أو {data:[...]}
        $items = $data['products'] ?? $data['data'] ?? (isset($data[0]) ? $data : []);
        foreach ($items as $p) {
            if (!is_array($p)) continue;
            $list[] = [
                'id'        => $p['id'] ?? $p['product_id'] ?? null,
                'name'      => $p['name'] ?? $p['title'] ?? '',
                'price'     => (float)($p['price'] ?? 0),
                'category'  => $p['category_name'] ?? $p['category'] ?? 'منتجات أخرى',
                'image'     => $p['image'] ?? $p['img'] ?? '',
                'available' => !isset($p['available']) || $p['available'] == 1 || $p['available'] === true,
                'params'    => $p['params'] ?? [],
                'qty_values'=> $p['qty_values'] ?? null,
                'desc'      => $p['description'] ?? $p['desc'] ?? '',
            ];
        }
    }
    if ($list) cache_set('fc_products', $list, PRODUCTS_CACHE_TTL);
    return $list ?: (cache_get('fc_products') ?? []);
}

/** المنتجات مع سعر البيع (هامش الربح) */
function store_products() {
    $profit = (float)setting('profit_percent', DEFAULT_PROFIT);
    $list = fc_products();
    foreach ($list as &$p) {
        $p['cost'] = $p['price'];
        $p['price'] = round($p['price'] * (1 + $profit / 100));
    }
    return $list;
}

/** الأقسام مبنية من المنتجات */
function store_categories() {
    $cats = [];
    foreach (store_products() as $p) {
        $cats[$p['category']] = ($cats[$p['category']] ?? 0) + 1;
    }
    return $cats;
}

function store_product($id) {
    foreach (store_products() as $p) if ((string)$p['id'] === (string)$id) return $p;
    return null;
}

/** إرسال طلب جديد لـ FastCard */
function fc_new_order($productId, $qty, $playerId, $uuid) {
    $params = ['qty' => $qty, 'order_uuid' => $uuid];
    if ($playerId !== '') $params['playerId'] = $playerId;
    return fc_request('newOrder/' . rawurlencode($productId) . '/params', $params);
}

/** فحص حالة الطلبات بالـ UUID */
function fc_check_uuid($uuid) {
    $r = fc_request('check', ['orders' => json_encode([$uuid]), 'uuid' => 1]);
    $d = $r['data'];
    if (!is_array($d)) return null;
    $orders = $d['orders'] ?? $d['data'] ?? (isset($d[0]) ? $d : []);
    foreach ($orders as $o) {
        if (!is_array($o)) continue;
        return [
            'status' => $o['status'] ?? null, // accept / processing / reject
            'id'     => $o['id'] ?? $o['order_id'] ?? null,
        ];
    }
    return null;
}
