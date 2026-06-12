<?php
require_once __DIR__ . '/db.php';
require_login();
$U = current_user();
$msg = ''; $ok = false;

function verify_syriatel_tx($txId) {
    // التحقق عبر apisyria - find_tx
    $url = APISYRIA_URL . '?' . http_build_query([
        'key'    => apisyria_key(),
        'action' => 'find_tx',
        'gsm'    => SYRIATEL_NUMBER,
        'period' => 'all',
        'tx'     => $txId,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $res = curl_exec($ch);
    curl_close($ch);
    $d = json_decode($res, true);
    if (!is_array($d)) return null;
    // يدعم أكثر من شكل للرد
    $tx = $d['tx'] ?? $d['data'] ?? $d['result'] ?? null;
    if (is_array($tx) && isset($tx[0])) $tx = $tx[0];
    if (!is_array($tx)) return null;
    $amount = (float)($tx['amount'] ?? $tx['value'] ?? 0);
    return $amount > 0 ? $amount : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txId = trim($_POST['tx_id'] ?? '');
    if (!$txId) {
        $msg = 'أدخل رقم عملية التحويل';
    } else {
        // منع التكرار
        $st = db()->prepare("SELECT COUNT(*) FROM topups WHERE tx_id=?");
        $st->execute([$txId]);
        if ($st->fetchColumn()) {
            $msg = 'رقم العملية هذا مستخدم مسبقاً';
        } else {
            $amount = verify_syriatel_tx($txId);
            if ($amount === null) {
                $msg = 'لم يتم العثور على التحويل — تأكد من رقم العملية وأن التحويل وصل إلى ' . SYRIATEL_NUMBER;
            } else {
                db()->beginTransaction();
                db()->prepare("INSERT INTO topups (user_id,tx_id,amount) VALUES (?,?,?)")
                    ->execute([$U['id'], $txId, $amount]);
                db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")
                    ->execute([$amount, $U['id']]);
                db()->commit();
                $ok = true;
                $msg = 'تم شحن محفظتك بمبلغ ' . number_format($amount) . ' ل.س ✅';
                $U = current_user();
            }
        }
    }
}

$st = db()->prepare("SELECT * FROM topups WHERE user_id=? ORDER BY id DESC LIMIT 10");
$st->execute([$U['id']]);
$history = $st->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'المحفظة';
include __DIR__ . '/header.php'; ?>

<div class="wallet-wrap">
  <div class="card balance-card">
    <div class="muted">رصيد محفظتك</div>
    <div class="big-balance"><?= number_format($U['balance']) ?> <span>ل.س</span></div>
  </div>

  <div class="card">
    <h3>شحن المحفظة — سيرياتيل كاش</h3>
    <ol class="steps">
      <li>حوّل المبلغ المطلوب إلى الرقم: <b class="copyable" onclick="copyText('<?= SYRIATEL_NUMBER ?>')"><?= SYRIATEL_NUMBER ?> 📋</b></li>
      <li>أدخل رقم عملية التحويل بالأسفل</li>
      <li>سيُضاف المبلغ تلقائياً بعد التحقق</li>
    </ol>
    <?php if ($msg): ?><div class="alert <?= $ok ? 'ok' : '' ?>"><?= e($msg) ?></div><?php endif; ?>
    <form method="post">
      <label>رقم عملية التحويل</label>
      <input name="tx_id" required placeholder="مثال: 600123456789">
      <button class="btn full" type="submit">تحقق وشحن</button>
    </form>
  </div>

  <?php if ($history): ?>
  <div class="card">
    <h3>آخر عمليات الشحن</h3>
    <table class="tbl">
      <tr><th>رقم العملية</th><th>المبلغ</th><th>التاريخ</th></tr>
      <?php foreach ($history as $h): ?>
        <tr><td><?= e($h['tx_id']) ?></td><td><?= number_format($h['amount']) ?> ل.س</td><td><?= e($h['created_at']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php';
