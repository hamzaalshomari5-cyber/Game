<?php
require_once __DIR__ . '/db.php';
require_login();
$U = current_user();
$msg = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code === '') {
        $msg = 'اكتب كود الخصم';
    } else {
        $st = db()->prepare("SELECT * FROM coupons WHERE code=? AND active=1 AND (scope IS NULL OR scope <> 'price')");
        $st->execute([$code]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            $msg = 'كود الخصم غير صحيح أو غير فعّال';
        } elseif ($c['max_uses'] > 0 && $c['used'] >= $c['max_uses']) {
            $msg = 'انتهت صلاحية كود الخصم (تم استخدامه بالكامل)';
        } elseif (!empty($c['user_id']) && (int)$c['user_id'] !== (int)$U['id']) {
            $msg = 'هذا الكود خاص بحساب آخر';
        } else {
            // مرة واحدة لكل مستخدم
            $st = db()->prepare("SELECT COUNT(*) FROM coupon_uses WHERE coupon_id=? AND user_id=?");
            $st->execute([$c['id'], $U['id']]);
            if ($st->fetchColumn()) {
                $msg = 'استخدمت هذا الكود مسبقاً';
            } else {
                // الكوبون هنا يعطي مبلغ ثابت مباشرة للمحفظة
                // (نوع percent يُحسب على قيمة ثابتة مرجعية، والأفضل للكوبون المنفصل أن يكون مبلغ ثابت)
                if ($c['type'] === 'fixed') {
                    $bonus = (float)$c['amount'];
                } else {
                    // نسبة — تُطبّق كمبلغ ثابت بسيط (نعتبر النسبة على 100000 كمكافأة ترحيبية)
                    // الأفضل استخدام أكواد "مبلغ ثابت" للتفعيل المباشر
                    $bonus = 0;
                    $msg = 'هذا الكود من نوع نسبة ويُطبّق عند الإيداع فقط';
                }
                if ($bonus > 0) {
                    db()->beginTransaction();
                    db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$bonus, $U['id']]);
                    db()->prepare("UPDATE coupons SET used = used + 1 WHERE id=?")->execute([$c['id']]);
                    db()->prepare("INSERT INTO coupon_uses (coupon_id,user_id) VALUES (?,?)")->execute([$c['id'], $U['id']]);
                    db()->commit();
                    $ok = true;
                    $msg = 'تم تفعيل الكود! أُضيف ' . number_format($bonus) . ' ل.س لمحفظتك 🎁';
                    $U = current_user();
                    notify_user($U['id'], 'تم تفعيل كود الخصم 🎁', 'أُضيف ' . number_format($bonus) . ' ل.س لمحفظتك.', '🎁');
                    notify_admin("🎁 <b>تفعيل كود خصم</b>\nالمستخدم: " . e($U['name']) . "\nالكود: $code\nالمبلغ: " . number_format($bonus) . " ل.س");
                }
            }
        }
    }
}

$pageTitle = 'كود الخصم';
include __DIR__ . '/header.php'; ?>

<div class="coupon-page">
  <div class="card balance-card">
    <div class="muted">رصيد محفظتك</div>
    <div class="big-balance bal-amount-big" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> <span>ل.س</span></div>
  </div>

  <div class="card">
    <div class="coupon-head">
      <div class="coupon-icon">🎁</div>
      <div>
        <h2>كود الخصم</h2>
        <p class="muted small">عندك كود خصم؟ فعّله هون واحصل على رصيد إضافي</p>
      </div>
    </div>

    <?php if ($msg): ?><div class="alert <?= $ok ? 'ok' : '' ?>"><?= e($msg) ?></div><?php endif; ?>

    <form method="post">
      <label>أدخل كود الخصم</label>
      <input name="code" placeholder="مثال: WELCOME" required style="text-transform:uppercase; text-align:center; font-weight:800; letter-spacing:2px; font-size:1.1rem">
      <button class="btn full" type="submit">تفعيل الكود</button>
    </form>

    <p class="muted small" style="margin-top:14px; text-align:center">
      💡 بعض الأكواد تُفعّل هنا مباشرة، وبعضها يُطبّق تلقائياً عند شحن المحفظة.
    </p>
  </div>
</div>

<?php include __DIR__ . '/footer.php';
