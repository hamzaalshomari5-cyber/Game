<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== فحص توثيق الموبايل (raselSMS) ===\n\n";

$key = rasel_key();
echo "1) RASEL_API_KEY: " . ($key !== '' ? "موجود (" . strlen($key) . " خانة)" : "❌ غير موجود!") . "\n";
echo "   RASEL_BASE_URL: " . rasel_base() . "\n";
echo "   RASEL_CHANNEL: " . rasel_channel() . "\n";
echo "   otp_enabled: " . (otp_enabled() ? "نعم ✅" : "لا ❌") . "\n\n";

if ($key === '') {
    echo "⚠️ المشكلة: RASEL_API_KEY غير مضاف على Railway.\n";
    echo "الحل: صندوق Game → Variables → أضف RASEL_API_KEY\n";
    exit;
}

$gsm = $_GET['gsm'] ?? '';
if ($gsm === '') {
    echo "2) لتجربة إرسال فعلي، أضف رقمك للرابط:\n";
    echo "   otpcheck.php?gsm=09XXXXXXXX\n";
    exit;
}

$normalized = normalize_gsm($gsm);
echo "2) تجربة إرسال:\n";
echo "   الرقم المدخل: $gsm\n";
echo "   بعد التحويل: $normalized\n";
echo "   القناة: " . rasel_channel() . "\n\n";

[$http, $data] = rasel_request('/api/v2/verification/send', [
    'to'      => $normalized,
    'channel' => rasel_channel(),
]);

echo "   === رد raselSMS ===\n";
echo "   HTTP Code: $http\n";
echo "   الرد: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "   === التفسير ===\n";
if ($http >= 200 && $http < 300) {
    $vid = $data['verificationId'] ?? ($data['data']['verificationId'] ?? '(غير موجود!)');
    echo "   ✅ تم الإرسال بنجاح! لازم يوصلك SMS فيه الرمز.\n";
    echo "   verificationId: $vid\n";
    echo "   (احفظه إذا بدك تجرب التحقق يدوياً)\n";
} else {
    $code = $data['code'] ?? ($data['error'] ?? '(غير معروف)');
    echo "   ❌ فشل الإرسال. كود الخطأ: $code\n";
    echo "   راجع الرد الكامل فوق لمعرفة السبب.\n";
    if (stripos(json_encode($data), 'channel') !== false) {
        echo "   💡 قد تكون القناة غلط — جرّب RASEL_CHANNEL = local_sms أو international_sms\n";
    }
    if ($http === 401 || $http === 403) {
        echo "   💡 الـ API Key غلط أو غير مفعّل.\n";
    }
}
