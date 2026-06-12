<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$player = trim((string)($_GET['player'] ?? ''));
if ($player === '') { echo json_encode(['ok' => false, 'msg' => 'أدخل ID اللاعب أولاً']); exit; }

function try_check($url, $params, $post) {
    $full = $post ? $url : $url . '?' . http_build_query($params);
    $ch = curl_init($full);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain, */*'],
    ]);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $res];
}

function extract_name($res) {
    if (!$res) return null;
    $j = json_decode($res, true);
    if (is_array($j)) {
        foreach (['name', 'nickname', 'username', 'player_name', 'playerName', 'nick'] as $k) {
            if (!empty($j[$k]) && is_string($j[$k])) return $j[$k];
            if (!empty($j['data'][$k]) && is_string($j['data'][$k])) return $j['data'][$k];
        }
        return null;
    }
    // رد نصي مباشر (اسم فقط)
    $t = trim(strip_tags($res));
    if ($t !== '' && mb_strlen($t) <= 60 && stripos($t, 'error') === false && stripos($t, '<') === false) return $t;
    return null;
}

// جرّب عدة صيغ شائعة لنفس الـ endpoint
$variants = [
    [['player_id' => $player], false],
    [['playerId'  => $player], false],
    [['id'        => $player], false],
    [['player_id' => $player], true],
    [['playerId'  => $player], true],
];

$reached = false;
$debug = [];
foreach ($variants as [$params, $post]) {
    [$code, $res] = try_check(CHECK_PLAYER_URL, $params, $post);
    if ($code >= 200 && $code < 500 && $res !== false) $reached = true;
    $debug[] = ($post ? 'POST ' : 'GET ') . json_encode($params) . " => HTTP $code | " . mb_substr(trim((string)$res), 0, 250);
    $name = extract_name($res);
    if ($name) { echo json_encode(['ok' => true, 'name' => $name], JSON_UNESCAPED_UNICODE); exit; }
}

// وضع الفحص: /check_name.php?player=XXX&debug=1
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n\n", $debug);
    exit;
}

if ($reached) {
    echo json_encode(['ok' => false, 'msg' => 'لم يتم العثور على اللاعب — تأكد من الـ ID'], JSON_UNESCAPED_UNICODE);
} else {
    // الخدمة نفسها مش متاحة — لا نمنع الشراء
    echo json_encode(['ok' => false, 'soft' => true, 'msg' => 'تعذّر التحقق من الاسم حالياً'], JSON_UNESCAPED_UNICODE);
}
