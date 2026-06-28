<?php
require_once __DIR__ . '/fastcard_api.php';
$U = require_admin();
$tab = $_GET['tab'] ?? 'stats';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['usd_rate'])) {
        set_setting('usd_rate', (float)$_POST['usd_rate']);
        cache_set('fc_products', [], 0); // إبطال الكاش فوراً لتحديث الأسعار
        cache_set('fc_all_products', [], 0);
        $msg = 'تم حفظ سعر الصرف العام لعروض الألعاب ✅';
    }
    if (isset($_POST['usd_rate_sham'])) {
        set_setting('usd_rate_sham', (float)$_POST['usd_rate_sham']);
        cache_set('fc_products', [], 0); // إبطال الكاش فوراً لتحديث الأسعار
        cache_set('fc_all_products', [], 0);
        $msg = 'تم حفظ سعر صرف دولار شام كاش والرصيد ✅';
    }
    if (isset($_POST['profit_percent'])) {
        set_setting('profit_percent', (float)$_POST['profit_percent']);
        cache_set('fc_products', [], 0); // إبطال الكاش
        cache_set('fc_all_products', [], 0);
        $msg = 'تم حفظ هامش الربح ✅';
    }
    if (isset($_POST['add_balance_user'], $_POST['add_balance_amount'])) {
        db()->prepare(\"UPDATE users SET balance = balance + ? WHERE id=?\")
            ->execute([(float)$_POST['add_balance_amount'], (int)$_POST['add_balance_user']]);
        $msg = 'تم تعديل الرصيد ✅';
    }
    // حظر / فك حظر مستخدم (ما عدا الأدمن)
    if (isset($_POST['toggle_ban'])) {
        $uid = (int)$_POST['toggle_ban'];
        db()->prepare(\"UPDATE users SET banned = 1 - COALESCE(banned,0) WHERE id=? AND role <> 'admin'\")->execute([$uid]);
        $msg = 'تم تحديث حالة المستخدم ✅';
    }
    // إرسال رسالة لشام كاش (تأكيد يدوي للتحويلات القديمة أو الطلبات)
    if (isset($_POST['confirm_order_id'])) {
         $oid = (int)$_POST['confirm_order_id'];
         db()->prepare(\"UPDATE orders SET status='completed' WHERE id=?\")->execute([$oid]);
         $msg = 'تمت الموافقة على الطلب يدوياً ✅';
    }
    if (isset($_POST['sync_products'])) {
        cache_set('fc_products', [], 0);
        cache_set('fc_all_products', [], 0);
        store_products();
        $msg = 'تمت مزامنة وتحديث الأسعار من API بنجاح 🔄';
    }
}

// جلب الإحصائيات والأرقام
$stats = db()->query(\"SELECT 
    (SELECT COUNT(*) FROM users) as users,
    (SELECT COUNT(*) FROM orders) as orders,
    (SELECT COUNT(*) FROM orders WHERE status='pending') as pending_orders,
    (SELECT SUM(balance) FROM users) as total_balances
\")->fetch(PDO::FETCH_ASSOC);

// جلب سعر الصرف ودولار شام كاش الحالي من الإعدادات
$current_usd_rate = (float)setting('usd_rate', 15000);
$current_usd_sham = (float)setting('usd_rate_sham', 15000); // القيمة الافتراضية إذا لم تحدد
$current_profit = (float)setting('profit_percent', 5);

require_once __DIR__ . '/header.php';
?>
<main class=\"container admin-page\">
  <div class=\"admin-header\">
    <h2>لوحة التحكم الإدارية 🔐</h2>
    <?php if ($msg): ?><div class=\"alert ok\"><?= e($msg) ?></div><?php endif; ?>
  </div>

  <div class=\"tabs-row\">
    <a href=\"?tab=stats\" class=\"tab-btn <?= $tab==='stats'?'active':'' ?>\">📊 الإحصائيات</a>
    <a href=\"?tab=settings\" class=\"tab-btn <?= $tab==='settings'?'active':'' ?>\">⚙️ إعدادات الأسعار</a>
    <a href=\"?tab=orders\" class=\"tab-btn <?= $tab==='orders'?'active':'' ?>\">📦 الطلبات المعلقة</a>
    <a href=\"?tab=users\" class=\"tab-btn <?= $tab==='users'?'active':'' ?>\">👥 إدارة الأعضاء</a>
  </div>

  <?php if ($tab === 'stats'): ?>
  <div class=\"grid admin-stats\">
    <div class=\"card stat-card\">
      <div class=\"num\"><?= number_format($stats['users']) ?></div>
      <div class=\"lbl\">إجمالي المستخدمين</div>
    </div>
    <div class=\"card stat-card warning\">
      <div class=\"num\"><?= number_format($stats['pending_orders']) ?></div>
      <div class=\"lbl\">طلبات تنتظر التنفيذ</div>
    </div>
    <div class=\"card stat-card\">
      <div class=\"num\"><?= number_format($stats['orders']) ?></div>
      <div class=\"lbl\">إجمالي الطلبات المستلمة</div>
    </div>
    <div class=\"card stat-card info\">
      <div class=\"num\"><?= number_format($stats['total_balances']) ?> ل.س</div>
      <div class=\"lbl\">إجمالي ديون/أرصدة الزبائن</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'settings'): ?>
  <div class=\"grid admin-settings-grid\">
    <div class=\"card\">
      <h3>💵 سعر صرف عروض الألعاب</h3>
      <p class=\"muted small\">يُستخدم هذا السعر لتسعير بطاقات شدات ببجي، فري فاير، والألعاب العادية القادمة من FastCard.</p>
      <form method=\"post\" style=\"margin-top:15px\">
        <div class=\"form-group\">
          <input type=\"number\" name=\"usd_rate\" step=\"10\" value=\"<?= $current_usd_rate ?>\" required>
          <span class=\"input-hint\">ل.س لكل 1 دولار أمريكي</span>
        </div>
        <button class=\"btn\" style=\"margin-top:10px\">حفظ سعر صرف الألعاب 💾</button>
      </form>
    </div>

    <div class=\"card\">
      <h3>💳 سعر صرف دولار شام كاش والرصيد</h3>
      <p class=\"muted small\">خاص بتسعير باقات شحن شام كاش دولار، الرصيد، والخدمات السوشيال (متابعين، لايكات وغيرها).</p>
      <form method=\"post\" style=\"margin-top:15px\">
        <div class=\"form-group\">
          <input type=\"number\" name=\"usd_rate_sham\" step=\"10\" value=\"<?= $current_usd_sham ?>\" required>
          <span class=\"input-hint\">ل.س لكل 1 دولار شام كاش / رصيد</span>
        </div>
        <button class=\"btn btn-accent\" style=\"margin-top:10px\">حفظ سعر صرف شام كاش 💾</button>
      </form>
    </div>

    <div class=\"card\">
      <h3>📈 هامش الربح العام المتجر</h3>
      <p class=\"muted small\">النسبة المئوية التي تضاف فوق السعر الأساسي القادم من المورد كربح صافٍ لك.</p>
      <form method=\"post\" style=\"margin-top:15px\">
        <div class=\"form-group\">
          <input type=\"number\" name=\"profit_percent\" step=\"0.1\" value=\"<?= $current_profit ?>\" required>
          <span class=\"input-hint\">% نسبة الربح الحالية</span>
        </div>
        <button class=\"btn\" style=\"margin-top:10px\">حفظ نسبة الربح 💾</button>
      </form>
    </div>

    <div class=\"card\">
      <h3>🔄 مزامنة وتحديث فوري</h3>
      <p class=\"muted small\">تحديث المنتجات وتصفير الكاش اليدوي لجميع الأسعار بالموقع فوراً بناءً على الحسبة الجديدة:</p>
      <form method=\"post\" style=\"margin-top:15px\">\n        <button class=\"btn btn-sm\" name=\"sync_products\" value=\"1\">تحديث ومزامنة الأسعار الآن 🔄</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'orders'): ?>
  <div class=\"card\">
    <h3>📥 الطلبات المعلقة (Pending)</h3>
    <?php
    $orders = db()->query(\"SELECT o.*, u.username FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='pending' ORDER BY o.id DESC\")->fetchAll(PDO::FETCH_ASSOC);
    if (!$orders): ?><p class=\"empty\">ممتاز! لا يوجد طلبات معلقة حالياً.</p><?php else: ?>
    <div class=\"table-responsive\" style=\"margin-top:15px\">
      <table class=\"admin-table\">
        <thead>
          <tr>
            <th>رقم الطلب</th>
            <th>الزبون</th>
            <th>المنتج</th>
            <th>الكمية</th>
            <th>الـ ID / البيانات</th>
            <th>السعر المدفوع</th>
            <th>إجراء يدوياً</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td>#<?= $o['id'] ?></td>
            <td><b><?= e($o['username']) ?></b></td>
            <td><?= e($o['product_name']) ?></td>
            <td><?= $o['qty'] ?></td>
            <td><code class=\"copyable\" onclick=\"copyText('<?= e($o['player_id']) ?>')\"><?= e($o['player_id']) ?> 📋</code></td>
            <td><?= number_format($o['total_price']) ?> ل.س</td>
            <td>
              <form method=\"post\" style=\"display:inline\">
                <button class=\"btn btn-sm ok-btn\" name=\"confirm_order_id\" value=\"<?= $o['id'] ?>\">موافقة وإكمال الطلب ✅</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'users'): ?>
  <div class=\"card\">
    <h3>👥 إدارة أرصدة وحظر الزبائن</h3>
    <div class=\"grid admin-user-actions\" style=\"margin-top:15px; margin-bottom:20px;\">
      <form method=\"post\" class=\"inline-form-box\">
        <h4>💳 إضافة رصيد لحساب</h4>
        <div style=\"display:flex; gap:10px; margin-top:10px;\">
          <select name=\"add_balance_user\" required style=\"flex:2\">
            <option value=\"\">اختر الزبون...</option>
            <?php 
            $usrs = db()->query(\"SELECT id, username, balance FROM users ORDER BY username ASC\")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($usrs as $u) {
                echo \"<option value='{$u['id']}'>\".e($u['username']).\" ( الحالي: \".number_format($u['balance']).\" ل.س)</option>\";
            }
            ?>
          </select>
          <input type=\"number\" name=\"add_balance_amount\" placeholder=\"المبلغ بالـ ل.س\" required style=\"flex:1\">
          <button class=\"btn\">إضافة الرصيد</button>
        </div>
      </form>
    </div>

    <div class=\"table-responsive\">
      <table class=\"admin-table\">
        <thead>
          <tr>
            <th>ID</th>
            <th>اسم المستخدم</th>
            <th>البريد الإلكتروني</th>
            <th>الرصيد الحالي</th>
            <th>تاريخ التسجيل</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usrs as $u): 
            $fullU = db()->query(\"SELECT * FROM users WHERE id={$u['id']}\")->fetch(PDO::FETCH_ASSOC);
          ?>
          <tr>
            <td>#<?= $fullU['id'] ?></td>
            <td><b><?= e($fullU['username']) ?></b> <?= $fullU['role']==='admin'?'<span class=\"badge admin\">Ad</span>':'' ?></td>
            <td><?= e($fullU['email']) ?></td>
            <td><b class=\"bal-txt\"><?= number_format($fullU['balance']) ?> ل.س</b></td>
            <td><small><?= $fullU['created_at'] ?></small></td>
            <td>
              <?php if ($fullU['role'] !== 'admin'): ?>
                <form method=\"post\" style=\"display:inline\">
                  <button class=\"btn btn-sm <?= $fullU['banned']?'btn-accent':'no-btn' ?>\" name=\"toggle_ban\" value=\"<?= $fullU['id'] ?>\">
                    <?= $fullU['banned'] ? '🟢 إلغاء الحظر' : '🔴 حظر الحساب' ?>
                  </button>
                </form>
              <?php else: ?>
                <span class=\"muted\">محمي</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
