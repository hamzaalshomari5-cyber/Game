<?php
// ===== إعدادات الموقع =====
define('STORE_NAME', 'LUXE CARD');
define('STORE_TAGLINE', 'شحن ألعاب وبطاقات رقمية بسرعة وأمان');

// FastCard API
define('FASTCARD_BASE', 'https://api.fastcard1.store/client/api');
define('FASTCARD_TOKEN', 'ضع_توكن_FASTCARD_هنا'); // أو متغير بيئة FASTCARD_TOKEN

// هامش الربح الافتراضي % (يتعدل من لوحة الأدمن)
define('DEFAULT_PROFIT', 10);

// سيرياتيل كاش
define('SYRIATEL_NUMBER', '0982493924');
define('APISYRIA_KEY', 'ضع_مفتاح_APISYRIA_هنا'); // أو متغير بيئة APISYRIA_KEY
define('APISYRIA_URL', 'https://apisyria.com/api/v1/');

// حساب الأدمن (أول دخول)
define('ADMIN_EMAIL', 'admin@luxecard.store');
define('ADMIN_PASS', 'admin123'); // غيّرها فوراً بعد أول دخول

// روابط التواصل
define('WHATSAPP_1', 'https://wa.me/963900000000');
define('WHATSAPP_GROUP', '');
define('INSTAGRAM', '');

// قاعدة البيانات (Railway Volume إذا موجود)
define('DB_PATH', getenv('DB_PATH') ?: __DIR__ . '/data/store.db');

// كاش المنتجات (ثواني)
define('PRODUCTS_CACHE_TTL', 300);

function env_or($name, $const) {
    $v = getenv($name);
    return $v !== false && $v !== '' ? $v : $const;
}
function fastcard_token() { return env_or('FASTCARD_TOKEN', FASTCARD_TOKEN); }
function apisyria_key()  { return env_or('APISYRIA_KEY', APISYRIA_KEY); }

date_default_timezone_set('Asia/Damascus');
session_start();

// رابط التحقق من اسم اللاعب (نفس اللي بيستخدمه موقع FastCard)
define('CHECK_PLAYER_URL', 'https://fastcard1.store/redeemtech_check_player.php');

// حساب موقع FastCard (للتحقق من اسم اللاعب — نفس بيانات دخولك للموقع)
define('FASTCARD_WEB_BASE', 'https://fastcard1.store');
define('FASTCARD_WEB_USERNAME', ''); // أو متغير بيئة FASTCARD_WEB_USERNAME
define('FASTCARD_WEB_PASSWORD', ''); // أو متغير بيئة FASTCARD_WEB_PASSWORD
define('FASTCARD_2FA_SECRET', '');   // أو متغير بيئة FASTCARD_2FA_SECRET (إذا حسابك عليه مصادقة ثنائية)
function fcw_user()   { return env_or('FASTCARD_WEB_USERNAME', FASTCARD_WEB_USERNAME); }
function fcw_pass()   { return env_or('FASTCARD_WEB_PASSWORD', FASTCARD_WEB_PASSWORD); }
function fcw_2fa()    { return env_or('FASTCARD_2FA_SECRET', FASTCARD_2FA_SECRET); }
