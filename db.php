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
        $u = parse_url(db_url());
        $host = $u['host'] ?? 'localhost';
        $port = $u['port'] ?? 5432;
        $dbname = ltrim($u['path'] ?? '', '/');
        $user = $u['user'] ?? '';
        $pass = $u['pass'] ?? '';
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
    // لو القاعدة قديمة (SQLite) وما فيها عمود codes
    if (!is_pg()) {
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN codes TEXT"); } catch (Exception $e) {}
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
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
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
