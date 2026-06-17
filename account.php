<?php
require_once __DIR__ . '/order_tracker.php';
require_login();
$U = current_user();
$msg = ''; $ok = false;

// فحص هدية عيد الميلاد
check_birthday_gift($U);
$U = current_user();

// حفظ البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $birthday = trim($_POST['birthday'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    db()->prepare("UPDATE users SET birthday=?, phone=? WHERE id=?")
        ->execute([$birthday ?: null, $phone ?: null, $U['id']]);
    $ok = true; $msg = 'تم حفظ بياناتك ✅';
    $U = current_user();
}

$vip = user_vip_info($U['id']);

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

  <!-- تعديل البيانات -->
  <div class="card">
    <h3>بياناتي الشخصية</h3>
    <?php if ($msg): ?><div class="alert <?= $ok ? 'ok' : '' ?>"><?= e($msg) ?></div><?php endif; ?>
    <form method="post">
      <label>📱 رقم الموبايل</label>
      <input name="phone" value="<?= e($U['phone'] ?? '') ?>" placeholder="مثال: 0991234567">
      <label>🎂 تاريخ ميلادك <?= bday_gift_amount() > 0 ? '(تربح ' . number_format(bday_gift_amount()) . ' ل.س هدية كل عيد ميلاد!)' : '' ?></label>
      <input name="birthday" type="date" value="<?= e($U['birthday'] ?? '') ?>">
      <button class="btn full" name="save_profile" value="1">حفظ</button>
    </form>
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
