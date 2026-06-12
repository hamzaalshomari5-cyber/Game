<?php
require_once __DIR__ . '/fastcard_api.php';
$U = require_admin();
$tab = $_GET['tab'] ?? 'stats';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['profit_percent'])) {
        set_setting('profit_percent', (float)$_POST['profit_percent']);
        cache_set('fc_products', cache_get('fc_products') ?? [], 0); // إبطال الكاش
        $msg = 'تم حفظ هامش الربح ✅';
    }
    if (isset($_POST['add_balance_user'], $_POST['add_balance_amount'])) {
        db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")
            ->execute([(float)$_POST['add_balance_amount'], (int)$_POST['add_balance_user']]);
        $msg = 'تم تعديل الرصيد ✅';
    }
    if (isset($_POST['sync_products'])) {
        fc_products(true);
        $msg = 'تمت مزامنة المنتجات من FastCard ✅';
    }
}

$stats = [
    'users'   => db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'orders'  => db()->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'sales'   => db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept'")->fetchColumn(),
    'pending' => db()->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
];
$fcProfile = $tab === 'stats' ? fc_profile() : null;
$fcBalance = is_array($fcProfile) ? ($fcProfile['balance'] ?? $fcProfile['data']['balance'] ?? null) : null;

$pageTitle = 'لوحة الأدمن';
include __DIR__ . '/header.php'; ?>

<h1 class="section-title">لوحة الأدمن 🛠</h1>
<div class="tabs">
  <a class="<?= $tab === 'stats' ? 'on' : '' ?>" href="?tab=stats">إحصائيات</a>
  <a class="<?= $tab === 'orders' ? 'on' : '' ?>" href="?tab=orders">الطلبات</a>
  <a class="<?= $tab === 'users' ? 'on' : '' ?>" href="?tab=users">المستخدمين</a>
  <a class="<?= $tab === 'settings' ? 'on' : '' ?>" href="?tab=settings">الإعدادات</a>
</div>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>

<?php if ($tab === 'stats'): ?>
  <div class="grid stats-grid">
    <div class="card stat"><div class="n"><?= $stats['users'] ?></div><div>مستخدم</div></div>
    <div class="card stat"><div class="n"><?= $stats['orders'] ?></div><div>طلب</div></div>
    <div class="card stat"><div class="n"><?= number_format($stats['sales']) ?></div><div>مبيعات (ل.س)</div></div>
    <div class="card stat"><div class="n"><?= $stats['pending'] ?></div><div>قيد التنفيذ</div></div>
    <?php if ($fcBalance !== null): ?>
      <div class="card stat"><div class="n"><?= number_format((float)$fcBalance) ?></div><div>رصيدك في FastCard</div></div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'orders'):
  $orders = db()->query("SELECT o.*, u.name uname FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <table class="tbl">
      <tr><th>#</th><th>المستخدم</th><th>المنتج</th><th>ID</th><th>الإجمالي</th><th>الحالة</th><th>التاريخ</th></tr>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= $o['id'] ?></td><td><?= e($o['uname']) ?></td>
          <td><?= e($o['product_name']) ?> ×<?= $o['qty'] ?></td>
          <td><?= e($o['player_id']) ?></td>
          <td><?= number_format($o['total']) ?></td>
          <td><?= e($o['status']) ?></td>
          <td class="small"><?= e($o['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php elseif ($tab === 'users'):
  $users = db()->query("SELECT * FROM users ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <h3>تعديل رصيد مستخدم</h3>
    <form method="post" class="inline-form">
      <input name="add_balance_user" type="number" placeholder="رقم المستخدم" required>
      <input name="add_balance_amount" type="number" step="any" placeholder="المبلغ (سالب للخصم)" required>
      <button class="btn">تنفيذ</button>
    </form>
  </div>
  <div class="card">
    <table class="tbl">
      <tr><th>#</th><th>الاسم</th><th>الإيميل</th><th>الرصيد</th><th>الدور</th></tr>
      <?php foreach ($users as $u): ?>
        <tr><td><?= $u['id'] ?></td><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td>
            <td><?= number_format($u['balance']) ?></td><td><?= e($u['role']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php else: ?>
  <div class="card">
    <h3>هامش الربح</h3>
    <form method="post" class="inline-form">
      <input name="profit_percent" type="number" step="any" value="<?= e(setting('profit_percent', DEFAULT_PROFIT)) ?>" required>
      <span class="muted">% فوق سعر FastCard</span>
      <button class="btn">حفظ</button>
    </form>
  </div>
  <div class="card">
    <h3>مزامنة المنتجات</h3>
    <p class="muted">تُحدَّث المنتجات تلقائياً كل 5 دقائق — أو حدّثها الآن:</p>
    <form method="post"><button class="btn" name="sync_products" value="1">مزامنة الآن 🔄</button></form>
  </div>
  <div class="card">
    <h3>ملاحظة</h3>
    <p class="muted">توكن FastCard ومفتاح apisyria يُضبطان في <code>config.php</code> أو عبر متغيرات البيئة <code>FASTCARD_TOKEN</code> و <code>APISYRIA_KEY</code> على Railway.</p>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php';
