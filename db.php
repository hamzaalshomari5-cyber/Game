<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');
    init_db($pdo);
    return $pdo;
}

function init_db($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT, email TEXT UNIQUE, password TEXT,
        balance REAL DEFAULT 0, role TEXT DEFAULT 'user',
        created_at TEXT DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER, product_id TEXT, product_name TEXT,
        qty INTEGER DEFAULT 1, player_id TEXT,
        price REAL, total REAL,
        uuid TEXT, fc_order_id TEXT,
        status TEXT DEFAULT 'pending', -- pending / accept / reject
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS topups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER, tx_id TEXT UNIQUE, amount REAL,
        status TEXT DEFAULT 'approved',
        created_at TEXT DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY, value TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cache (
        key TEXT PRIMARY KEY, value TEXT, expires INTEGER
    )");
    // أدمن افتراضي
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='admin'");
    $st->execute();
    if (!$st->fetchColumn()) {
        $pdo->prepare("INSERT OR IGNORE INTO users (name,email,password,role) VALUES (?,?,?,'admin')")
            ->execute(['الأدمن', ADMIN_EMAIL, password_hash(ADMIN_PASS, PASSWORD_DEFAULT)]);
    }
}

function setting($key, $default = null) {
    $st = db()->prepare("SELECT value FROM settings WHERE key=?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v !== false ? $v : $default;
}
function set_setting($key, $value) {
    db()->prepare("INSERT INTO settings (key,value) VALUES (?,?)
        ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([$key, $value]);
}

function cache_get($key) {
    $st = db()->prepare("SELECT value FROM cache WHERE key=? AND expires > ?");
    $st->execute([$key, time()]);
    $v = $st->fetchColumn();
    return $v !== false ? json_decode($v, true) : null;
}
function cache_set($key, $data, $ttl) {
    db()->prepare("INSERT INTO cache (key,value,expires) VALUES (?,?,?)
        ON CONFLICT(key) DO UPDATE SET value=excluded.value, expires=excluded.expires")
        ->execute([$key, json_encode($data, JSON_UNESCAPED_UNICODE), time() + $ttl]);
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
