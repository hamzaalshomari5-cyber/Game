<?php
require_once __DIR__ . '/db.php';
$U = current_user();
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(STORE_NAME) ?> | <?= e($pageTitle ?? 'الرئيسية') ?></title>
<meta name="description" content="<?= e(STORE_NAME . ' - ' . STORE_TAGLINE) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="/style.css?v=15">
<!-- الخط يحمّل بدون حجب الصفحة (أسرع ظهور) -->
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet"></noscript>
</head>
<body>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
  <div class="sb-head">
    <div class="logo-txt"><img src="/logo.svg?v=1" class="logo-img" alt=""><span class="logo-name"><?= e(STORE_NAME) ?></span></div>
  </div>
  <?php if ($U): ?>
    <div class="sb-user">
      <div class="sb-name">👤 <?= e($U['name']) ?></div>
      <div class="sb-balance">المحفظة: <b class="bal-amount" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> ل.س</b></div>
      <a class="btn btn-sm" href="/wallet.php">شحن المحفظة</a>
    </div>
  <?php else: ?>
    <div class="sb-user">
      <div class="sb-name">تسجيل الدخول</div>
      <p class="muted">سجّل دخول أو أنشئ حساب جديد للمتابعة</p>
      <a class="btn btn-sm" href="/auth.php">تسجيل الدخول</a>
    </div>
  <?php endif; ?>
  <nav class="sb-nav">
    <a href="/index.php">🏠 الرئيسية</a>
    <a href="/index.php?page=search">🔍 بحث عن منتج</a>
    <a href="/assistant.php">🤖 المساعد الذكي</a>
    <?php if ($U): ?>
      <a href="/account.php">👤 حسابي</a>
      <a href="/orders.php">🧾 طلباتي</a>
      <a href="/notifications.php">🔔 الإشعارات</a>
      <a href="/index.php?page=favs">❤ المفضلة</a>
      <a href="/index.php?page=cart">🛒 السلة</a>
      <a href="/index.php?page=wheel">🎡 عجلة الحظ</a>
      <a href="/wallet.php">💳 المحفظة</a>
      <a href="/coupon.php">🎁 كود الخصم</a>
      <?php if ($U['role'] === 'admin'): ?><a href="/admin.php">🛠 لوحة الأدمن</a><?php endif; ?>
      <a href="/auth.php?logout=1">🚪 تسجيل الخروج</a>
    <?php endif; ?>
    <a href="/index.php?page=about">ℹ️ من نحن</a>
    <a href="/contact.php">📞 تواصل معنا</a>
    <a href="/faq.php">❓ الأسئلة الشائعة</a>
    <a href="/index.php?page=terms">📄 سياسة الاسترجاع</a>
  </nav>
  <button class="theme-toggle" onclick="toggleTheme()">🌙 / ☀️ تبديل الوضع</button>
  <button class="theme-toggle" onclick="toggleCurrency()">💱 العملة: <?= display_currency() === 'usd' ? 'دولار $' : 'ليرة ل.س' ?></button>
  <div class="sb-social">
    <?php if (WHATSAPP_1): ?><a href="<?= e(WHATSAPP_1) ?>" target="_blank">واتساب</a><?php endif; ?>
    <?php if (INSTAGRAM): ?><a href="<?= e(INSTAGRAM) ?>" target="_blank">انستغرام</a><?php endif; ?>
  </div>
</aside>

<header class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>
  <?php
    $_uri = $_SERVER['REQUEST_URI'] ?? '';
    $_isHome = ($_uri === '/' || $_uri === '/index.php' || strpos($_uri, '/index.php?page=home') === 0
                || (strpos($_uri, '/index.php') === 0 && strpos($_uri, 'page=') === false && strpos($_uri, 'cat=') === false));
  ?>
  <?php if (!$_isHome): ?><button class="back-btn" onclick="goBack()" title="رجوع">‹</button><?php endif; ?>
  <a class="logo-txt" href="/index.php"><img src="/logo.svg?v=1" class="logo-img" alt=""><span class="logo-name"><?= e(STORE_NAME) ?></span></a>
  <div class="top-actions">
    <a class="icon-btn" href="/index.php?page=search" title="بحث">🔍</a>
    <?php if ($U): ?>
      <a class="icon-btn cart-icon" href="/index.php?page=cart" title="السلة">🛒<span class="cart-badge" id="cartBadge" style="display:none">0</span></a>
      <a class="icon-btn notif-bell" href="/notifications.php" title="الإشعارات">🔔<span class="notif-badge" id="notifBadge" style="display:none">0</span></a>
      <a class="balance-pill" href="/wallet.php">💳 <span class="bal-amount" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> ل.س</span></a>
    <?php else: ?>
      <a class="btn btn-sm" href="/auth.php">دخول</a>
    <?php endif; ?>
  </div>
</header>
<script>const USD_RATE = <?= usd_rate() ?>; const CUR = '<?= display_currency() ?>';</script>
<?php
// خصومات الأسعار الدائمة المفعّلة للمستخدم (لإظهارها تلقائياً على المنتجات)
$myDiscounts = [];
if ($U) {
    $st = db()->prepare("SELECT ud.player_id AS player_id, c.type AS type, c.amount AS amount
        FROM user_discounts ud JOIN coupons c ON c.id = ud.coupon_id
        WHERE ud.user_id=? AND ud.status='active' AND c.active=1 ORDER BY ud.id DESC");
    $st->execute([$U['id']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $myDiscounts[] = ['player_id' => (string)$r['player_id'], 'type' => $r['type'], 'amount' => (float)$r['amount']];
    }
}
?>
<script>const MY_DISCOUNTS = <?= json_encode($myDiscounts, JSON_UNESCAPED_UNICODE) ?>;</script>
<main class="container">
