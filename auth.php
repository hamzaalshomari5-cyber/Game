<?php
require_once __DIR__ . '/db.php';

if (isset($_GET['logout'])) { session_destroy(); header('Location: /index.php'); exit; }

$err = ''; $mode = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($_POST['action'] === 'register') {
        $name = trim($_POST['name'] ?? '');
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
            $err = 'تأكد من الاسم والإيميل وكلمة مرور 6 أحرف على الأقل';
            $mode = 'register';
        } else {
            try {
                db()->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)")
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                $_SESSION['uid'] = last_id('users');
                header('Location: /index.php'); exit;
            } catch (Exception $ex) { $err = 'الإيميل مستخدم مسبقاً'; $mode = 'register'; }
        }
    } else {
        $st = db()->prepare("SELECT * FROM users WHERE email=?");
        $st->execute([$email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u && password_verify($pass, $u['password'])) {
            $_SESSION['uid'] = $u['id'];
            header('Location: ' . ($u['role'] === 'admin' ? '/admin.php' : '/index.php')); exit;
        }
        $err = 'بيانات الدخول غير صحيحة';
    }
}

$pageTitle = 'تسجيل الدخول';
include __DIR__ . '/header.php'; ?>

<div class="auth-box card">
  <h2><?= $mode === 'register' ? 'إنشاء حساب جديد' : 'تسجيل الدخول' ?></h2>
  <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="action" value="<?= $mode === 'register' ? 'register' : 'login' ?>">
    <?php if ($mode === 'register'): ?>
      <label>الاسم</label>
      <input name="name" required>
    <?php endif; ?>
    <label>البريد الإلكتروني</label>
    <input type="email" name="email" required>
    <label>كلمة المرور</label>
    <input type="password" name="password" required>
    <button class="btn full" type="submit"><?= $mode === 'register' ? 'إنشاء الحساب' : 'دخول' ?></button>
  </form>

  <?php if (google_enabled()): ?>
  <div class="auth-divider"><span>أو</span></div>
  <a class="btn-google" href="/google_login.php">
    <img src="https://store.ahminix.com/google-icon-logo.svg" alt="Google" width="18" height="18">
    تسجيل بواسطة غوغل
  </a>
  <?php endif; ?>

  <p class="muted center">
    <?php if ($mode === 'register'): ?>
      عندك حساب؟ <a href="/auth.php">سجّل دخول</a>
    <?php else: ?>
      ما عندك حساب؟ <a href="/auth.php?mode=register">أنشئ حساب جديد</a>
    <?php endif; ?>
  </p>
</div>

<?php include __DIR__ . '/footer.php';
