<?php
require_once __DIR__ . '/fastcard_api.php';
$page = $_GET['page'] ?? 'home';
$U = current_user();

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

if ($page === 'products') {
    $cat = $_GET['cat'] ?? '';
    $products = array_values(array_filter(store_products(), fn($p) => $p['category'] === $cat));
    $pageTitle = $cat ?: 'المنتجات';
    include __DIR__ . '/header.php'; ?>

    <h1 class="section-title"><?= e($cat) ?></h1>
    <?php if (!$products): ?>
      <p class="empty">لا توجد منتجات في هذا القسم حالياً. تأكد من ضبط توكن FastCard في الإعدادات.</p>
    <?php endif; ?>
    <div class="grid products-grid">
      <?php foreach ($products as $p): ?>
        <div class="card product-card <?= $p['available'] ? '' : 'oos' ?>"
             data-id="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>"
             data-price="<?= e($p['price']) ?>" data-desc="<?= e($p['desc']) ?>"
             onclick="openBuy(this)">
          <?php if ($p['image']): ?><img src="<?= e($p['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="ph">🎮</div><?php endif; ?>
          <div class="p-name"><?= e($p['name']) ?></div>
          <div class="p-price"><?= number_format($p['price']) ?> ل.س</div>
          <?php if (!$p['available']): ?><div class="oos-badge">غير متوفر ❌</div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- مودال الشراء -->
    <div class="modal" id="buyModal">
      <div class="modal-box">
        <h3 id="mName">اسم المنتج</h3>
        <div class="m-price" id="mPrice"></div>
        <p class="muted" id="mDesc"></p>
        <label>الكمية</label>
        <div class="qty-row">
          <button type="button" onclick="qtyStep(-1)">−</button>
          <input type="number" id="mQty" value="1" min="1">
          <button type="button" onclick="qtyStep(1)">+</button>
        </div>
        <label>ID اللاعب / المعرف المطلوب</label>
        <input type="text" id="mPlayer" placeholder="مثال: 5123456789">
        <div class="m-total">الإجمالي: <b id="mTotal"></b> ل.س</div>
        <div class="m-msg" id="mMsg"></div>
        <div class="m-actions">
          <button class="btn ghost" onclick="closeBuy()">إلغاء</button>
          <button class="btn" id="mBuyBtn" onclick="submitBuy()">شراء الآن</button>
        </div>
      </div>
    </div>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

// ===== الرئيسية =====
$cats = store_categories();
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
<?php if (!$cats): ?>
  <p class="empty">لم يتم تحميل المنتجات بعد — تأكد من توكن FastCard في <code>config.php</code> أو متغير البيئة <code>FASTCARD_TOKEN</code>.</p>
<?php endif; ?>
<div class="grid cats-grid">
  <?php foreach ($cats as $name => $count): ?>
    <a class="card cat-card" href="/index.php?page=products&cat=<?= urlencode($name) ?>">
      <div class="cat-icon">🎮</div>
      <div class="cat-name"><?= e($name) ?></div>
      <div class="cat-count"><?= $count ?> منتج</div>
    </a>
  <?php endforeach; ?>
</div>

<section class="features">
  <div class="feat">⚡ تسليم فوري</div>
  <div class="feat">🛡 منتجات أصلية</div>
  <div class="feat">💬 دعم 24/7</div>
  <div class="feat">💰 أسعار منافسة</div>
</section>

<?php include __DIR__ . '/footer.php';
