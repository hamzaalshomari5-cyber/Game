<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== فحص توثيق الموبايل (OTP) ===\n\n";

// 1) فحص المتغيرات
$token = aman_token();
$tid = aman_template_id();
echo "1) AMAN_TOKEN: " . ($token !== '' ? "موجود (" . strlen($token) . " خانة، يبدأ بـ " . substr($token,0,4) . "...)" : "❌ غير موجود!") . "\n";
echo "   AMAN_TEMPLATE_ID: " . $tid . "\n";
echo "   otp_enabled: " . (otp_enabled() ? "نعم ✅" : "لا ❌") . "\n\n";

if ($token === '') {
    echo "⚠️ المشكلة: AMAN_TOKEN غير مضاف على Railway.\n";
    echo "الحل: صندوق Game → Variables → أضف AMAN_TOKEN و AMAN_TEMPLATE_ID=2\n";
    exit;
}

// 2) تجربة إرسال فعلي (إذا أعطيت رقم)
$gsm = $_GET['gsm'] ?? '';
if ($gsm === '') {
    echo "2) لتجربة إرسال فعلي، أضف رقمك للرابط:\n";
    echo "   otpcheck.php?gsm=09XXXXXXXX\n";
    exit;
}

$normalized = normalize_gsm($gsm);
echo "2) تجربة إرسال:\n";
echo "   الرقم المدخل: $gsm\n";
echo "   بعد التحويل: $normalized\n\n";

$code = (string)random_int(100000, 999999);
$body = json_encode([
    'gsm'         => $normalized,
    'template_id' => $tid,
    'code'        => $code,
    'language'    => 0,
]);

$ch = curl_init('https://aman-gate.com/otp/send/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: Token ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "   ما أرسلناه: $body\n\n";
echo "   === رد Aman Gate ===\n";
echo "   HTTP Code: $http\n";
echo "   الرد الكامل: " . ($res ?: '(فاضي)') . "\n";
if ($err) echo "   خطأ curl: $err\n";
echo "\n";

// تفسير
echo "   === التفسير ===\n";
if ($http === 201) {
    echo "   ✅ تم الإرسال بنجاح! لازم يوصلك SMS فيه الرمز: $code\n";
    echo "   إذا ما وصل الـ SMS رغم نجاح الطلب، المشكلة عند مزوّد الرسائل أو الرقم.\n";
} elseif ($http === 401) {
    echo "   ❌ خطأ 401: التوكن غلط، أو الـ IP مش مسموح (whitelist).\n";
    echo "   الحل: تأكد من التوكن، وأضف IP سيرفر Railway بلوحة Aman Gate.\n";
} elseif ($http === 402) {
    echo "   ❌ خطأ 402: لا يوجد اشتراك فعّال أو تجاوزت الحصة.\n";
    echo "   الحل: جدّد اشتراكك بـ Aman Gate.\n";
} elseif ($http === 400) {
    echo "   ❌ خطأ 400: بيانات غلط (رقم القالب أو صيغة الرقم).\n";
    echo "   تأكد أن template_id=$tid صحيح وأن الرقم بصيغة دولية.\n";
} else {
    echo "   ⚠️ كود غير متوقع: $http — راجع الرد الكامل فوق.\n";
}
