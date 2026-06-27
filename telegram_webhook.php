<?php
require_once __DIR__ . '/db.php'; // يحمّل config.php (التوكن + notify_user)
http_response_code(200);

$token = admin_bot_token();
$adminChat = (string)admin_chat_id();
if ($token === '') { echo 'ok'; exit; }

// تحقق السر (Telegram يرسله بالهيدر) لمنع أي طلب مزوّر
$secret = substr(hash('sha256', 'wh' . $token), 0, 32);
$hdr = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($hdr !== $secret) { echo 'ok'; exit; }

$update = json_decode(file_get_contents('php://input'), true);
$msg = $update['message'] ?? null;
if (!$msg) { echo 'ok'; exit; }

$chatId = (string)($msg['chat']['id'] ?? '');
$text   = trim((string)($msg['text'] ?? ''));
// فقط محادثة الأدمن
if ($adminChat !== '' && $chatId !== $adminChat) { echo 'ok'; exit; }

function tg_send($token, $chat, $text) {
    $ch = curl_init("https://api.telegram.org/bot$token/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML']),
    ]);
    @curl_exec($ch); @curl_close($ch);
}

$uid = 0; $reply = '';
// (1) ردّ (Reply) على رسالة الطلب — نستخرج رقم المستخدم منها (#45)
if (!empty($msg['reply_to_message']['text']) && preg_match('/#(\d+)/', $msg['reply_to_message']['text'], $m)) {
    $uid = (int)$m[1]; $reply = $text;
}
// (2) صيغة مباشرة: "45: نص الرد"  أو  "/r 45 نص الرد"
if (!$uid && $text !== '') {
    if (preg_match('~^/r\s+(\d+)\s+([\s\S]+)~u', $text, $m))      { $uid = (int)$m[1]; $reply = trim($m[2]); }
    elseif (preg_match('~^(\d+)\s*[:：]\s*([\s\S]+)~u', $text, $m)) { $uid = (int)$m[1]; $reply = trim($m[2]); }
}

if (!$uid || $reply === '') {
    if ($text !== '' && strncmp($text, '/start', 6) !== 0) {
        tg_send($token, $chatId, "للرد على زبون:\n• اعمل <b>Reply</b> على رسالة طلبه، واكتب ردّك.\n• أو اكتب: <code>45: نص الرد</code> (45 = رقم المستخدم).");
    }
    echo 'ok'; exit;
}

try {
    $st = db()->prepare("SELECT name FROM users WHERE id=?");
    $st->execute([$uid]);
    $name = $st->fetchColumn();
} catch (Exception $e) { $name = false; }

if ($name === false) { tg_send($token, $chatId, "❌ ما في مستخدم رقمو #$uid"); echo 'ok'; exit; }

try {
    db()->prepare("INSERT INTO support_messages (user_id,sender,body,read_user,read_admin) VALUES (?, 'admin', ?, 0, 1)")
        ->execute([$uid, mb_substr($reply, 0, 2000)]);
    notify_user($uid, 'رد من الدعم 🧑‍💼', mb_substr($reply, 0, 140), '🎧');
    tg_send($token, $chatId, "✅ وصل ردّك للزبون " . $name . " (#$uid)");
} catch (Exception $e) {
    tg_send($token, $chatId, "⚠️ صار خطأ بإرسال الرد");
}
echo 'ok';
