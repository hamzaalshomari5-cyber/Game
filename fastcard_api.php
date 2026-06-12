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

function _fc_items($data, $keys) {
    if (!is_array($data)) return [];
    foreach ($keys as $k) if (isset($data[$k]) && is_array($data[$k])) return $data[$k];
    return isset($data[0]) ? $data : [];
}

/** شجرة الأقسام من FastCard (إذا متوفرة) مع كاش */
function fc_categories($force = false) {
    if (!$force) {
        $c = cache_get('fc_categories');
        if ($c !== null) return $c;
    }
    $cats = [];
    foreach (['categories', 'content', 'cats'] as $ep) {
        $r = fc_request($ep);
        if ($r['code'] < 200 || $r['code'] >= 300) continue;
        $items = _fc_items($r['data'], ['categories', 'data', 'cats', 'content']);
        foreach ($items as $c) {
            if (!is_array($c) || !isset($c['id'])) continue;
            $cats[] = [
                'id'     => (string)$c['id'],
                'name'   => $c['name'] ?? $c['title'] ?? '',
                'parent' => (string)($c['parent_id'] ?? $c['parent'] ?? $c['category_id'] ?? '0'),
                'image'  => $c['image'] ?? $c['img'] ?? '',
            ];
        }
        if ($cats) break;
    }
    cache_set('fc_categories', $cats, PRODUCTS_CACHE_TTL);
    return $cats;
}

/** كل المنتجات من FastCard مع كاش */
function fc_products($force = false) {
    if (!$force) {
        $c = cache_get('fc_products');
        if ($c !== null) return $c;
    }
    $r = fc_request('products');
    $list = [];
    foreach (_fc_items($r['data'], ['products', 'data']) as $p) {
        if (!is_array($p)) continue;
        $list[] = [
            'id'        => (string)($p['id'] ?? $p['product_id'] ?? ''),
            'name'      => $p['name'] ?? $p['title'] ?? '',
            'price'     => (float)($p['price'] ?? 0),
            'category'  => $p['category_name'] ?? $p['category'] ?? 'منتجات أخرى',
            'cat_id'    => (string)($p['category_id'] ?? $p['cat_id'] ?? $p['category'] ?? ''),
            'image'     => $p['image'] ?? $p['img'] ?? '',
            'available' => !isset($p['available']) || $p['available'] == 1 || $p['available'] === true,
            'desc'      => $p['description'] ?? $p['desc'] ?? '',
        ];
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

function store_product($id) {
    foreach (store_products() as $p) if ((string)$p['id'] === (string)$id) return $p;
    return null;
}

/* ===== نظام الأقسام الشجري (مثل FastCard) =====
   إذا API رجّع شجرة أقسام نستخدمها، وإلا نبني الأقسام من أسماء فئات المنتجات */

function tree_mode() { return count(fc_categories()) > 0; }

/** الأقسام الجذرية (الرئيسية) */
function root_categories() {
    if (tree_mode()) {
        $roots = [];
        foreach (fc_categories() as $c)
            if ($c['parent'] === '0' || $c['parent'] === '' || $c['parent'] === null) $roots[] = $c;
        return $roots;
    }
    // وضع مسطّح: قسم لكل فئة منتجات
    $cats = [];
    foreach (store_products() as $p)
        $cats[$p['category']] = ['id' => $p['category'], 'name' => $p['category'], 'image' => '', 'parent' => '0'];
    return array_values($cats);
}

/** الأقسام الفرعية لقسم معيّن */
function child_categories($id) {
    if (!tree_mode()) return [];
    $out = [];
    foreach (fc_categories() as $c) if ($c['parent'] === (string)$id) $out[] = $c;
    return $out;
}

/** اسم قسم */
function category_name($id) {
    if (tree_mode()) {
        foreach (fc_categories() as $c) if ($c['id'] === (string)$id) return $c['name'];
    }
    return (string)$id;
}

/** منتجات قسم معيّن */
function products_in($id) {
    $out = [];
    foreach (store_products() as $p) {
        $match = tree_mode() ? ($p['cat_id'] === (string)$id) : ($p['category'] === (string)$id);
        if ($match) $out[] = $p;
    }
    return $out;
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
    $orders = _fc_items($r['data'], ['orders', 'data']);
    foreach ($orders as $o) {
        if (!is_array($o)) continue;
        return [
            'status' => $o['status'] ?? null, // accept / processing / reject
            'id'     => $o['id'] ?? $o['order_id'] ?? null,
        ];
    }
    return null;
}
