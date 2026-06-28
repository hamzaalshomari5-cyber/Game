<?php
// index.php

// 1. جلب ملفات النظام الأساسية أولاً لتعريف الدوّال وتفادي خطأ current_user()
if (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} elseif (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// 2. تضمين ملف الـ API المحدث
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
// (يمكنك ترك بقية الدوال هنا إذا كانت موجودة في ملفك الأصلي)

$root = fc_content(0);
$pageTitle = 'الرئيسية';
include __DIR__ . '/header.php'; ?>

<div class="search-box-container">
<form method="get" action="/index.php" class="home-search-form">
  <input type="hidden" name="page" value="products">
  <input type="text" name="q" class="hs-input" id="homeSearchInput" placeholder="ابحث عن لعبة، شحن، بطاقة..." autocomplete="off">
  <button type="submit" class="hs-btn">بحث</button>
</form>
<div class="search-suggest" id="searchSuggest"></div>
</div>

<h2 class="section-title">الأقسام</h2>
<?php if (!$root['categories'] && !$root['products']): ?>
  <p class="empty">لم يتم تحميل المنتجات بعد — تأكد من توكن FastCard في الإعدادات.</p>
<?php endif; ?>
<div class="grid cats-grid">
  <?php foreach ($root['categories'] as $c): $cimg = item_img_url($c['id']); ?>
    <a class="card cat-card" href="/index.php?page=products&cat=<?= urlencode($c['id']) ?>&name=<?= urlencode($c['name']) ?><?= $cimg ? '&img=' . urlencode($c['id']) : '' ?>">
      <?php if ($cimg): ?><img class=\"cat-img\" src="<?= e($cimg) ?>" alt="" loading="lazy"><?php else: ?><div class="cat-icon"></div><?php endif; ?>
      <div class="cat-name"><?= e($c['name']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($root['products']): $favs = user_favs($U); ?>
  <h2 class="section-title">منتجات</h2>
  <div class="grid products-grid"><?php foreach ($root['products'] as $p) product_card($p, $favs); ?></div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
