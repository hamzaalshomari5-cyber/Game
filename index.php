<?php
// app/index.php

// 1. تحديد المجلد الرئيسي للموقع بدقة لجلب ملفات النظام الأساسية
$root_dir = dirname(__DIR__); 

// 2. استدعاء ملف الدوال الرئيسي (functions.php) الذي يحتوي على current_user و db وكل دوال الموقع
if (file_exists($root_dir . '/functions.php')) {
    require_once $root_dir . '/functions.php';
} elseif (file_exists($root_dir . '/db.php')) {
    require_once $root_dir . '/db.php';
}

// 3. تضمين ملف الـ API الخاص بـ FastCard من المجلد الحالي
if (file_exists(__DIR__ . '/fastcard_api.php')) {
    require_once __DIR__ . '/fastcard_api.php';
}

// 4. التحقق من المستخدم الحالي (الآن الدالة معرفة وسيعمل بشكل سليم 100%)
$user = function_exists('current_user') ? current_user() : null;

// 5. الـ Router والصفحات الخاصة بالسكربت
$page = $_GET['page'] ?? 'home';
$pageTitle = 'الرئيسية';

// تضمين الهيدر الخاص بالموقع من المجلد الرئيسي
if (file_exists($root_dir . '/header.php')) {
    include_once $root_dir . '/header.php';
}

// 6. عرض محتوى الصفحات وجلب البيانات من FastCard
switch ($page) {
    case 'products':
        if (function_exists('fc_content')) {
            // جلب الأقسام الرئيسية من الفاست كارد بدون أي أخطاء تعريفيّة
            $rootData = fc_content(0); 
            $categories = $rootData['categories'] ?? [];
        }
        ?>
        <div class="container" style="margin-top: 20px;">
            <h1 class="section-title">الأقسام والمنتجات المتوفرة 🛒</h1>
            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 10px)); gap: 15px; margin-top:20px;">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <div class="card" style="padding: 15px; text-align: center; border: 1px solid var(--border); border-radius: 8px;">
                            <h3><?= htmlspecialchars($cat['name']) ?></h3>
                            <a href="?page=category&id=<?= $cat['id'] ?>" class="btn-mini" style="display:inline-block; margin-top:10px; text-decoration:none;">عرض القسم</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="muted">لا يوجد أقسام متوفرة حالياً، تأكد من الإعدادات.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        break;

    case 'profile':
        ?>
        <div class="container" style="margin-top: 20px;">
            <h1 class="section-title">حسابي الشخصي 👤</h1>
            <div class="card" style="margin-top: 15px; padding: 20px;">
                <?php if ($user): ?>
                    <p>مرحباً بك: <b><?= htmlspecialchars($user['name'] ?? '') ?></b></p>
                    <p>البريد الإلكتروني: <span><?= htmlspecialchars($user['email'] ?? '') ?></span></p>
                    <p>رصيدك الحالي: <b style="color: var(--ok, #22c55e);"><?= number_format($user['balance'] ?? 0) ?> ل.س</b></p>
                <?php else: ?>
                    <p class="warn">الرجاء تسجيل الدخول أولاً للوصول إلى حسابك.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        break;

    case 'home':
    default:
        ?>
        <div class="container" style="margin-top: 40px; text-align: center;">
            <h1 class="section-title">مرحباً بك في متجر الشحن الرقمي الخاص بنا 🚀</h1>
            <p class="muted" style="margin-top: 10px;">أسرع منصة لشحن الألعاب والبطاقات الرقمية تلقائياً.</p>
            
            <div style="margin-top: 30px;">
                <a href="?page=products" class="btn" style="text-decoration: none; display: inline-block; padding: 12px 24px; font-weight: bold;">تصفح الخدمات والمنتجات 🛒</a>
            </div>
        </div>
        <?php
        break;
}

// تضمين الفوتر الخاص بالموقع من المجلد الرئيسي
if (file_exists($root_dir . '/footer.php')) {
    include_once $root_dir . '/footer.php';
}
