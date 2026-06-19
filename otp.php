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
    if (strlen($gsm) < 12) jout(false, 'رقم الموبايل غير صحيح');

    // منع الإرسال المتكرر السريع (مرة كل 60 ثانية)
    $st = db()->prepare("SELECT created_at FROM otp_codes WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$U['id']]);
    $last = $st->fetchColumn();
    if ($last && (time() - strtotime($last)) < 60) {
        jout(false, 'انتظر دقيقة قبل إعادة الإرسال');
    }

    // raselSMS هو من يولّد الرمز ويرسله — نحن نخزّن verificationId فقط
    [$sent, $smsg, $vid] = rasel_send_otp($gsm);
    if (!$sent) jout(false, $smsg);
    if (!$vid)  jout(false, 'تعذّر بدء عملية التحقق، حاول مجدداً');

    // حذف القديم وتخزين verificationId الجديد (نخزّنه بحقل code مؤقتاً)
    db()->prepare("DELETE FROM otp_codes WHERE user_id=?")->execute([$U['id']]);
    db()->prepare("INSERT INTO otp_codes (user_id,phone,code,expires_at) VALUES (?,?,?,?)")
        ->execute([$U['id'], $gsm, $vid, date('Y-m-d H:i:s', time() + 600)]);

    jout(true, 'تم إرسال رمز التحقق إلى رقمك 📱');
}

// ===== تأكيد الرمز =====
if ($action === 'verify') {
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (strlen($code) < 4) jout(false, 'الرمز غير صحيح');

    $st = db()->prepare("SELECT * FROM otp_codes WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$U['id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) jout(false, 'لم يتم إرسال رمز، اطلب رمزاً جديداً');

    $verificationId = $row['code']; // خزّنا verificationId هنا
    $phone = $row['phone'];

    [$ok, $vmsg] = rasel_verify_otp($verificationId, $code);
    if (!$ok) jout(false, $vmsg);

    // نجح: توثيق الرقم
    db()->prepare("UPDATE users SET phone=?, phone_verified=1 WHERE id=?")
        ->execute([$phone, $U['id']]);
    db()->prepare("DELETE FROM otp_codes WHERE user_id=?")->execute([$U['id']]);
    notify_user($U['id'], 'تم توثيق رقمك ✅', 'تم توثيق رقم موبايلك بنجاح. حسابك الآن أكثر أماناً.', '✅');
    jout(true, 'تم توثيق رقمك بنجاح ✅');
}

jout(false, 'طلب غير صحيح');
