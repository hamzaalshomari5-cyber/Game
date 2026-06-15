<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
@start_session_once();
$uid = $_SESSION['uid'] ?? null;
if (!$uid) { echo json_encode(['count' => 0]); exit; }
$st = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$st->execute([$uid]);
echo json_encode(['count' => (int)$st->fetchColumn()]);
