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
define('PRODUCTS_CACHE_TTL', 300);

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
// عنوان شام كاش المربوط بـ apisyria (فارغ = البحث بكل الحسابات المربوطة)
function shamcash_account() {
    return env_or('SHAMCASH_ADDRESS', SHAMCASH_ADDRESS);
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
