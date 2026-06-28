<?php
// app/index.php

// 1. استدعاء ملفات النظام والدوال الأساسية أولاً لضمان تعريف دالة db و current_user
$base_dir = dirname(__DIR__);
if (file_exists($base_dir . '/functions.php')) {
    require_once $base_dir . '/functions.php';
} elseif (file_exists($base_dir . '/db.php')) {
    require_once $base_dir . '/db.php';
} elseif (file_exists($base_dir . '/config.php')) {
    require_once $base_dir . '/config.php';
}

// 2. تضمين ملف الـ API الخاص بـ FastCard
if (file_exists(__DIR__ . '/fastcard_api.php')) {
    require_once __DIR__ . '/fastcard_api.php';
}

// 3. التحقق من المستخدم الحالي (الآن ستعمل الدالة بنجاح وبدون أي أخطاء)
$user = function_exists('current_user') ? current_user() : null;

// 4. جلب الصفحة المطلوبة (الـ Router الخاص بموقعك)
$page = $_GET['page'] ?? 'home';

// عنوان الصفحة الافتراضي
$pageTitle = 'الرئيسية';

// تضمين الهيدر الخاص بالموقع
if (file_exists($base_dir . '/header.php')) {
    include_once $base_dir . '/header.php';
}

// 5. عرض محتوى الصفحة بناءً على الـ المتغير الممرر
switch ($page) {
    case 'products':
        // كود عرض المنتجات
        if (function_exists('get_fastcard_products')) {
            $products = get_fastcard_products();
        }
        ?>
        <div class="container">
            <h1 class="section-title">المنتجات المتوفرة 🛒</h1>
            </div>
        <?php
        break;

    case 'profile':
        // صفحة حساب المستخدم
        ?>
        <div class="container">
            <h1 class="section-title">حسابي الشخصي 👤</h1>
            <?php if ($user): ?>
                <p>أهلاً بك، <b><?= htmlspecialchars($user['name'] ?? '') ?></b></p>
                <p>رصيدك الحالي: <b><?= number_format($user['balance'] ?? 0) ?> ل.س</b></p>
            <?php else: ?>
                <p>الرجاء تسجيل الدخول أولاً.</p>
            <?php endif; ?>
        </div>
        <?php
        break;

    case 'home':
    default:
        // الصفحة الرئيسية للموقع
        ?>
        <div class="container">
            <h1 class="section-title">مرحباً بك في موقعنا 🚀</h1>
            <p class="muted">اختر القسم أو الخدمة التي ترغب بشحنها الآن.</p>
            
            <div style="margin-top: 20px;">
                <a href="?page=products" class="btn" style="text-decoration: none; display: inline-block;">عرض كل المنتجات 🛒</a>
            </div>
        </div>
        <?php
        break;
}

// تضمين الفوتر الخاص بالموقع
if (file_exists($base_dir . '/footer.php')) {
    include_once $base_dir . '/footer.php';
}
