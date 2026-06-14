<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: application/json; charset=utf-8');

function out($ok, $data = []) {
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE); exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$message = trim((string)($in['message'] ?? ''));
$history = is_array($in['history'] ?? null) ? $in['history'] : [];

$apiKey = env_or('GEMINI_API_KEY', '');

// وضع تشخيص: افتح /ai_chat.php?debug=1 بالمتصفح
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "GEMINI_API_KEY موجود: " . ($apiKey !== '' ? 'نعم (طول: ' . strlen($apiKey) . ')' : 'لا ❌') . "\n";
    if ($apiKey === '') { echo "\n⚠️ المفتاح مش محطوط على Railway. ضيف متغير GEMINI_API_KEY"; exit; }
    $testUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($apiKey);
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['contents' => [['role' => 'user', 'parts' => [['text' => 'قل مرحبا']]]]]),
    ]);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "\nHTTP: $code\n";
    if ($err) echo "CURL_ERR: $err\n";
    echo "\nالرد:\n" . mb_substr($r, 0, 800);
    exit;
}

if ($message === '') out(false, ['msg' => 'اكتب سؤالك']);
if ($apiKey === '') {
    out(false, ['msg' => 'المساعد الذكي غير مفعّل حالياً. تواصل معنا عبر واتساب.']);
}

// ===== معلومات الموقع للسياق =====
$cats = [];
try {
    $root = fc_content(0);
    foreach (array_slice($root['categories'], 0, 15) as $c) $cats[] = $c['name'];
} catch (Exception $e) {}
$catsList = $cats ? implode('، ', $cats) : 'الألعاب، البطاقات، تطبيقات التواصل، خدمات الدفع';
$sham = shamcash_number() ? 'سيرياتيل كاش وشام كاش' : 'سيرياتيل كاش';

// ===== تعليمات النظام (محصورة بمواضيع الموقع) =====
$system = "أنت المساعد الذكي لمتجر \"" . STORE_NAME . "\"، متجر سوري لشحن الألعاب والبطاقات الرقمية.

مهمتك: مساعدة الزبائن بأسئلتهم عن المتجر فقط. تحدث باللهجة السورية البيضاء بشكل ودود ومختصر.

معلومات المتجر:
- نبيع: شحن ألعاب (ببجي موبايل، فري فاير، وغيرها)، بطاقات رقمية، اشتراكات، خدمات دفع.
- الأقسام المتوفرة: $catsList.
- طرق الدفع/الإيداع: $sham (تلقائي — الزبون يحوّل ويدخل رقم العملية فيُضاف الرصيد لمحفظته).
- آلية الطلب: الزبون يسجّل دخول، يشحن محفظته، يختار المنتج، يدخل ID اللاعب لمنتجات الألعاب، يتأكد من اسم اللاعب، ثم يشتري.
- لمنتجات ببجي وفري فاير: يوجد تحقق من اسم اللاعب قبل الشراء لضمان صحة الـ ID.
- التسليم فوري خلال دقائق (تلقائي على مدار الساعة).
- الأسعار بالليرة السورية، ويمكن عرضها بالدولار من زر العملة.

قواعد مهمة جداً:
- جاوب فقط عن مواضيع تخص المتجر (المنتجات، الشحن، الأسعار، الطلب، الدفع، الحساب، المحفظة، التحقق).
- إذا سُئلت عن أي موضوع خارج المتجر (سياسة، رياضة، برمجة، معلومات عامة، مواضيع شخصية، أو أي سؤال لا علاقة له بالمتجر)، اعتذر بلطف وقل إنك مساعد خاص بمتجر " . STORE_NAME . " وتساعد فقط بأمور المتجر، واقترح سؤالاً متعلقاً بالمتجر. لا تجب على السؤال الخارجي إطلاقاً.
- لا تخترع أسعاراً محددة لمنتجات (الأسعار تتغير) — وجّه الزبون لتصفح القسم المناسب لرؤية السعر الحالي.
- لا تعطِ معلومات تقنية عن كيفية عمل الموقع الداخلي أو الـ API أو قاعدة البيانات.
- كن مختصراً (2-4 جمل عادةً).
- إذا واجه الزبون مشكلة تحتاج تدخل بشري، وجّهه للتواصل عبر واتساب.";

// ===== بناء محتوى المحادثة لـ Gemini =====
$contents = [];
foreach ($history as $h) {
    $role = ($h['role'] ?? '') === 'assistant' ? 'model' : 'user';
    $content = trim((string)($h['content'] ?? ''));
    if ($content !== '') $contents[] = ['role' => $role, 'parts' => [['text' => $content]]];
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

$payload = [
    'system_instruction' => ['parts' => [['text' => $system]]],
    'contents' => $contents,
    'generationConfig' => ['maxOutputTokens' => 600, 'temperature' => 0.7],
];

// ===== نداء Gemini API =====
$model = 'gemini-2.0-flash';
$url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . urlencode($apiKey);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 40,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$d = json_decode($res, true);
if ($code !== 200 || !is_array($d)) {
    out(false, ['msg' => 'المساعد مشغول حالياً، حاول بعد قليل أو تواصل عبر واتساب.']);
}

// استخراج النص من رد Gemini
$reply = '';
$parts = $d['candidates'][0]['content']['parts'] ?? [];
foreach ($parts as $p) {
    if (isset($p['text'])) $reply .= $p['text'];
}
$reply = trim($reply);
if ($reply === '') $reply = 'ما فهمت سؤالك تماماً، ممكن توضّح أكتر؟';

out(true, ['reply' => $reply]);
