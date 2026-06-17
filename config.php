<?php
// ===== إعدادات الموقع =====
define('STORE_NAME', 'LUXE CARD');
define('STORE_TAGLINE', 'شحن ألعاب وبطاقات رقمية بسرعة وأمان');

// FastCard API
define('FASTCARD_BASE', 'https://api.fastcard1.store/client/api');
define('FASTCARD_TOKEN', 'ضع_توكن_FASTCARD_هنا'); // أو متغير بيئة FASTCARD_TOKEN

// هامش الربح الافتراضي % (يتعدل من لوحة الأدمن)
define('DEFAULT_PROFIT', 10);

// ===== طرق الإيداع =====
// سيرياتيل كاش
define('SYRIATEL_NUMBER', '0982493924'); // الرقم المعروض للزبون ليحوّل عليه
// رقم/كود حساب سيرياتيل المربوط بـ apisyria للبحث (افتراضياً = نفس الرقم المعروض)
define('SYRIATEL_GSM', ''); // أو متغير بيئة SYRIATEL_GSM — اتركه فارغاً ليستخدم SYRIATEL_NUMBER
// شام كاش
define('SHAMCASH_NUMBER', ''); // عنوان شام كاش المعروض للزبون — أو متغير بيئة SHAMCASH_NUMBER
define('SHAMCASH_ADDRESS', ''); // account_address المربوط بـ apisyria — أو متغير بيئة SHAMCASH_ADDRESS (افتراضياً = SHAMCASH_NUMBER)
// التحقق من التحويلات
define('APISYRIA_KEY', 'ضع_مفتاح_APISYRIA_هنا'); // أو متغير بيئة APISYRIA_KEY
define('APISYRIA_URL', 'https://apisyria.com/api/v1');

// حساب الأدمن (أول دخول)
define('ADMIN_EMAIL', 'admin@luxecard.store');
define('ADMIN_PASS', 'admin123'); // غيّرها فوراً بعد أول دخول

// روابط التواصل
define('WHATSAPP_NUM_1', '0982493924');
define('WHATSAPP_NUM_2', '0951655874');
define('WHATSAPP_1', 'https://wa.me/963982493924');
define('WHATSAPP_2', 'https://wa.me/963951655874');
define('WHATSAPP_GROUP', '');
define('INSTAGRAM', '');

// قاعدة البيانات (SQLite محلي — على Railway بيستخدم DATABASE_URL تلقائياً)
define('DB_PATH', getenv('DB_PATH') ?: __DIR__ . '/data/store.db');

// كاش المنتجات (ثواني)
define('PRODUCTS_CACHE_TTL', 3600); // ساعة — تحميل أسرع (المنتجات نادراً تتغير)

function env_or($name, $const) {
    $v = getenv($name);
    return $v !== false && $v !== '' ? $v : $const;
}
function fastcard_token() { return env_or('FASTCARD_TOKEN', FASTCARD_TOKEN); }
function apisyria_key()   { return env_or('APISYRIA_KEY', APISYRIA_KEY); }
function shamcash_number() { return env_or('SHAMCASH_NUMBER', SHAMCASH_NUMBER); }
// رقم سيرياتيل المربوط بـ apisyria (يرجع للرقم المعروض إذا ما تحدد)
function syriatel_gsm() {
    $g = env_or('SYRIATEL_GSM', SYRIATEL_GSM);
    return $g !== '' ? $g : SYRIATEL_NUMBER;
}
// عنوان شام كاش المربوط بـ apisyria (يقبل SHAMCASH_ADDRESS أو SHAMCASH_TOKEN)
function shamcash_account() {
    $a = env_or('SHAMCASH_ADDRESS', SHAMCASH_ADDRESS);
    if ($a === '') $a = env_or('SHAMCASH_TOKEN', '');
    return $a;
}

date_default_timezone_set('Asia/Damascus');

// ===== إعداد كوكي الجلسة (30 يوم) — session_start تُستدعى من db.php بعد تجهيز القاعدة =====
define('SESSION_LIFETIME', 30 * 24 * 60 * 60);
ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
ini_set('session.cookie_lifetime', (string)SESSION_LIFETIME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

// رابط التحقق من اسم اللاعب (نفس اللي بيستخدمه موقع FastCard)
define('CHECK_PLAYER_URL', 'https://fastcard1.store/redeemtech_check_player.php');

// حساب موقع FastCard (للتحقق من اسم اللاعب)
define('FASTCARD_WEB_BASE', 'https://fastcard1.store');
define('FASTCARD_WEB_USERNAME', '');
define('FASTCARD_WEB_PASSWORD', '');
define('FASTCARD_2FA_SECRET', '');
function fcw_user() { return env_or('FASTCARD_WEB_USERNAME', FASTCARD_WEB_USERNAME); }
function fcw_pass() { return env_or('FASTCARD_WEB_PASSWORD', FASTCARD_WEB_PASSWORD); }
function fcw_2fa()  { return env_or('FASTCARD_2FA_SECRET', FASTCARD_2FA_SECRET); }

// التحقق من اسم اللاعب عبر البوت
define('BOT_CHECK_URL', '');
define('CHECK_API_SECRET', '');
function bot_check_url() { return env_or('BOT_CHECK_URL', BOT_CHECK_URL); }
function check_secret()  { return env_or('CHECK_API_SECRET', CHECK_API_SECRET); }

// ===== تسجيل دخول Google =====
define('GOOGLE_CLIENT_ID', '');     // أو متغير بيئة GOOGLE_CLIENT_ID
define('GOOGLE_CLIENT_SECRET', ''); // أو متغير بيئة GOOGLE_CLIENT_SECRET
// رابط الموقع (للـ redirect بعد دخول جوجل) — مثال: https://game-production-xxx.up.railway.app
define('SITE_URL', '');             // أو متغير بيئة SITE_URL
function google_client_id()     { return env_or('GOOGLE_CLIENT_ID', GOOGLE_CLIENT_ID); }
function google_client_secret() { return env_or('GOOGLE_CLIENT_SECRET', GOOGLE_CLIENT_SECRET); }
function site_url() {
    $u = env_or('SITE_URL', SITE_URL);
    if ($u) return rtrim($u, '/');
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function google_enabled() { return google_client_id() !== '' && google_client_secret() !== ''; }

// ===== إشعارات الأدمن عبر تيليغرام =====
// أنشئ بوت من @BotFather واحصل على التوكن، واحصل على chat_id من @userinfobot
define('ADMIN_BOT_TOKEN', '');  // أو متغير بيئة ADMIN_BOT_TOKEN
define('ADMIN_CHAT_ID', '');    // أو متغير بيئة ADMIN_CHAT_ID
function admin_bot_token() { return env_or('ADMIN_BOT_TOKEN', ADMIN_BOT_TOKEN); }
function admin_chat_id()   { return env_or('ADMIN_CHAT_ID', ADMIN_CHAT_ID); }

// إرسال إشعار للأدمن (لا يعطّل العملية إذا فشل)
function notify_admin($text) {
    $token = admin_bot_token();
    $chat = admin_chat_id();
    if ($token === '' || $chat === '') return;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chat,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]),
    ]);
    @curl_exec($ch);
    @curl_close($ch);
}

// ===== إشعارات المستخدم (داخل الموقع) =====
function notify_user($userId, $title, $body = '', $icon = '🔔') {
    if (!$userId) return;
    try {
        db()->prepare("INSERT INTO notifications (user_id,title,body,icon) VALUES (?,?,?,?)")
            ->execute([$userId, $title, $body, $icon]);
    } catch (Exception $e) {}
}

// ===== نظام العرض بوقت محدود =====
// يُدار من إعدادات الأدمن. الإعدادات مخزّنة في جدول settings:
//   promo_active (0/1), promo_type (discount/deposit/banner),
//   promo_value (نسبة), promo_title, promo_end (timestamp أو فارغ ليدوي)
function promo_get() {
    $active = setting('promo_active', '0');
    if ($active !== '1') return null;
    $end = setting('promo_end', '');
    // إذا في وقت نهاية وانتهى، العرض متوقف
    if ($end !== '' && (int)$end > 0 && time() > (int)$end) return null;
    return [
        'type'  => setting('promo_type', 'banner'),
        'value' => (float)setting('promo_value', '0'),
        'title' => setting('promo_title', 'عرض خاص'),
        'end'   => $end !== '' ? (int)$end : 0,
    ];
}
// نسبة خصم المنتجات الحالية (0 إذا ما في عرض خصم)
function promo_discount_pct() {
    $p = promo_get();
    return ($p && $p['type'] === 'discount') ? $p['value'] : 0;
}
// نسبة بونص الإيداع الحالية (0 إذا ما في عرض بونص)
function promo_deposit_pct() {
    $p = promo_get();
    return ($p && $p['type'] === 'deposit') ? $p['value'] : 0;
}

// ===== نظام مستويات VIP =====
// VIP1 افتراضي، VIP2 عند إنفاق 1000$، VIP3 عند 5000$ (الإنفاق = مجموع الطلبات المنفّذة)
function vip_thresholds() {
    // العتبات بالدولار — تُحوّل لليرة حسب سعر الصرف
    return [2 => 1000, 3 => 5000];
}
// مجموع إنفاق المستخدم بالليرة (الطلبات المنفّذة)
function user_spent($userId) {
    $st = db()->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=? AND status='accept'");
    $st->execute([$userId]);
    return (float)$st->fetchColumn();
}
// مستوى VIP الحالي للمستخدم (1/2/3)
function user_vip_level($userId) {
    $spentSyp = user_spent($userId);
    $rate = usd_rate();
    $spentUsd = $rate > 0 ? $spentSyp / $rate : 0;
    $level = 1;
    foreach (vip_thresholds() as $lvl => $minUsd) {
        if ($spentUsd >= $minUsd) $level = $lvl;
    }
    return $level;
}
// معلومات VIP كاملة (للعرض)
function user_vip_info($userId) {
    $spentSyp = user_spent($userId);
    $rate = usd_rate();
    $spentUsd = $rate > 0 ? $spentSyp / $rate : 0;
    $level = user_vip_level($userId);
    $th = vip_thresholds();
    // المتبقي للمستوى التالي
    $next = null; $needUsd = 0;
    if ($level < 3) {
        $nextLevel = $level + 1;
        $needUsd = $th[$nextLevel] - $spentUsd;
        $next = $nextLevel;
    }
    return [
        'level' => $level,
        'spent_usd' => $spentUsd,
        'spent_syp' => $spentSyp,
        'next_level' => $next,
        'need_usd' => max(0, $needUsd),
    ];
}
function vip_badge($level) {
    $badges = [1 => '🥉 VIP 1', 2 => '🥈 VIP 2', 3 => '🥇 VIP 3'];
    return $badges[$level] ?? '🥉 VIP 1';
}

// ===== توثيق رقم الموبايل عبر Aman Gate OTP =====
function aman_token()       { return env_or('AMAN_TOKEN', ''); }
function aman_template_id()  { return (int)env_or('AMAN_TEMPLATE_ID', '1'); }
function otp_enabled()       { return aman_token() !== ''; }

// تحويل الرقم لصيغة دولية 963xxxxxxxxx
function normalize_gsm($phone) {
    $p = preg_replace('/\D/', '', $phone);
    if (strpos($p, '963') === 0) return $p;
    if (strpos($p, '0') === 0) return '963' . substr($p, 1);
    if (strlen($p) === 9) return '963' . $p;
    return $p;
}

// إرسال رمز OTP عبر Aman Gate. يرجّع [نجاح(bool), رسالة]
function aman_send_otp($gsm, $code) {
    $token = aman_token();
    if ($token === '') return [false, 'خدمة التوثيق غير مفعّلة'];
    $body = json_encode([
        'gsm'         => $gsm,
        'template_id' => aman_template_id(),
        'code'        => (string)$code,
        'language'    => 0,
    ]);
    $ch = curl_init('https://aman-gate.com/otp/send/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http === 201) return [true, 'تم إرسال الرمز'];
    if ($http === 402) return [false, 'خدمة الرسائل غير متوفرة حالياً، حاول لاحقاً'];
    if ($http === 401) return [false, 'خطأ بإعدادات خدمة التوثيق'];
    return [false, 'تعذّر إرسال الرمز، تأكد من الرقم وحاول مجدداً'];
}
