<?php
require_once __DIR__ . '/fastcard_web.php';
header('Content-Type: application/json; charset=utf-8');

$player  = trim((string)($_GET['player'] ?? ''));
$product = (int)($_GET['product'] ?? 0);
if ($player === '') { echo json_encode(['ok' => false, 'msg' => 'أدخل ID اللاعب أولاً'], JSON_UNESCAPED_UNICODE); exit; }

$res = fcw_check_player($player, $product);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
