<?php
require_once __DIR__ . '/fastcard_api.php';
require_login();
$U = current_user();

// تحديث حالات الطلبات المعلقة (آخر 20)
$st = db()->prepare("SELECT * FROM orders WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 20");
$st->execute([$U['id']]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $o) {
    $chk = fc_check_uuid($o['uuid']);
    if (!$chk || !$chk['status']) continue;
    $s = $chk['status'];
    $codes = !empty($chk['codes']) ? json_encode(array_filter($chk['codes']), JSON_UNESCAPED_UNICODE) : $o['codes'];
    if ($s === 'accept' || $s === 'completed') {
        db()->prepare("UPDATE orders SET status='accept', codes=?, fc_order_id=COALESCE(fc_order_id,?), updated_at=datetime('now') WHERE id=?")
            ->execute([$codes, $chk['id'], $o['id']]);
    } elseif ($s === 'reject' || $s === 'rejected') {
        db()->beginTransaction();
        db()->prepare("UPDATE orders SET status='reject', updated_at=datetime('now') WHERE id=?")->execute([$o['id']]);
        db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$o['total'], $o['user_id']]);
        db()->commit();
    }
}

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
        <?php
          $codes = $o['codes'] ? json_decode($o['codes'], true) : [];
          if ($codes): ?>
          <div class="codes-box">
            🎟 الكود:
            <?php foreach ($codes as $c): ?>
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
