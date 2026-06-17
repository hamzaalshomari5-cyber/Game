<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
$U = current_user();

function jout($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';

// ===== إرسال الرمز =====
if ($action === 'send') {
    if (!otp_enabled()) jout(false, 'خدمة التوثيق غير مفعّلة حالياً');
    $phone = trim($_POST['phone'] ?? '');
    $gsm = normalize_gsm($phone);
    if (strlen($gsm) < 11) jout(false, 'رقم الموبايل غير صحيح');

    // منع الإرسال المتكرر السريع (مرة كل 60 ثانية)
    $st = db()->prepare("SELECT created_at FROM otp_codes WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$U['id']]);
    $last = $st->fetchColumn();
    if ($last && (time() - strtotime($last)) < 60) {
        jout(false, 'انتظر دقيقة قبل إعادة الإرسال');
    }

    // توليد رمز 6 أرقام
    $code = (string)random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', time() + 300); // 5 دقائق

    // حذف الرموز القديمة لنفس المستخدم
    db()->prepare("DELETE FROM otp_codes WHERE user_id=?")->execute([$U['id']]);
    db()->prepare("INSERT INTO otp_codes (user_id,phone,code,expires_at) VALUES (?,?,?,?)")
        ->execute([$U['id'], $gsm, $code, $expires]);

    [$sent, $smsg] = aman_send_otp($gsm, $code);
    if (!$sent) jout(false, $smsg);
    jout(true, 'تم إرسال رمز التحقق إلى رقمك 📱');
}

// ===== تأكيد الرمز =====
if ($action === 'verify') {
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (strlen($code) !== 6) jout(false, 'الرمز يجب أن يكون 6 أرقام');

    $st = db()->prepare("SELECT * FROM otp_codes WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$U['id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) jout(false, 'لم يتم إرسال رمز، اطلب رمزاً جديداً');
    if (strtotime($row['expires_at']) < time()) jout(false, 'انتهت صلاحية الرمز، اطلب رمزاً جديداً');
    if ((int)$row['attempts'] >= 5) jout(false, 'تجاوزت عدد المحاولات، اطلب رمزاً جديداً');

    db()->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id=?")->execute([$row['id']]);

    if (!hash_equals($row['code'], $code)) jout(false, 'الرمز غير صحيح');

    // نجح: توثيق الرقم
    db()->prepare("UPDATE users SET phone=?, phone_verified=1 WHERE id=?")
        ->execute([$row['phone'], $U['id']]);
    db()->prepare("DELETE FROM otp_codes WHERE user_id=?")->execute([$U['id']]);
    notify_user($U['id'], 'تم توثيق رقمك ✅', 'تم توثيق رقم موبايلك بنجاح. حسابك الآن أكثر أماناً.', '✅');
    jout(true, 'تم توثيق رقمك بنجاح ✅');
}

jout(false, 'طلب غير صحيح');
