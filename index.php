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
    $label = $p['params'][0] ?? '';
    $qm = max(1, (int)$p['qty_min']);
    $unitSmall = ($p['type'] ?? '') === 'amount' || $p['price'] < 100;
    $showFrom = ($qm > 1) || $unitSmall;
    $displayPrice = $showFrom ? round($p['price'] * $qm) : $p['price'];
    $prefix = ($qm > 1) ? 'من ' : ''; ?>
    <div class="card product-card <?= $p['available'] ? '' : 'oos' ?>"
         data-id="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>"
         data-price="<?= e($p['price']) ?>" data-desc="<?= e($p['desc']) ?>"
         data-param="<?= e($label) ?>" data-qmin="<?= e($p['qty_min']) ?>" data-qmax="<?= e($p['qty_max']) ?>"
         data-type="<?= e($p['type'] ?? '') ?>"
         data-cat="<?= e($p['category'] ?? '') ?>"
         data-verify="<?= (needs_verify($p, $ctx) && !empty($p['params'])) ? '1' : '0' ?>"
         onclick="openBuy(this)">
      <button class="fav-btn <?= $isFav ? 'on' : '' ?>" onclick="toggleFav(event, '<?= e($p['id']) ?>', this)">❤</button>
      <?php $pimg = item_img_url($p['id']) ?: ($GLOBALS['cur_cat_img'] ?? null);
            if ($pimg): ?><img src="<?= e($pimg) ?>" alt="" loading="lazy"><?php else: ?><div class="ph"></div><?php endif; ?>
      <div class="p-name"><?= e($p['name']) ?></div>
      <?php $disc = promo_discount_pct(); if ($disc > 0 && $p['available']):
        $newPrice = $displayPrice * (1 - $disc/100); ?>
        <div class="p-price-wrap">
          <span class="p-price-old"><?= fmt_price($displayPrice) ?></span>
          <span class="p-price discounted"><?= $prefix ?><?= fmt_price($newPrice) ?></span>
        </div>
        <span class="p-disc-badge">-<?= rtrim(rtrim(number_format($disc,1),'0'),'.') ?>%</span>
      <?php else: ?>
        <div class="p-price"><?= $prefix ?><?= fmt_price($displayPrice) ?></div>
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

/* ===== صفحة البحث ===== */
if ($page === 'search') {
    $q = trim($_GET['q'] ?? '');
    $minP = (isset($_GET['min']) && $_GET['min'] !== '') ? (float)$_GET['min'] : null;
    $maxP = (isset($_GET['max']) && $_GET['max'] !== '') ? (float)$_GET['max'] : null;
    $sortBy = $_GET['sort'] ?? '';
    $favs = user_favs($U);
    $results = [];
    if ($q !== '' && mb_strlen($q) >= 2) {
        $ql = mb_strtolower($q);
        foreach (store_products() as $p) {
            if (mb_strpos(mb_strtolower($p['name']), $ql) !== false
                || mb_strpos(mb_strtolower($p['category']), $ql) !== false) {
                if ($minP !== null && $p['price'] < $minP) continue;
                if ($maxP !== null && $p['price'] > $maxP) continue;
                $results[] = $p;
            }
        }
        if ($sortBy === 'price_asc') usort($results, fn($a,$b) => $a['price'] <=> $b['price']);
        elseif ($sortBy === 'price_desc') usort($results, fn($a,$b) => $b['price'] <=> $a['price']);
        elseif ($sortBy === 'name') usort($results, fn($a,$b) => strcmp($a['name'], $b['name']));
    }
    $pageTitle = 'بحث';
    include __DIR__ . '/header.php'; ?>
    <h1 class="section-title">🔍 بحث عن منتج</h1>
    <form method="get" class="search-form-adv">
      <input type="hidden" name="page" value="search">
      <div class="sf-row">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="اكتب اسم المنتج أو القسم..." autofocus>
        <button class="btn" type="submit">بحث</button>
      </div>
      <div class="sf-filters">
        <div class="sf-price">
          <span class="sf-lbl">السعر (ل.س):</span>
          <input type="number" name="min" value="<?= e($_GET['min'] ?? '') ?>" placeholder="من" inputmode="numeric">
          <span>—</span>
          <input type="number" name="max" value="<?= e($_GET['max'] ?? '') ?>" placeholder="إلى" inputmode="numeric">
        </div>
        <select name="sort" class="sf-sort">
          <option value="">الترتيب: الافتراضي</option>
          <option value="price_asc" <?= $sortBy==='price_asc'?'selected':'' ?>>الأرخص أولاً ↑</option>
          <option value="price_desc" <?= $sortBy==='price_desc'?'selected':'' ?>>الأغلى أولاً ↓</option>
          <option value="name" <?= $sortBy==='name'?'selected':'' ?>>أبجدياً</option>
        </select>
      </div>
    </form>
    <?php if ($q !== '' && mb_strlen($q) < 2): ?>
      <p class="empty">اكتب حرفين على الأقل للبحث.</p>
    <?php elseif ($q !== '' && !$results): ?>
      <p class="empty">ما في نتائج لـ "<?= e($q) ?>"<?= ($minP!==null||$maxP!==null) ? ' ضمن نطاق السعر المحدّد' : '' ?> — جرّب كلمة ثانية أو وسّع نطاق السعر.</p>
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
