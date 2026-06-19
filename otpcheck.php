<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== فحص توثيق الموبايل (raselSMS) ===\n\n";

$key = rasel_key();
echo "1) RASEL_API_KEY: " . ($key !== '' ? "موجود (" . strlen($key) . " خانة)" : "❌ غير موجود!") . "\n";
echo "   RASEL_BASE_URL: " . rasel_base() . "\n";
echo "   RASEL_CHANNEL (الافتراضي): " . rasel_channel() . "\n";
echo "   otp_enabled: " . (otp_enabled() ? "نعم ✅" : "لا ❌") . "\n\n";

if ($key === '') {
    echo "⚠️ المشكلة: RASEL_API_KEY غير مضاف على Railway.\n";
    exit;
}

$gsm = $_GET['gsm'] ?? '';
if ($gsm === '') {
    echo "2) لتجربة إرسال، أضف رقمك:\n";
    echo "   otpcheck.php?gsm=09XXXXXXXX\n";
    echo "   ولتجربة قناة محددة:\n";
    echo "   otpcheck.php?gsm=09XXXXXXXX&channel=local_sms\n\n";
    echo "   القنوات المتاحة للتجربة:\n";
    echo "   - auto (بدون تحديد - يختار النظام)\n";
    echo "   - local_sms\n";
    echo "   - international_sms\n";
    echo "   - sms_syria\n";
    echo "   - sms_twilio\n";
    echo "   - whatsapp_web\n";
    exit;
}

$normalized = normalize_gsm($gsm);
$ch = $_GET['channel'] ?? rasel_channel();
echo "2) تجربة إرسال:\n";
echo "   الرقم: $normalized\n";
echo "   القناة المُجرّبة: " . ($ch === 'auto' ? 'تلقائي (بدون تحديد)' : $ch) . "\n\n";

$payload = ['to' => $normalized];
if ($ch !== 'auto') $payload['channel'] = $ch;

[$http, $data] = rasel_request('/api/v2/verification/send', $payload);

echo "   === رد raselSMS ===\n";
echo "   HTTP Code: $http\n";
echo "   الرد: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "   === التفسير ===\n";
if ($http >= 200 && $http < 300 && !empty($data['success'])) {
    echo "   ✅ نجح الإرسال عبر قناة: $ch\n";
    echo "   لازم يوصلك SMS. إذا وصل، حط RASEL_CHANNEL=$ch على Railway.\n";
} elseif (!empty($data['failedChannel'])) {
    echo "   ❌ فشلت القناة: " . $data['failedChannel'] . "\n";
    echo "   جرّب قناة تانية من الرابط: &channel=local_sms\n";
    echo "   أو راجع لوحة raselSMS وفعّل قناة SMS.\n";
} else {
    echo "   ❌ فشل. الكود: " . ($data['code'] ?? '?') . "\n";
    echo "   جرّب: &channel=auto أو &channel=international_sms\n";
}
