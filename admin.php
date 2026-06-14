<?php
require_once __DIR__ . '/fastcard_api.php';
$U = require_admin();
$tab = $_GET['tab'] ?? 'stats';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['usd_rate'])) {
        set_setting('usd_rate', (float)$_POST['usd_rate']);
        $msg = 'تم حفظ سعر الصرف ✅';
    }
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
        store_products(true); fc_content(0, true);
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
    <h3>تعديل رصيد مستخدم 💰</h3>
    <p class="muted">حط رقم المستخدم (من الجدول تحت) والمبلغ — موجب للإضافة، سالب للخصم.</p>
    <form method="post" class="inline-form">
      <input name="add_balance_user" type="number" placeholder="رقم المستخدم" required>
      <input name="add_balance_amount" type="number" step="any" placeholder="المبلغ بالليرة (± )" required>
      <button class="btn">تنفيذ</button>
    </form>
  </div>
  <div class="card">
    <h3>المستخدمين (<?= count($users) ?>)</h3>
    <input type="text" id="userSearch" placeholder="🔍 ابحث بالاسم أو الإيميل..." onkeyup="filterUsers()" style="margin-bottom:12px">
    <table class="tbl" id="usersTable">
      <tr><th>#</th><th>الاسم</th><th>الإيميل</th><th>الرصيد</th><th>الدور</th></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><b><?= $u['id'] ?></b></td>
          <td><?= e($u['name']) ?></td>
          <td class="small"><?= e($u['email']) ?></td>
          <td><b><?= number_format($u['balance']) ?></b></td>
          <td><?= $u['role'] === 'admin' ? '👑 أدمن' : 'مستخدم' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <script>
  function filterUsers() {
    const q = document.getElementById('userSearch').value.toLowerCase();
    document.querySelectorAll('#usersTable tr').forEach((row, i) => {
      if (i === 0) return;
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
  </script>

<?php else: ?>
  <div class="card">
    <h3>سعر صرف الدولار 💱</h3>
    <p class="muted">أسعار FastCard بترجع بالدولار — حط سعر الصرف ليتحول السعر لليرة تلقائياً.</p>
    <form method="post" class="inline-form">
      <input name="usd_rate" type="number" step="any" value="<?= e(setting('usd_rate', 11000)) ?>" required>
      <span class="muted">ل.س لكل 1$</span>
      <button class="btn">حفظ</button>
    </form>
    <?php $sp = store_products(); if ($sp): $x = $sp[0]; ?>
      <p class="muted" style="margin-top:10px">
        ✔ مثال للتأكد: "<?= e($x['name']) ?>" — سعر FastCard: <b><?= e($x['cost']) ?>$</b> →
        سعر البيع عندك: <b><?= number_format($x['price']) ?> ل.س</b>
        (<?= e($x['cost']) ?> × <?= e(setting('usd_rate', 11000)) ?> × <?= 1 + (float)setting('profit_percent', DEFAULT_PROFIT) / 100 ?>)
      </p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>هامش الربح</h3>
    <form method="post" class="inline-form">
      <input name="profit_percent" type="number" step="any" value="<?= e(setting('profit_percent', DEFAULT_PROFIT)) ?>" required>
      <span class="muted">% فوق سعر FastCard</span>
      <button class="btn">حفظ</button>
    </form>
  </div>
  <div class="card">
    <h3>حالة الربط مع FastCard</h3>
    <?php $root = fc_content(0); $np = count(store_products()); ?>
    <p class="muted">
      الأقسام الرئيسية: <b><?= count($root['categories']) ?></b> —
      إجمالي المنتجات: <b><?= $np ?></b><br>
      <?= count($root['categories']) ? '✅ النظام الشجري شغال (content API)' : '⚠️ ما في أقسام — تأكد من التوكن' ?>
    </p>
  </div>
  <div class="card">
    <h3>مزامنة المنتجات</h3>
    <p class="muted">تُحدَّث المنتجات تلقائياً كل 5 دقائق — أو حدّثها الآن:</p>
    <form method="post"><button class="btn" name="sync_products" value="1">مزامنة الآن 🔄</button></form>
  </div>
  <div class="card">
    <h3>الإعدادات والمتغيرات (Railway)</h3>
    <p class="muted">تُضبط عبر متغيرات البيئة على Railway (صندوق الموقع → Variables):</p>
    <p class="muted small" style="line-height:2">
      <code>FASTCARD_TOKEN</code> — توكن FastCard<br>
      <code>APISYRIA_KEY</code> — التحقق من التحويلات<br>
      <code>SHAMCASH_NUMBER</code> — رقم محفظة شام كاش (لتفعيل الإيداع عبرها)<br>
      <code>BOT_CHECK_URL</code> + <code>CHECK_API_SECRET</code> — التحقق من اسم اللاعب<br>
      <code>GOOGLE_CLIENT_ID</code> + <code>GOOGLE_CLIENT_SECRET</code> + <code>SITE_URL</code> — دخول جوجل
    </p>
    <p class="muted small">
      حالة شام كاش: <?= shamcash_number() ? '✅ مفعّل' : '⚠️ غير مفعّل' ?> —
      حالة دخول جوجل: <?= google_enabled() ? '✅ مفعّل' : '⚠️ غير مفعّل' ?>
    </p>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php';
