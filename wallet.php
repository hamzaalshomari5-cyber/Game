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
    echo "الطريقة: $method\n";
    echo "GSM/Address: " . ($method==='shamcash'?shamcash_account():syriatel_gsm()) . "\n";
    echo "مفتاح apisyria: " . (apisyria_key()!==''?'موجود (طول '.strlen(apisyria_key()).')':'فارغ ❌') . "\n";
    echo "الرابط: $shown\n\n";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "HTTP: $code\n";
    if ($err) echo "CURL_ERR: $err\n";
    echo "\nالرد:\n$res";
    exit;
}

// التحقق من تحويل (سيرياتيل أو شام كاش) عبر apisyria — حسب التوثيق الرسمي
function verify_tx($txId, $method) {
    $base = 'https://apisyria.com/api/v1';
    if ($method === 'shamcash') {
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
    $close = curl_close($ch);
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
    if (!empty($c['user_id']) && (int)$c['user_id'] !== (int)$userId) {
        return [0, 'هذا الكود خاص بحساب آخر'];
    }
    $st = db()->prepare("SELECT COUNT(*) FROM coupon_uses WHERE coupon_id=? AND user_id=?");
    $st->execute([$c['id'], $userId]);
    if ($st->fetchColumn()) return [0, 'استخدمت هذا الكود مسبقاً'];
    return [$c, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txId = trim($_POST['tx_id'] ?? '');
    $method = ($_POST['method'] ?? 'syriatel') === 'shamcash' ? 'shamcash' : 'syriatel';
    $currency = ($_POST['currency'] ?? 'syp') === 'usd' ? 'usd' : 'syp';
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
                // ------ 🟢 تعديل الحسبة بدقة هنا 🟢 ------
                if ($method === 'shamcash' && $currency === 'usd') {
                    // إذا كان الشحن بالدولار، نضربه مباشرة بسعر الصرف الحالي بدون إضافة أصفار
                    $amount = round($amount * usd_rate());
                } else {
                    // إذا كان الشحن بالليرة السورية (سيرياتيل أو شام كاش سوري)، نضربه بـ 100 لإضافة الـ 00 للعملة القديمة
                    $amount = $amount * 100;
                }
                // ----------------------------------------
                
                $bonus = 0; $couponObj = null; $couponMsg = null;
                if ($couponCode !== '') {
                    [$couponObj, $couponMsg] = check_coupon($couponCode, $U['id']);
                    if ($couponObj) {
                        if ($couponObj['type'] === 'percent') $bonus = round($amount * $couponObj['amount'] / 100);
                        else $bonus = $couponObj['amount'];
                    }
                }
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
                notify_user($U['id'], 'تم شحن محفظتك 💰', 'أُضيف ' . number_format($total) . ' ل.س' . ($bonus > 0 ? ' (منها ' . number_format($bonus) . ' مكافأة)' : '') . ' لمحفظتك.', '💰');
                notify_admin("💰 <b>إيداع جديد</b>\nالمستخدم: " . e($U['name']) . "\nالمبلغ: " . number_format($total) . " ل.س\nالطريقة: " . ($method === 'shamcash' ? 'شام كاش' : 'سيرياتيل كاش') . "\nرقم العملية: $txId");
            }
        }
    }
}

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
        <li>اختر عملة تحويلك (سوري أو دولار)</li>
        <li>أدخل رقم عملية التحويل بالأسفل</li>
        <li>سيُضاف المبلغ تلقائياً بعد التحقق</li>
      </ol>
      <div class="cur-choice">
        <span class="cur-lbl">عملة التحويل:</span>
        <div class="cur-btns">
          <button type="button" class="cur-btn active" data-cur="syp" onclick="selectCur(this)">🇸🇾 ليرة سورية</button>
          <button type="button" class="cur-btn" data-cur="usd" onclick="selectCur(this)">💵 دولار</button>
        </div>
        <p class="muted small" id="curNote" style="display:none;margin-top:6px">سيُحوّل مبلغ الدولار لليرة حسب سعر الصرف الحالي (<?= number_format(usd_rate()) ?> ل.س للدولار).</p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($msg): ?><div class="alert <?= $ok ? 'ok' : '' ?>"><?= e($msg) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="method" id="payMethod" value="syriatel">
      <input type="hidden" name="currency" id="payCurrency" value="syp">
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
  if (m !== 'shamcash') {
    document.getElementById('payCurrency').value = 'syp';
  }
}
function selectCur(btn) {
  document.querySelectorAll('.cur-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const c = btn.dataset.cur;
  document.getElementById('payCurrency').value = c;
  const note = document.getElementById('curNote');
  if (note) note.style.display = (c === 'usd') ? 'block' : 'none';
}
</script>

<style>
.cur-choice { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border, rgba(255,255,255,.1)); }
.cur-lbl { font-size: .85rem; color: var(--muted, #888); display: block; margin-bottom: 8px; }
.cur-btns { display: flex; gap: 8px; }
.cur-btn {
  flex: 1; padding: 10px; border-radius: 10px;
  border: 1px solid var(--border, rgba(255,255,255,.15));
  background: var(--card2, rgba(255,255,255,.04)); color: var(--text, #fff);
  font-size: .88rem; font-weight: 700; cursor: pointer; transition: all .2s;
}
.cur-btn.active {
  background: var(--accent, #d4af37); color: #1a1a1a;
  border-color: var(--accent, #d4af37);
}
</style>

<?php include __DIR__ . '/footer.php';
