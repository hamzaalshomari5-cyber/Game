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
    // إضافة كوبون
    if (isset($_POST['add_coupon'])) {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $type = ($_POST['coupon_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $amt = (float)($_POST['coupon_amount'] ?? 0);
        $maxu = (int)($_POST['coupon_maxuses'] ?? 0);
        if ($code && $amt > 0) {
            try {
                db()->prepare("INSERT INTO coupons (code,type,amount,max_uses) VALUES (?,?,?,?)")
                    ->execute([$code, $type, $amt, $maxu]);
                $msg = 'تم إضافة كود الخصم ✅';
            } catch (Exception $e) { $msg = 'الكود موجود مسبقاً'; }
        } else { $msg = 'تأكد من الكود والقيمة'; }
    }
    // حذف/تفعيل كوبون
    if (isset($_POST['toggle_coupon'])) {
        db()->prepare("UPDATE coupons SET active = 1 - active WHERE id=?")->execute([(int)$_POST['toggle_coupon']]);
        $msg = 'تم التحديث ✅';
    }
    if (isset($_POST['del_coupon'])) {
        db()->prepare("DELETE FROM coupons WHERE id=?")->execute([(int)$_POST['del_coupon']]);
        $msg = 'تم حذف الكود ✅';
    }
    // إضافة سلايد
    if (isset($_POST['add_slide'])) {
        $img = trim($_POST['slide_image'] ?? '');
        $link = trim($_POST['slide_link'] ?? '');
        $sort = (int)($_POST['slide_sort'] ?? 0);
        if ($img) {
            db()->prepare("INSERT INTO slides (image,link,sort) VALUES (?,?,?)")->execute([$img, $link, $sort]);
            $msg = 'تم إضافة الصورة ✅';
        } else { $msg = 'أدخل رابط الصورة'; }
    }
    if (isset($_POST['del_slide'])) {
        db()->prepare("DELETE FROM slides WHERE id=?")->execute([(int)$_POST['del_slide']]);
        $msg = 'تم حذف الصورة ✅';
    }
}

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$monthAgo = date('Y-m-d', strtotime('-30 days'));
$dateCol = is_pg() ? "created_at::date" : "date(created_at)";
$stats = [
    'users'   => db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'orders'  => db()->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'sales'   => db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept'")->fetchColumn(),
    'pending' => db()->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
];
// مبيعات زمنية (الطلبات المنفّذة)
$salesToday = db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept' AND $dateCol = '$today'")->fetchColumn();
$salesWeek  = db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept' AND $dateCol >= '$weekAgo'")->fetchColumn();
$salesMonth = db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept' AND $dateCol >= '$monthAgo'")->fetchColumn();
$ordersToday = db()->query("SELECT COUNT(*) FROM orders WHERE $dateCol = '$today'")->fetchColumn();
$topupsTotal = db()->query("SELECT COALESCE(SUM(amount),0) FROM topups")->fetchColumn();
$topupsToday = db()->query("SELECT COALESCE(SUM(amount),0) FROM topups WHERE $dateCol = '$today'")->fetchColumn();
// أكثر 5 منتجات مبيعاً
$topProducts = db()->query("SELECT product_name, COUNT(*) cnt, COALESCE(SUM(total),0) revenue FROM orders WHERE status='accept' GROUP BY product_name ORDER BY cnt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$fcProfile = $tab === 'stats' ? fc_profile() : null;
$fcBalance = is_array($fcProfile) ? ($fcProfile['balance'] ?? $fcProfile['data']['balance'] ?? null) : null;

$pageTitle = 'لوحة الأدمن';
include __DIR__ . '/header.php'; ?>

<h1 class="section-title">لوحة الأدمن 🛠</h1>
<div class="tabs">
  <a class="<?= $tab === 'stats' ? 'on' : '' ?>" href="?tab=stats">إحصائيات</a>
  <a class="<?= $tab === 'orders' ? 'on' : '' ?>" href="?tab=orders">الطلبات</a>
  <a class="<?= $tab === 'topups' ? 'on' : '' ?>" href="?tab=topups">الإيداعات</a>
  <a class="<?= $tab === 'users' ? 'on' : '' ?>" href="?tab=users">المستخدمين</a>
  <a class="<?= $tab === 'coupons' ? 'on' : '' ?>" href="?tab=coupons">كوبونات</a>
  <a class="<?= $tab === 'slides' ? 'on' : '' ?>" href="?tab=slides">السلايدر</a>
  <a class="<?= $tab === 'settings' ? 'on' : '' ?>" href="?tab=settings">الإعدادات</a>
</div>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>

<?php if ($tab === 'stats'): ?>
  <!-- الأرباح الزمنية -->
  <div class="grid stats-grid">
    <div class="card stat highlight"><div class="n"><?= number_format($salesToday) ?></div><div>💰 مبيعات اليوم (ل.س)</div></div>
    <div class="card stat"><div class="n"><?= number_format($salesWeek) ?></div><div>📅 آخر 7 أيام</div></div>
    <div class="card stat"><div class="n"><?= number_format($salesMonth) ?></div><div>📆 آخر 30 يوم</div></div>
    <div class="card stat"><div class="n"><?= $ordersToday ?></div><div>🛒 طلبات اليوم</div></div>
  </div>

  <!-- إجماليات -->
  <div class="grid stats-grid" style="margin-top:14px">
    <div class="card stat"><div class="n"><?= $stats['users'] ?></div><div>👥 مستخدم</div></div>
    <div class="card stat"><div class="n"><?= $stats['orders'] ?></div><div>📦 إجمالي الطلبات</div></div>
    <div class="card stat"><div class="n"><?= number_format($stats['sales']) ?></div><div>✅ إجمالي المبيعات</div></div>
    <div class="card stat"><div class="n"><?= $stats['pending'] ?></div><div>⏳ قيد التنفيذ</div></div>
    <div class="card stat"><div class="n"><?= number_format($topupsTotal) ?></div><div>💳 إجمالي الإيداعات</div></div>
    <div class="card stat"><div class="n"><?= number_format($topupsToday) ?></div><div>💵 إيداعات اليوم</div></div>
    <?php if ($fcBalance !== null): ?>
      <div class="card stat <?= (float)$fcBalance < 5 ? 'warn' : '' ?>"><div class="n"><?= number_format((float)$fcBalance, 2) ?></div><div>🔋 رصيدك في FastCard ($)</div></div>
    <?php endif; ?>
  </div>

  <!-- أكثر المنتجات مبيعاً -->
  <?php if ($topProducts): ?>
  <div class="card" style="margin-top:14px">
    <h3>🏆 أكثر المنتجات مبيعاً</h3>
    <table class="tbl">
      <tr><th>المنتج</th><th>عدد المبيعات</th><th>الإيرادات</th></tr>
      <?php foreach ($topProducts as $i => $tp): ?>
        <tr>
          <td><?= ['🥇','🥈','🥉','4️⃣','5️⃣'][$i] ?? '' ?> <?= e($tp['product_name']) ?></td>
          <td><b><?= $tp['cnt'] ?></b></td>
          <td><?= number_format($tp['revenue']) ?> ل.س</td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

<?php elseif ($tab === 'orders'):
  $orders = db()->query("SELECT o.*, u.name uname FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  $statusLabels = ['accept' => '✅ تم', 'pending' => '⏳ قيد التنفيذ', 'reject' => '❌ مرفوض']; ?>
  <div class="card">
    <h3>الطلبات (<?= count($orders) ?>)</h3>
    <input type="text" id="ordSearch" placeholder="🔍 ابحث بالاسم أو المنتج أو ID..." onkeyup="filterRows('ordSearch','ordersTable')" style="margin-bottom:12px">
    <table class="tbl" id="ordersTable">
      <tr><th>#</th><th>المستخدم</th><th>المنتج</th><th>ID</th><th>الإجمالي</th><th>الحالة</th><th>التاريخ</th></tr>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= $o['id'] ?></td><td><?= e($o['uname']) ?></td>
          <td><?= e($o['product_name']) ?> ×<?= $o['qty'] ?></td>
          <td class="small"><?= e($o['player_id']) ?></td>
          <td><b><?= number_format($o['total']) ?></b></td>
          <td><?= $statusLabels[$o['status']] ?? e($o['status']) ?></td>
          <td class="small"><?= e($o['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <script>
  function filterRows(inputId, tableId) {
    const q = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tr').forEach((row, i) => {
      if (i === 0) return;
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
  </script>

<?php elseif ($tab === 'topups'):
  $topups = db()->query("SELECT t.*, u.name uname, u.email FROM topups t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  $totalIn = db()->query("SELECT COALESCE(SUM(amount),0) FROM topups")->fetchColumn(); ?>
  <div class="card">
    <h3>الإيداعات — إجمالي: <?= number_format($totalIn) ?> ل.س</h3>
    <input type="text" id="topupSearch" placeholder="🔍 ابحث بالاسم أو رقم العملية..." onkeyup="filterRows('topupSearch','topupsTable')" style="margin-bottom:12px">
    <table class="tbl" id="topupsTable">
      <tr><th>#</th><th>المستخدم</th><th>رقم العملية</th><th>المبلغ</th><th>كوبون</th><th>التاريخ</th></tr>
      <?php foreach ($topups as $t): ?>
        <tr>
          <td><?= $t['id'] ?></td>
          <td><?= e($t['uname']) ?></td>
          <td class="small"><?= e($t['tx_id']) ?></td>
          <td><b><?= number_format($t['amount']) ?></b></td>
          <td><?= e($t['coupon'] ?? '') ?></td>
          <td class="small"><?= e($t['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <script>
  function filterRows(inputId, tableId) {
    const q = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tr').forEach((row, i) => {
      if (i === 0) return;
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
  </script>

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

<?php elseif ($tab === 'coupons'):
  $coupons = db()->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <h3>إضافة كود خصم 🎁</h3>
    <p class="muted">كود الخصم بيعطي المستخدم <b>مكافأة إضافية</b> على مبلغ الإيداع. مثلاً 10% يعني لو أودع 100,000 بياخد 110,000.</p>
    <form method="post" class="inline-form">
      <input name="coupon_code" placeholder="الكود مثلاً WELCOME" required style="text-transform:uppercase">
      <select name="coupon_type">
        <option value="percent">نسبة %</option>
        <option value="fixed">مبلغ ثابت ل.س</option>
      </select>
      <input name="coupon_amount" type="number" step="any" placeholder="القيمة" required>
      <input name="coupon_maxuses" type="number" placeholder="حد الاستخدام (0=لا نهائي)">
      <button class="btn" name="add_coupon" value="1">إضافة</button>
    </form>
  </div>
  <div class="card">
    <h3>الأكواد (<?= count($coupons) ?>)</h3>
    <?php if (!$coupons): ?><p class="empty">ما في أكواد بعد.</p><?php else: ?>
    <table class="tbl">
      <tr><th>الكود</th><th>القيمة</th><th>الاستخدام</th><th>الحالة</th><th>إجراء</th></tr>
      <?php foreach ($coupons as $c): ?>
        <tr>
          <td><b><?= e($c['code']) ?></b></td>
          <td><?= $c['type'] === 'percent' ? e($c['amount']).'%' : number_format($c['amount']).' ل.س' ?></td>
          <td><?= $c['used'] ?><?= $c['max_uses'] > 0 ? '/'.$c['max_uses'] : '' ?></td>
          <td><?= $c['active'] ? '✅ فعّال' : '⛔ موقوف' ?></td>
          <td style="white-space:nowrap">
            <form method="post" style="display:inline"><button class="btn-mini" name="toggle_coupon" value="<?= $c['id'] ?>"><?= $c['active'] ? 'إيقاف' : 'تفعيل' ?></button></form>
            <form method="post" style="display:inline" onsubmit="return confirm('حذف الكود؟')"><button class="btn-mini danger" name="del_coupon" value="<?= $c['id'] ?>">حذف</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'slides'):
  $slides = db()->query("SELECT * FROM slides ORDER BY sort ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <h3>إضافة صورة للسلايدر 🖼</h3>
    <p class="muted">حط رابط صورة (URL) — يظهر بالرئيسية. ممكن تضيف رابط يفتح عند الضغط (اختياري).</p>
    <form method="post">
      <label>رابط الصورة</label>
      <input name="slide_image" placeholder="https://..." required>
      <label>رابط عند الضغط (اختياري)</label>
      <input name="slide_link" placeholder="https://... أو /index.php?page=products&cat=...">
      <label>الترتيب</label>
      <input name="slide_sort" type="number" value="0">
      <button class="btn full" name="add_slide" value="1">إضافة الصورة</button>
    </form>
  </div>
  <div class="card">
    <h3>الصور (<?= count($slides) ?>)</h3>
    <?php if (!$slides): ?><p class="empty">ما في صور بعد.</p><?php else: ?>
      <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
        <?php foreach ($slides as $s): ?>
          <div style="position:relative">
            <img src="<?= e($s['image']) ?>" style="width:100%;border-radius:10px;border:1px solid var(--border)" alt="">
            <form method="post" onsubmit="return confirm('حذف الصورة؟')" style="margin-top:6px">
              <button class="btn-mini danger full" name="del_slide" value="<?= $s['id'] ?>">حذف</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

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
