<?php
require_once __DIR__ . '/db.php';
require_login();
$U = current_user();
$msg = ''; $ok = false;

// التحقق من تحويل (سيرياتيل أو شام كاش) عبر apisyria
function verify_tx($txId, $method) {
    $gsm = $method === 'shamcash' ? shamcash_number() : SYRIATEL_NUMBER;
    $action = $method === 'shamcash' ? 'find_tx_shamcash' : 'find_tx';
    $url = APISYRIA_URL . '?' . http_build_query([
        'key'    => apisyria_key(),
        'action' => $action,
        'gsm'    => $gsm,
        'period' => 'all',
        'tx'     => $txId,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $res = curl_exec($ch);
    curl_close($ch);
    $d = json_decode($res, true);
    if (!is_array($d)) return null;
    $tx = $d['tx'] ?? $d['data'] ?? $d['result'] ?? null;
    if (is_array($tx) && isset($tx[0])) $tx = $tx[0];
    if (!is_array($tx)) return null;
    $amount = (float)($tx['amount'] ?? $tx['value'] ?? 0);
    return $amount > 0 ? $amount : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txId = trim($_POST['tx_id'] ?? '');
    $method = ($_POST['method'] ?? 'syriatel') === 'shamcash' ? 'shamcash' : 'syriatel';
    if (!$txId) {
        $msg = 'أدخل رقم عملية التحويل';
    } else {
        $st = db()->prepare("SELECT COUNT(*) FROM topups WHERE tx_id=?");
        $st->execute([$txId]);
        if ($st->fetchColumn()) {
            $msg = 'رقم العملية هذا مستخدم مسبقاً';
        } else {
            $amount = verify_tx($txId, $method);
            if ($amount === null) {
                $dest = $method === 'shamcash' ? shamcash_number() : SYRIATEL_NUMBER;
                $msg = 'لم يتم العثور على التحويل — تأكد من رقم العملية وأن التحويل وصل إلى ' . $dest;
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
$hasSham = shamcash_number() !== '';

$pageTitle = 'المحفظة';
include __DIR__ . '/header.php'; ?>

<div class="wallet-wrap">
  <div class="card balance-card">
    <div class="muted">رصيد محفظتك</div>
    <div class="big-balance"><?= number_format($U['balance']) ?> <span>ل.س</span></div>
  </div>

  <div class="card">
    <h3>شحن المحفظة</h3>

    <!-- اختيار طريقة الإيداع (نفس أسلوب FastCard) -->
    <div class="pay-methods">
      <button type="button" class="pay-method active" data-method="syriatel" onclick="selectMethod(this)">
        <span class="pm-icon">📱</span>
        <span class="pm-name">سيرياتيل كاش</span>
      </button>
      <?php if ($hasSham): ?>
      <button type="button" class="pay-method" data-method="shamcash" onclick="selectMethod(this)">
        <span class="pm-icon">💳</span>
        <span class="pm-name">شام كاش</span>
      </button>
      <?php endif; ?>
    </div>

    <!-- تعليمات سيرياتيل -->
    <div class="pay-box" id="box-syriatel">
      <ol class="steps">
        <li>حوّل المبلغ المطلوب إلى رقم سيرياتيل كاش:
          <b class="copyable" onclick="copyText('<?= SYRIATEL_NUMBER ?>')"><?= SYRIATEL_NUMBER ?> 📋</b></li>
        <li>أدخل رقم عملية التحويل بالأسفل</li>
        <li>سيُضاف المبلغ تلقائياً بعد التحقق</li>
      </ol>
    </div>

    <?php if ($hasSham): ?>
    <!-- تعليمات شام كاش -->
    <div class="pay-box" id="box-shamcash" style="display:none">
      <ol class="steps">
        <li>حوّل المبلغ المطلوب إلى محفظة شام كاش:
          <b class="copyable" onclick="copyText('<?= e(shamcash_number()) ?>')"><?= e(shamcash_number()) ?> 📋</b></li>
        <li>أدخل رقم عملية التحويل بالأسفل</li>
        <li>سيُضاف المبلغ تلقائياً بعد التحقق</li>
      </ol>
    </div>
    <?php endif; ?>

    <?php if ($msg): ?><div class="alert <?= $ok ? 'ok' : '' ?>"><?= e($msg) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="method" id="payMethod" value="syriatel">
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

<script>
function selectMethod(btn) {
  document.querySelectorAll('.pay-method').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const m = btn.dataset.method;
  document.getElementById('payMethod').value = m;
  document.querySelectorAll('.pay-box').forEach(b => b.style.display = 'none');
  const box = document.getElementById('box-' + m);
  if (box) box.style.display = '';
}
</script>

<?php include __DIR__ . '/footer.php';
