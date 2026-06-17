<?php
require_once __DIR__ . '/order_tracker.php';
require_login();
$U = current_user();
$msg = ''; $ok = false;

$vip = user_vip_info($U['id']);

// حالة توثيق الهوية
$st = db()->prepare("SELECT status FROM id_verifications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$U['id']]);
$idvStatus = $st->fetchColumn(); // pending / rejected / approved / false

// سجل النشاط: آخر الطلبات والإيداعات مدمجة
$acts = [];
$st = db()->prepare("SELECT 'order' typ, product_name title, total amount, status, created_at FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 15");
$st->execute([$U['id']]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $acts[] = $r;
$st = db()->prepare("SELECT 'topup' typ, tx_id title, amount, 'accept' status, created_at FROM topups WHERE user_id=? ORDER BY id DESC LIMIT 15");
$st->execute([$U['id']]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $acts[] = $r;
usort($acts, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
$acts = array_slice($acts, 0, 20);

$pageTitle = 'حسابي';
include __DIR__ . '/header.php'; ?>

<div class="account-page">
  <h1 class="section-title">👤 حسابي</h1>

  <!-- بطاقة VIP -->
  <div class="vip-card vip-<?= $vip['level'] ?>">
    <div class="vip-top">
      <div class="vip-badge-big"><?= vip_badge($vip['level']) ?></div>
      <div class="vip-spent">أنفقت: $<?= number_format($vip['spent_usd'], 2) ?></div>
    </div>
    <?php if ($vip['next_level']): ?>
      <?php $th = vip_thresholds(); $target = $th[$vip['next_level']];
        $prog = $target > 0 ? min(100, ($vip['spent_usd'] / $target) * 100) : 0; ?>
      <div class="vip-progress"><div class="vip-progress-bar" style="width:<?= $prog ?>%"></div></div>
      <div class="vip-next">باقي <b>$<?= number_format($vip['need_usd'], 2) ?></b> للوصول إلى <?= vip_badge($vip['next_level']) ?></div>
      <?php if ($vip['level'] >= 2): ?><div class="vip-perk">🎁 تواصل مع الدعم لكود الخصم الخاص بك</div><?php endif; ?>
    <?php else: ?>
      <div class="vip-next">🏆 وصلت لأعلى مستوى!</div>
    <?php endif; ?>
  </div>

  <!-- معلومات الحساب -->
  <div class="card">
    <h3>معلوماتي</h3>
    <div class="acc-info">
      <div><span class="muted">الاسم:</span> <b><?= e($U['name']) ?></b></div>
      <div><span class="muted">الإيميل:</span> <b><?= e($U['email']) ?></b></div>
      <div><span class="muted">الرصيد:</span> <b class="bal-amount" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> ل.س</b></div>
    </div>
  </div>

  <!-- توثيق رقم الموبايل -->
  <div class="card">
    <h3>📱 رقم الموبايل</h3>
    <?php if (!empty($U['phone_verified'])): ?>
      <div class="phone-verified">
        ✅ رقمك موثّق: <b dir="ltr"><?= e($U['phone']) ?></b>
      </div>
    <?php elseif (!otp_enabled()): ?>
      <p class="muted">خدمة التوثيق غير مفعّلة حالياً.</p>
    <?php else: ?>
      <p class="muted small">وثّق رقم موبايلك ليصبح حسابك أكثر أماناً. رح يوصلك رمز تحقق على رقمك.</p>
      <div id="otpMsg" class="alert" style="display:none"></div>

      <!-- خطوة 1: إدخال الرقم -->
      <div id="otpStep1">
        <label>رقم الموبايل</label>
        <input id="otpPhone" type="tel" dir="ltr" value="<?= e($U['phone'] ?? '') ?>" placeholder="0991234567">
        <button class="btn full" id="otpSendBtn" onclick="otpSend()">إرسال رمز التحقق</button>
      </div>

      <!-- خطوة 2: إدخال الرمز -->
      <div id="otpStep2" style="display:none">
        <label>أدخل الرمز المرسل (6 أرقام)</label>
        <input id="otpCode" type="tel" dir="ltr" inputmode="numeric" maxlength="6" placeholder="123456">
        <button class="btn full" id="otpVerifyBtn" onclick="otpVerify()">تأكيد الرمز</button>
        <button class="btn full ghost" onclick="otpReset()" style="margin-top:8px">تغيير الرقم</button>
      </div>
    <?php endif; ?>
  </div>

  <!-- توثيق الهوية -->
  <div class="card">
    <h3>🪪 توثيق الهوية</h3>
    <?php if (!empty($U['id_verified'])): ?>
      <div class="phone-verified">✅ هويتك موثّقة</div>
    <?php elseif ($idvStatus === 'pending'): ?>
      <div class="alert" style="display:block">🪪 طلب التوثيق قيد المراجعة، رح يوصلك إشعار عند الموافقة.</div>
    <?php else: ?>
      <p class="muted small">
        ارفع صورة واضحة لهويتك أو بطاقتك الشخصية ليتم توثيق حسابك.
        <?= $idvStatus === 'rejected' ? '<br><span style="color:var(--no,#ef4444)">⚠️ طلبك السابق مرفوض، حاول بصورة أوضح.</span>' : '' ?>
      </p>
      <div id="idvMsg" class="alert" style="display:none"></div>
      <input type="file" id="idImage" accept="image/*" style="display:none" onchange="idPreview(event)">
      <div id="idPreviewWrap" style="display:none; margin-bottom:10px">
        <img id="idPreviewImg" style="max-width:100%; border-radius:10px; border:1px solid var(--border)">
      </div>
      <button class="btn full ghost" onclick="document.getElementById('idImage').click()" id="idPickBtn">📷 اختر صورة الهوية</button>
      <button class="btn full" onclick="idUpload()" id="idUploadBtn" style="display:none; margin-top:8px">إرسال للمراجعة</button>
    <?php endif; ?>
  </div>

  <!-- سجل النشاط -->
  <div class="card">
    <h3>📜 سجل النشاط</h3>
    <?php if (!$acts): ?><p class="empty">ما في نشاط بعد.</p><?php else: ?>
      <div class="activity-list">
        <?php foreach ($acts as $a): ?>
          <div class="activity-item">
            <div class="act-icon"><?= $a['typ'] === 'order' ? '🛒' : '💰' ?></div>
            <div class="act-body">
              <div class="act-title"><?= $a['typ'] === 'order' ? e($a['title']) : 'إيداع رصيد' ?></div>
              <div class="act-time"><?= e($a['created_at']) ?></div>
            </div>
            <div class="act-amount <?= $a['typ'] === 'topup' ? 'plus' : '' ?>">
              <?= $a['typ'] === 'topup' ? '+' : '-' ?><?= number_format($a['amount']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/footer.php';
