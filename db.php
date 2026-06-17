<?php
require_once __DIR__ . '/config.php';

/* ===== كشف نوع قاعدة البيانات ===== */
function db_url() {
    return getenv('DATABASE_URL') ?: '';
}
function is_pg() {
    static $pg = null;
    if ($pg === null) $pg = (bool)db_url();
    return $pg;
}
/** دالة الوقت الحالي حسب القاعدة */
function NOW_FN() { return is_pg() ? 'NOW()' : "datetime('now')"; }

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    if (is_pg()) {
        // PostgreSQL (Railway) — البيانات دائمة
        // الأولوية للمتغيرات المنفصلة (أضمن من تفكيك الرابط)
        $host = getenv('PGHOST');
        $port = getenv('PGPORT') ?: 5432;
        $dbname = getenv('PGDATABASE');
        $user = getenv('PGUSER');
        $pass = getenv('PGPASSWORD');
        // إذا المنفصلة ناقصة، فكّك DATABASE_URL
        if (!$host || !$user) {
            $u = parse_url(db_url());
            $host = $u['host'] ?? 'localhost';
            $port = $u['port'] ?? 5432;
            $dbname = ltrim($u['path'] ?? '', '/');
            $user = $u['user'] ?? '';
            $pass = isset($u['pass']) ? urldecode($u['pass']) : '';
        }
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => true,
        ]);
    } else {
        // SQLite (تجربة محلية)
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
    }
    init_db($pdo);
    return $pdo;
}

function init_db($pdo) {
    $pk = is_pg() ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $now = is_pg() ? 'CURRENT_TIMESTAMP' : "(datetime('now'))";
    $real = is_pg() ? 'DOUBLE PRECISION' : 'REAL';

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk,
        name TEXT, email TEXT UNIQUE, password TEXT,
        balance $real DEFAULT 0, role TEXT DEFAULT 'user',
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id $pk,
        user_id INTEGER, product_id TEXT, product_name TEXT,
        qty INTEGER DEFAULT 1, player_id TEXT,
        price $real, total $real,
        uuid TEXT, fc_order_id TEXT, codes TEXT,
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT $now,
        updated_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS topups (
        id $pk,
        user_id INTEGER, tx_id TEXT UNIQUE, amount $real,
        status TEXT DEFAULT 'approved',
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
        user_id INTEGER, product_id TEXT, PRIMARY KEY(user_id, product_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY, value TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cache (
        key TEXT PRIMARY KEY, value TEXT, expires BIGINT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        sid TEXT PRIMARY KEY, data TEXT, updated BIGINT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
        id $pk,
        code TEXT UNIQUE, type TEXT DEFAULT 'percent', amount $real DEFAULT 0,
        max_uses INTEGER DEFAULT 0, used INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupon_uses (
        coupon_id INTEGER, user_id INTEGER, used_at TIMESTAMP DEFAULT $now,
        PRIMARY KEY(coupon_id, user_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS slides (
        id $pk,
        image TEXT, link TEXT, sort INTEGER DEFAULT 0, active INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id $pk,
        user_id INTEGER, title TEXT, body TEXT, icon TEXT DEFAULT '🔔',
        is_read INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
        id $pk,
        user_id INTEGER, phone TEXT, code TEXT,
        expires_at TIMESTAMP, attempts INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS id_verifications (
        id $pk,
        user_id INTEGER, image TEXT,
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT $now
    )");
    // عمود الكوبون بجدول الإيداع (للقواعد القديمة)
    if (is_pg()) {
        try { $pdo->exec("ALTER TABLE topups ADD COLUMN IF NOT EXISTS coupon TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS codes TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS banned INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS user_id INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS birthday TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_bday_gift TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_verified INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS id_verified INTEGER DEFAULT 0"); } catch (Exception $e) {}
    } else {
        try { $pdo->exec("ALTER TABLE topups ADD COLUMN coupon TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN codes TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN banned INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN user_id INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN birthday TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN last_bday_gift TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN phone_verified INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN id_verified INTEGER DEFAULT 0"); } catch (Exception $e) {}
    }
    // أدمن افتراضي
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='admin'");
    $st->execute();
    if (!$st->fetchColumn()) {
        $ins = is_pg()
            ? "INSERT INTO users (name,email,password,role) VALUES (?,?,?,'admin') ON CONFLICT (email) DO NOTHING"
            : "INSERT OR IGNORE INTO users (name,email,password,role) VALUES (?,?,?,'admin')";
        $pdo->prepare($ins)->execute(['الأدمن', ADMIN_EMAIL, password_hash(ADMIN_PASS, PASSWORD_DEFAULT)]);
    }
}

/** آخر ID مُدرج (PostgreSQL يحتاج اسم السيكوينس) */
function last_id($table = null) {
    if (is_pg()) {
        $seq = $table ? "{$table}_id_seq" : null;
        return $seq ? db()->lastInsertId($seq) : db()->lastInsertId();
    }
    return db()->lastInsertId();
}

/* ===== جلسة محفوظة بقاعدة البيانات (تضل مسجّل دخول رغم إعادة النشر) ===== */
class DbSessionHandler implements SessionHandlerInterface {
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }
    #[\ReturnTypeWillChange]
    public function read($sid) {
        try {
            $st = db()->prepare("SELECT data FROM sessions WHERE sid=?");
            $st->execute([$sid]);
            $v = $st->fetchColumn();
            return $v !== false ? (string)$v : '';
        } catch (Exception $e) { return ''; }
    }
    public function write($sid, $data): bool {
        try {
            $sql = "INSERT INTO sessions (sid,data,updated) VALUES (?,?,?)
                    ON CONFLICT(sid) DO UPDATE SET data=excluded.data, updated=excluded.updated";
            db()->prepare($sql)->execute([$sid, $data, time()]);
            return true;
        } catch (Exception $e) { return false; }
    }
    public function destroy($sid): bool {
        try { db()->prepare("DELETE FROM sessions WHERE sid=?")->execute([$sid]); } catch (Exception $e) {}
        return true;
    }
    #[\ReturnTypeWillChange]
    public function gc($maxlifetime) {
        try { db()->prepare("DELETE FROM sessions WHERE updated < ?")->execute([time() - $maxlifetime]); } catch (Exception $e) {}
        return true;
    }
}

// بدء الجلسة بعد تجهيز القاعدة (مرة واحدة)
function start_session_once() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    db(); // التأكد من تجهيز القاعدة والجداول
    try {
        session_set_save_handler(new DbSessionHandler(), true);
    } catch (Exception $e) {}
    @session_start();
}
start_session_once();


function setting($key, $default = null) {
    $st = db()->prepare("SELECT value FROM settings WHERE key=?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v !== false ? $v : $default;
}
function set_setting($key, $value) {
    $sql = "INSERT INTO settings (key,value) VALUES (?,?)
            ON CONFLICT(key) DO UPDATE SET value=excluded.value";
    db()->prepare($sql)->execute([$key, $value]);
}

function cache_get($key) {
    $st = db()->prepare("SELECT value FROM cache WHERE key=? AND expires > ?");
    $st->execute([$key, time()]);
    $v = $st->fetchColumn();
    return $v !== false ? json_decode($v, true) : null;
}
function cache_set($key, $data, $ttl) {
    $sql = "INSERT INTO cache (key,value,expires) VALUES (?,?,?)
            ON CONFLICT(key) DO UPDATE SET value=excluded.value, expires=excluded.expires";
    db()->prepare($sql)->execute([$key, json_encode($data, JSON_UNESCAPED_UNICODE), time() + $ttl]);
}

function current_user() {
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    // المستخدم المحظور: تسجيل خروج فوري
    if ($u && !empty($u['banned'])) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
        return null;
    }
    return $u;
}
function require_login() {
    if (!current_user()) { header('Location: /auth.php'); exit; }
}
function require_admin() {
    $u = current_user();
    if (!$u || $u['role'] !== 'admin') { header('Location: /auth.php'); exit; }
    return $u;
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== العملة وسعر الصرف ===== */
function usd_rate() { return max(0.0001, (float)setting('usd_rate', 11000)); }
function display_currency() { return (($_COOKIE['currency'] ?? 'syp') === 'usd') ? 'usd' : 'syp'; }
function fmt_price($syp) {
    if (display_currency() === 'usd') return number_format($syp / usd_rate(), 2) . ' $';
    return number_format($syp) . ' ل.س';
}
