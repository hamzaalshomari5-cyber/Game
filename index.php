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
function fc_img($file, $cls) {
    if (!$file) return false;
    if (preg_match('#^https?://#', $file)) { $src = $file; $alts = ''; }
    else {
        $base = 'https://fastcard1.store/uploads/';
        $first = (strpos($file, 'cat_') === 0) ? 'categories/' : 'products/';
        $src = $base . $first . $file;
        $alts = e($base . $file);
    }
    echo '<img class="' . $cls . '" src="' . e($src) . '" alt="" loading="lazy"'
       . ($alts ? ' onerror="if(!this.dataset.t){this.dataset.t=1;this.src=\'' . $alts . '\'}else{this.style.display=\'none\';this.insertAdjacentHTML(\'afterend\',\'<div class=ph>🎮</div>\')}"'
                : ' onerror="this.style.display=\'none\';this.insertAdjacentHTML(\'afterend\',\'<div class=ph>🎮</div>\')"')
       . '>';
    return true;
}

function needs_verify($p, $ctx = '') {
    $t = mb_strtolower($p['name'] . ' ' . $p['category'] . ' ' . $ctx);
    foreach (['ببجي', 'pubg', 'شدة', 'شدات', 'فري فاير', 'free fire', 'freefire', 'uc '] as $k)
        if (mb_strpos($t, $k) !== false) return true;
    if (preg_match('/\d+\s*uc\b|\buc\s*\d+/i', $t)) return true;
    return false;
}

function product_card($p, $favs, $ctx = '') {
    $isFav = in_array((string)$p['id'], $favs);
    $label = $p['params'][0] ?? ''; ?>
    <div class="card product-card <?= $p['available'] ? '' : 'oos' ?>"
         data-id="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>"
         data-price="<?= e($p['price']) ?>" data-desc="<?= e($p['desc']) ?>"
         data-param="<?= e($label) ?>" data-qmin="<?= e($p['qty_min']) ?>" data-qmax="<?= e($p['qty_max']) ?>"
         data-verify="<?= (needs_verify($p, $ctx) && !empty($p['params'])) ? '1' : '0' ?>"
         onclick="openBuy(this)">
      <button class="fav-btn <?= $isFav ? 'on' : '' ?>" onclick="toggleFav(event, '<?= e($p['id']) ?>', this)">❤</button>
      <?php if (!fc_img($p['image'], '')): ?><div class="ph">🎮</div><?php endif; ?>
      <div class="p-name"><?= e($p['name']) ?></div>
      <?php $disc = promo_discount_pct(); if ($disc > 0 && $p['available']):
        $newPrice = $p['price'] * (1 - $disc/100); ?>
        <div class="p-price-wrap">
          <span class="p-price-old"><?= fmt_price($p['price']) ?></span>
          <span class="p-price discounted"><?= fmt_price($newPrice) ?></span>
        </div>
        <span class="p-disc-badge">-<?= rtrim(rtrim(number_format($disc,1),'0'),'.') ?>%</span>
      <?php else: ?>
        <div class="p-price"><?= fmt_price($p['price']) ?></div>
      <?php endif; ?>
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
/* ===== صفحة البحث ===== */
if ($page === 'search') {
    $q = trim($_GET['q'] ?? '');
    $favs = user_favs($U);
    $results = [];
    if ($q !== '' && mb_strlen($q) >= 2) {
        $ql = mb_strtolower($q);
        foreach (store_products() as $p) {
            if (mb_strpos(mb_strtolower($p['name']), $ql) !== false
                || mb_strpos(mb_strtolower($p['category']), $ql) !== false) {
                $results[] = $p;
            }
        }
    }
    $pageTitle = 'بحث';
    include __DIR__ . '/header.php'; ?>
    <h1 class="section-title">🔍 بحث عن منتج</h1>
    <form method="get" class="search-form">
      <input type="hidden" name="page" value="search">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="اكتب اسم المنتج أو القسم..." autofocus>
      <button class="btn" type="submit">بحث</button>
    </form>
    <?php if ($q !== '' && mb_strlen($q) < 2): ?>
      <p class="empty">اكتب حرفين على الأقل للبحث.</p>
    <?php elseif ($q !== '' && !$results): ?>
      <p class="empty">ما في نتائج لـ "<?= e($q) ?>" — جرّب كلمة ثانية.</p>
    <?php elseif ($results): ?>
      <p class="muted" style="margin-bottom:14px"><?= count($results) ?> نتيجة لـ "<?= e($q) ?>"</p>
      <div class="grid products-grid"><?php foreach (array_slice($results, 0, 60) as $p) product_card($p, $favs); ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/buy_modal.php'; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

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
            <?php if (!fc_img($c['image'], 'cat-img')): ?><div class="cat-icon">🎮</div><?php endif; ?>
            <div class="cat-name"><?= e($c['name']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($products): ?>
      <?php
      // ترتيب المنتجات
      $sort = $_GET['sort'] ?? '';
      if ($sort === 'price_asc') usort($products, fn($a,$b) => $a['price'] <=> $b['price']);
      elseif ($sort === 'price_desc') usort($products, fn($a,$b) => $b['price'] <=> $a['price']);
      $catQ = urlencode($cat); $nameQ = urlencode($catName);
      ?>
      <?php if ($subs): ?><h2 class="section-title">المنتجات</h2><?php endif; ?>
      <div class="sort-bar">
        <span class="sort-label">ترتيب:</span>
        <a href="/index.php?page=products&cat=<?= $catQ ?>&name=<?= $nameQ ?>" class="sort-btn <?= $sort===''?'on':'' ?>">الافتراضي</a>
        <a href="/index.php?page=products&cat=<?= $catQ ?>&name=<?= $nameQ ?>&sort=price_asc" class="sort-btn <?= $sort==='price_asc'?'on':'' ?>">الأرخص ↑</a>
        <a href="/index.php?page=products&cat=<?= $catQ ?>&name=<?= $nameQ ?>&sort=price_desc" class="sort-btn <?= $sort==='price_desc'?'on':'' ?>">الأغلى ↓</a>
      </div>
      <div class="grid products-grid"><?php foreach ($products as $p) product_card($p, $favs, $catName); ?></div>
    <?php elseif (!$subs): ?>
      <p class="empty">لا توجد منتجات في هذا القسم حالياً.</p>
    <?php endif; ?>

    <?php include __DIR__ . '/buy_modal.php'; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== الرئيسية: content/0 → الأقسام الرئيسية ===== */
$root = fc_content(0);
$slides = db()->query("SELECT * FROM slides WHERE active=1 ORDER BY sort ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'الرئيسية';
include __DIR__ . '/header.php'; ?>

<?php if ($slides): ?>
<!-- النص فوق الصورة (شريط منفصل) -->
<div class="slider-topbar">
  <span class="cap-line">⚡ تسليم فوري ودعم 24/7</span>
  <span class="cap-dot">•</span>
  <span class="cap-line">💰 أفضل الأسعار وأسرع خدمة</span>
</div>
<div class="slider" id="slider">
  <div class="slides" id="slides">
    <?php foreach ($slides as $s): ?>
      <?php if ($s['link']): ?><a href="<?= e($s['link']) ?>" class="slide"><img src="<?= e($s['image']) ?>" alt="" loading="lazy"></a>
      <?php else: ?><div class="slide"><img src="<?= e($s['image']) ?>" alt="" loading="lazy"></div><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php if (count($slides) > 1): ?>
  <div class="slider-dots" id="sliderDots">
    <?php foreach ($slides as $i => $s): ?><button class="<?= $i === 0 ? 'on' : '' ?>" onclick="goSlide(<?= $i ?>)"></button><?php endforeach; ?>
  </div>
  <script>
  let slideIdx = 0; const slideCount = <?= count($slides) ?>;
  function goSlide(i) {
    slideIdx = (i + slideCount) % slideCount;
    document.getElementById('slides').style.transform = 'translateX(' + (slideIdx * 100) + '%)';
    document.querySelectorAll('#sliderDots button').forEach((d, j) => d.classList.toggle('on', j === slideIdx));
  }
  setInterval(() => goSlide(slideIdx + 1), 4500);
  </script>
  <?php endif; ?>
</div>
<?php else: ?>
<!-- لو ما في صور بالسلايدر، نعرض شريط بسيط فيه النص -->
<div class="mini-banner">
  <div class="slider-caption"><span class="cap-line">⚡ تسليم فوري ودعم 24/7</span><span class="cap-dot">•</span><span class="cap-line">💰 أفضل الأسعار وأسرع خدمة</span></div>
</div>
<?php endif; ?>

<!-- بانر العرض بوقت محدود -->
<?php $promo = promo_get(); if ($promo):
  // أيقونة وشكل حسب نوع العرض
  $promoIcons = ['discount' => '🔥', 'deposit' => '💰', 'banner' => '🎉'];
  $pIcon = $promoIcons[$promo['type']] ?? '🎉';
  $pVal = (float)($promo['value'] ?? 0);
?>
<div class="promo-banner promo-<?= e($promo['type']) ?>" <?= $promo['end'] > 0 ? 'data-end="'.$promo['end'].'"' : '' ?>>
  <div class="promo-shine"></div>
  <div class="promo-icon"><?= $pIcon ?></div>
  <div class="promo-text">
    <div class="promo-title"><?= e($promo['title']) ?></div>
    <?php if ($promo['type'] === 'discount' && $pVal > 0): ?>
      <div class="promo-sub">خصم <?= rtrim(rtrim(number_format($pVal,1),'0'),'.') ?>% على جميع المنتجات</div>
    <?php elseif ($promo['type'] === 'deposit' && $pVal > 0): ?>
      <div class="promo-sub">بونص <?= rtrim(rtrim(number_format($pVal,1),'0'),'.') ?>% على كل إيداع</div>
    <?php endif; ?>
    <?php if ($promo['end'] > 0): ?><div class="promo-timer" id="promoTimer">⏳ <span></span></div><?php endif; ?>
  </div>
  <?php if ($pVal > 0 && in_array($promo['type'], ['discount','deposit'])): ?>
    <div class="promo-badge">-<?= rtrim(rtrim(number_format($pVal,1),'0'),'.') ?>%</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- شريط البحث الرئيسي (بالعرض، فوق الأقسام) -->
<div class="home-search-wrap">
<form method="get" action="/index.php" class="home-search" autocomplete="off">
  <input type="hidden" name="page" value="search">
  <span class="hs-icon">🔍</span>
  <input type="text" name="q" id="homeSearchInput" placeholder="ابحث عن لعبة، شحن، بطاقة..." autocomplete="off">
  <button type="submit" class="hs-btn">بحث</button>
</form>
<div class="search-suggest" id="searchSuggest"></div>
</div>

<h2 class="section-title">الأقسام</h2>
<?php if (!$root['categories'] && !$root['products']): ?>
  <p class="empty">لم يتم تحميل المنتجات بعد — تأكد من توكن FastCard في الإعدادات.</p>
<?php endif; ?>
<div class="grid cats-grid">
  <?php foreach ($root['categories'] as $c): ?>
    <a class="card cat-card" href="/index.php?page=products&cat=<?= urlencode($c['id']) ?>&name=<?= urlencode($c['name']) ?>">
      <?php if (!fc_img($c['image'], 'cat-img')): ?><div class="cat-icon">🎮</div><?php endif; ?>
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
