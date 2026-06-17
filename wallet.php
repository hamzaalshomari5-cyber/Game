<?php
require_once __DIR__ . '/db.php';
require_login();
$U = current_user();
$msg = ''; $ok = false;

// وضع تشخيص: /wallet.php?apidebug=TXID&m=syriatel  (احذفه بعد الحل)
if (isset($_GET['apidebug'])) {
    header('Content-Type: text/plain; charset=utf-8');
    // جلب الحسابات المربوطة: /wallet.php?apidebug=accounts
    if ($_GET['apidebug'] === 'accounts') {
        $url = 'https://apisyria.com/api/v1?' . http_build_query(['resource'=>'accounts','action'=>'list','api_key'=>apisyria_key()]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $res = curl_exec($ch); curl_close($ch);
        echo "حساباتك المربوطة بـ apisyria:\nانسخ account_address الخاص بشام كاش (يبدأ بـ 251aw) وحطّه بمتغير SHAMCASH_TOKEN على Railway\n\n$res";
        exit;
    }
    $txId = preg_replace('/\D/', '', $_GET['apidebug']);
    $method = ($_GET['m'] ?? 'syriatel') === 'shamcash' ? 'shamcash' : 'syriatel';
    $base = 'https://apisyria.com/api/v1';
    if ($method === 'shamcash') {
        $params = ['resource'=>'shamcash','action'=>'find_tx','tx'=>$txId,'account_address'=>shamcash_account(),'api_key'=>apisyria_key()];
    } else {
        $params = ['resource'=>'syriatel','action'=>'find_tx','tx'=>$txId,'gsm'=>syriatel_gsm(),'period'=>'all','api_key'=>apisyria_key()];
    }
    $url = $base . '?' . http_build_query($params);
    $shown = str_replace(urlencode(apisyria_key()), '***KEY***', $url);
    echo "الطريقة: $method
";
    echo "GSM/Address: " . ($method==='shamcash'?shamcash_account():syriatel_gsm()) . "
";
    echo "مفتاح apisyria: " . (apisyria_key()!==''?'موجود (طول '.strlen(apisyria_key()).')':'فارغ ❌') . "
";
    echo "الرابط: $shown

";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "HTTP: $code
";
    if ($err) echo "CURL_ERR: $err
";
    echo "
الرد:
$res";
    exit;
}

// التحقق من تحويل (سيرياتيل أو شام كاش) عبر apisyria — حسب التوثيق الرسمي
function verify_tx($txId, $method) {
    $base = 'https://apisyria.com/api/v1';
    if ($method === 'shamcash') {
        // tx = رقم العملية (tran_id) أرقام فقط. account_address اختياري (نتركه ليبحث بكل الحسابات)
        $params = [
            'resource' => 'shamcash',
            'action'   => 'find_tx',
            'tx'       => $txId,
            'api_key'  => apisyria_key(),
        ];
        $addr = shamcash_account();
        if ($addr !== '') $params['account_address'] = $addr;
    } else {
        $params = [
            'resource' => 'syriatel',
            'action'   => 'find_tx',
            'tx'       => $txId,
            'gsm'      => syriatel_gsm(),
            'period'   => 'all',
            'api_key'  => apisyria_key(),
        ];
    }
    $url = $base . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = json_decode($res, true);
    if (!is_array($d) || empty($d['success'])) return null;
    $data = $d['data'] ?? [];
    if (empty($data['found'])) return null;
    $tx = $data['transaction'] ?? [];
    $amount = (float)($tx['amount'] ?? 0);
    return $amount > 0 ? $amount : null;
}

// التحقق من كود الخصم وإرجاع نسبة/قيمة الإضافة
function check_coupon($code, $userId) {
    $code = strtoupper(trim($code));
    if ($code === '') return [0, null];
    $st = db()->prepare("SELECT * FROM coupons WHERE code=? AND active=1");
    $st->execute([$code]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) return [0, 'كود الخصم غير صحيح'];
    if ($c['max_uses'] > 0 && $c['used'] >= $c['max_uses']) return [0, 'انتهت صلاحية كود الخصم'];
    // كوبون مربوط بمستخدم محدد (كود VIP خاص)
    if (!empty($c['user_id']) && (int)$c['user_id'] !== (int)$userId) {
        return [0, 'هذا الكود خاص بحساب آخر'];
    }
    // مرة واحدة لكل مستخدم
    $st = db()->prepare("SELECT COUNT(*) FROM coupon_uses WHERE coupon_id=? AND user_id=?");
    $st->execute([$c['id'], $userId]);
    if ($st->fetchColumn()) return [0, 'استخدمت هذا الكود مسبقاً'];
    return [$c, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txId = trim($_POST['tx_id'] ?? '');
    $method = ($_POST['method'] ?? 'syriatel') === 'shamcash' ? 'shamcash' : 'syriatel';
    $couponCode = trim($_POST['coupon'] ?? '');
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
                // تطبيق كود الخصم (مكافأة إضافية على الإيداع)
                $bonus = 0; $couponObj = null; $couponMsg = null;
                if ($couponCode !== '') {
                    [$couponObj, $couponMsg] = check_coupon($couponCode, $U['id']);
                    if ($couponObj) {
                        if ($couponObj['type'] === 'percent') $bonus = round($amount * $couponObj['amount'] / 100);
                        else $bonus = $couponObj['amount'];
                    }
                }
                // بونص العرض بوقت محدود (يُضاف فوق أي كوبون)
                $promoBonus = 0;
                $promoPct = promo_deposit_pct();
                if ($promoPct > 0) $promoBonus = round($amount * $promoPct / 100);
                $bonus += $promoBonus;
                $total = $amount + $bonus;
                db()->beginTransaction();
                db()->prepare("INSERT INTO topups (user_id,tx_id,amount,coupon) VALUES (?,?,?,?)")
                    ->execute([$U['id'], $txId, $total, $couponObj ? $couponCode : null]);
                db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")
                    ->execute([$total, $U['id']]);
                if ($couponObj) {
                    db()->prepare("UPDATE coupons SET used = used + 1 WHERE id=?")->execute([$couponObj['id']]);
                    db()->prepare("INSERT INTO coupon_uses (coupon_id,user_id) VALUES (?,?)")
                        ->execute([$couponObj['id'], $U['id']]);
                }
                db()->commit();
                $ok = true;
                $msg = 'تم شحن محفظتك بمبلغ ' . number_format($total) . ' ل.س ✅';
                if ($bonus > 0) {
                    $parts = [];
                    if ($promoBonus > 0) $parts[] = number_format($promoBonus) . ' بونص العرض 🎉';
                    if ($bonus - $promoBonus > 0) $parts[] = number_format($bonus - $promoBonus) . ' كود الخصم 🎁';
                    $msg .= ' (منها ' . implode(' + ', $parts) . ')';
                } elseif ($couponMsg) $msg .= ' — ملاحظة: ' . $couponMsg;
                $U = current_user();
                // إشعار المستخدم بنجاح الإيداع
                notify_user($U['id'], 'تم شحن محفظتك 💰',
                    'أُضيف ' . number_format($total) . ' ل.س' . ($bonus > 0 ? ' (منها ' . number_format($bonus) . ' مكافأة)' : '') . ' لمحفظتك.', '💰');
                // إشعار الأدمن
                notify_admin("💰 <b>إيداع جديد</b>\nالمستخدم: " . e($U['name']) . "\nالمبلغ: " . number_format($total) . " ل.س\nالطريقة: " . ($method === 'shamcash' ? 'شام كاش' : 'سيرياتيل كاش') . "\nرقم العملية: $txId");
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
    <div class="big-balance bal-amount-big" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> <span>ل.س</span></div>
  </div>

  <div class="card">
    <h3>شحن المحفظة</h3>

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

    <div class="pay-box" id="box-syriatel">
      <ol class="steps">
        <li>حوّل المبلغ المطلوب إلى رقم سيرياتيل كاش:
          <b class="copyable" onclick="copyText('<?= SYRIATEL_NUMBER ?>')"><?= SYRIATEL_NUMBER ?> 📋</b></li>
        <li>أدخل رقم عملية التحويل بالأسفل</li>
        <li>سيُضاف المبلغ تلقائياً بعد التحقق</li>
      </ol>
    </div>

    <?php if ($hasSham): ?>
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
      <label>كود البونص (اختياري) 🎁</label>
      <input name="coupon" placeholder="إذا عندك كود بونص، اكتبه هون">
      <button class="btn full" type="submit">تحقق وشحن</button>
    </form>
    <p class="muted small" style="margin-top:10px; text-align:center">
      أو فعّل كود الخصم من <a href="/coupon.php" style="color:var(--accent)">صفحة الأكواد 🎁</a>
    </p>
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
