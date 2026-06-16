<?php
require_once __DIR__ . '/order_tracker.php';
require_login();
$U = current_user();

// تحديث حالات الطلبات المعلقة (مع إشعار الأدمن عند التنفيذ)
track_pending_orders($U['id'], 20);

$st = db()->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 50");
$st->execute([$U['id']]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);

$labels = ['pending' => ['قيد التنفيذ ⏳', 'st-pending'], 'accept' => ['تم التنفيذ ✅', 'st-ok'], 'reject' => ['مرفوض — أُعيد المبلغ ❌', 'st-no']];

$pageTitle = 'طلباتي';
include __DIR__ . '/header.php'; ?>

<h1 class="section-title">طلباتي</h1>
<?php if (!$orders): ?>
  <p class="empty">ما عندك طلبات بعد — تصفّح <a href="/index.php">الأقسام</a> واطلب أول منتج.</p>
<?php else: ?>
<div class="orders-list">
  <?php foreach ($orders as $o): [$txt, $cls] = $labels[$o['status']] ?? [$o['status'], '']; ?>
    <div class="card order-card">
      <div class="o-head">
        <b>#<?= $o['id'] ?></b>
        <span class="badge <?= $cls ?>"><?= $txt ?></span>
      </div>
      <div class="o-body">
        <div><?= e($o['product_name']) ?> × <?= $o['qty'] ?></div>
        <?php if ($o['player_id']): ?><div class="muted">ID: <?= e($o['player_id']) ?></div><?php endif; ?>
        <?php if ($o['status'] === 'pending'): ?>
          <div class="eta-note">⏱ الوقت المتوقع للتنفيذ: من دقيقة إلى 10 دقائق</div>
        <?php endif; ?>
        <?php
          $codes = $o['codes'] ? json_decode($o['codes'], true) : [];
          // تسطيح أي قيم متداخلة وتحويلها لنصوص
          $flatCodes = [];
          if (is_array($codes)) {
              array_walk_recursive($codes, function($v) use (&$flatCodes) {
                  $v = trim((string)$v);
                  if ($v !== '') $flatCodes[] = $v;
              });
          }
          if ($flatCodes): ?>
          <div class="codes-box">
            🎟 الكود:
            <?php foreach ($flatCodes as $c): ?>
              <b class="copyable" onclick="copyText('<?= e($c) ?>')"><?= e($c) ?> 📋</b>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="o-total"><?= number_format($o['total']) ?> ل.س</div>
        <div class="muted small"><?= e($o['created_at']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php';
