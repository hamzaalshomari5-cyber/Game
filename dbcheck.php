<?php
header('Content-Type: text/plain; charset=utf-8');
function mask($v) {
    if ($v === false || $v === '') return '(فارغ ❌)';
    $len = strlen($v);
    return substr($v, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($v, -2) . " (طول: $len)";
}
echo "=== المتغيرات المنفصلة ===\n";
foreach (['PGHOST','PGPORT','PGDATABASE','PGUSER','PGPASSWORD'] as $k) {
    $v = getenv($k);
    echo str_pad($k, 14) . ": " . ($k === 'PGPASSWORD' ? mask($v) : ($v === false ? '(فارغ ❌)' : $v)) . "\n";
}
echo "\n=== DATABASE_URL ===\n";
$durl = getenv('DATABASE_URL');
if ($durl === false || $durl === '') {
    echo "(فارغ ❌)\n";
} else {
    $u = parse_url($durl);
    echo "host: " . ($u['host'] ?? '?') . "\n";
    echo "port: " . ($u['port'] ?? '?') . "\n";
    echo "user: " . ($u['user'] ?? '?') . "\n";
    echo "db:   " . ltrim($u['path'] ?? '', '/') . "\n";
    echo "pass: " . mask(isset($u['pass']) ? urldecode($u['pass']) : '') . "\n";
}

echo "\n=== تجربة الاتصال ===\n";
$host = getenv('PGHOST') ?: (parse_url($durl)['host'] ?? '');
$port = getenv('PGPORT') ?: 5432;
$dbname = getenv('PGDATABASE') ?: ltrim(parse_url($durl)['path'] ?? '', '/');
$user = getenv('PGUSER') ?: (parse_url($durl)['user'] ?? '');
$pass = getenv('PGPASSWORD') ?: (isset(parse_url($durl)['pass']) ? urldecode(parse_url($durl)['pass']) : '');
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ الاتصال نجح! القاعدة شغالة.\n";
} catch (Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n";
}
