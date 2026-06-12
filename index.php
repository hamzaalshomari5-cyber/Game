<?php
require_once __DIR__ . '/fastcard_api.php';
$page = $_GET['page'] ?? 'home';
$U = current_user();

/* ===== المفضلة (toggle عبر AJAX) ===== */
if (($_GET['action'] ?? '') === 'fav') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$U) { echo json_encode(['ok' => false, 'login' => true]); exit; }
    $pid = (string)($_GET['pid'] ?? '');
    $st = db()->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=? AND product_id=?");
    $st->execute([$U['id'], $pid]);
    if ($st->fetchColumn()) {
        db()->prepare("DELETE FROM favorites WHERE user_id=? AND product_id=?")->execute([$U['id'], $pid]);
        echo json_encode(['ok' => true, 'fav' => false]);
    } else {
        db()->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?,?)")->execute([$U['id'], $pid]);
        echo json_encode(['ok' => true, 'fav' => true]);
    }
    exit;
}

function user_favs($U) {
    if (!$U) return [];
    $st = db()->prepare("SELECT product_id FROM favorites WHERE user_id=?");
    $st->execute([$U['id']]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/* ===== كرت منتج (مثل FastCard: صورة + اسم + سعر + غير متوفر + قلب) ===== */
function product_card($p, $favs) {
    $isFav = in_array((string)$p['id'], $favs);
    $label = $p['params'][0] ?? ''; ?>
    <div class="card product-card <?= $p['available'] ? '' : 'oos' ?>"
         data-id="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>"
         data-price="<?= e($p['price']) ?>" data-desc="<?= e($p['desc']) ?>"
         data-param="<?= e($label) ?>" data-qmin="<?= e($p['qty_min']) ?>" data-qmax="<?= e($p['qty_max']) ?>"
         onclick="openBuy(this)">
      <button class="fav-btn <?= $isFav ? 'on' : '' ?>" onclick="toggleFav(event, '<?= e($p['id']) ?>', this)">❤</button>
      <?php if ($p['image']): ?><img src="<?= e($p['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="ph">🎮</div><?php endif; ?>
      <div class="p-name"><?= e($p['name']) ?></div>
      <div class="p-price"><?= number_format($p['price']) ?> ل.س</div>
      <?php if (!$p['available']): ?><div class="oos-badge">غير متوفر حالياً ❌</div><?php endif; ?>
    </div>
<?php }

/* ===== صفحات ثابتة ===== */
if ($page === 'about' || $page === 'terms') {
    $pageTitle = $page === 'about' ? 'من نحن' : 'سياسة الاسترجاع';
    include __DIR__ . '/header.php'; ?>
    <section class="static-page">
      <?php if ($page === 'about'): ?>
        <h1>من نحن</h1>
        <p><?= e(STORE_NAME) ?> — وجهتك لشحن الألعاب والبطاقات الرقمية بسرعة وأمان.</p>
        <ul class="checks">
          <li>✔ تسليم فوري خلال دقائق</li>
          <li>✔ دعم فني على مدار الساعة</li>
          <li>✔ منتجات أصلية 100%</li>
          <li>✔ أسعار منافسة</li>
        </ul>
      <?php else: ?>
        <h1>سياسة الاسترجاع</h1>
        <p>في حال حدوث مشكلة في تنفيذ الطلب، يُعاد المبلغ كاملاً إلى محفظتك تلقائياً. للمساعدة تواصل معنا عبر واتساب.</p>
      <?php endif; ?>
    </section>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== صفحة المفضلة ===== */
if ($page === 'favs') {
    $favs = user_favs($U);
    $products = array_values(array_filter(store_products(), fn($p) => in_array((string)$p['id'], $favs)));
    $pageTitle = 'المفضلة';
    include __DIR__ . '/header.php'; ?>
    <h1 class="section-title">المفضلة ❤</h1>
    <?php if (!$U): ?><p class="empty"><a href="/auth.php">سجّل دخول</a> لاستخدام المفضلة.</p>
    <?php elseif (!$products): ?><p class="empty">ما في منتجات بالمفضلة بعد — اضغط ❤ على أي منتج لإضافته.</p>
    <?php else: ?>
      <div class="grid products-grid"><?php foreach ($products as $p) product_card($p, $favs); ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/buy_modal.php'; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== صفحة قسم: content/{id} → أقسام فرعية + منتجات (نفس FastCard) ===== */
if ($page === 'products') {
    $cat = $_GET['cat'] ?? '0';
    $content = fc_content($cat);
    $subs = $content['categories'];
    $products = $content['products'];
    $favs = user_favs($U);
    $catName = $_GET['name'] ?? 'المنتجات';
    $pageTitle = $catName;
    include __DIR__ . '/header.php'; ?>

    <h1 class="section-title"><?= e($catName) ?></h1>

    <?php if ($subs): ?>
      <div class="grid cats-grid">
        <?php foreach ($subs as $c): ?>
          <a class="card cat-card" href="/index.php?page=products&cat=<?= urlencode($c['id']) ?>&name=<?= urlencode($c['name']) ?>">
            <?php if ($c['image']): ?><img class="cat-img" src="<?= e($c['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="cat-icon">🎮</div><?php endif; ?>
            <div class="cat-name"><?= e($c['name']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($products): ?>
      <?php if ($subs): ?><h2 class="section-title">المنتجات</h2><?php endif; ?>
      <div class="grid products-grid"><?php foreach ($products as $p) product_card($p, $favs); ?></div>
    <?php elseif (!$subs): ?>
      <p class="empty">لا توجد منتجات في هذا القسم حالياً.</p>
    <?php endif; ?>

    <?php include __DIR__ . '/buy_modal.php'; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== الرئيسية: content/0 → الأقسام الرئيسية ===== */
$root = fc_content(0);
$pageTitle = 'الرئيسية';
include __DIR__ . '/header.php'; ?>

<section class="hero">
  <div class="hero-inner">
    <h1><?= e(STORE_NAME) ?> <span class="bolt">⚡</span></h1>
    <p><?= e(STORE_TAGLINE) ?> — أفضل الأسعار وأسرع خدمة</p>
    <div class="hero-ticker"><span>🔥 أسعار خاصة وخصومات للتجار وأصحاب المحلات — تواصل معنا عبر واتساب</span></div>
  </div>
</section>

<h2 class="section-title">الأقسام</h2>
<?php if (!$root['categories'] && !$root['products']): ?>
  <p class="empty">لم يتم تحميل المنتجات بعد — تأكد من توكن FastCard في الإعدادات.</p>
<?php endif; ?>
<div class="grid cats-grid">
  <?php foreach ($root['categories'] as $c): ?>
    <a class="card cat-card" href="/index.php?page=products&cat=<?= urlencode($c['id']) ?>&name=<?= urlencode($c['name']) ?>">
      <?php if ($c['image']): ?><img class="cat-img" src="<?= e($c['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="cat-icon">🎮</div><?php endif; ?>
      <div class="cat-name"><?= e($c['name']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($root['products']): $favs = user_favs($U); ?>
  <h2 class="section-title">منتجات</h2>
  <div class="grid products-grid"><?php foreach ($root['products'] as $p) product_card($p, $favs); ?></div>
  <?php include __DIR__ . '/buy_modal.php'; ?>
  <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
<?php endif; ?>

<section class="features">
  <div class="feat">⚡ تسليم فوري</div>
  <div class="feat">🛡 منتجات أصلية</div>
  <div class="feat">💬 دعم 24/7</div>
  <div class="feat">💰 أسعار منافسة</div>
</section>

<?php include __DIR__ . '/footer.php';
