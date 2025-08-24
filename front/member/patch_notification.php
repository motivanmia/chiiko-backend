<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// 檢查方法：允許 PATCH，或 POST + _method=PATCH
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PATCH') {
  $method = 'PATCH';
}
if ($method !== 'PATCH') {
  http_response_code(405);
  echo json_encode(['status' => 'fail', 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
  exit;
}

// 驗證登入
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['status' => 'fail', 'message' => '未登入'], JSON_UNESCAPED_UNICODE);
  exit;
}
$user_id = (int)$_SESSION['user_id'];

// 讀取 JSON body
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

// 允許兩種格式：{ id: 123 } 或 { ids: [1,2,3] }
$ids = [];
if (isset($payload['id'])) {
  $ids = [ (int)$payload['id'] ];
} elseif (isset($payload['ids']) && is_array($payload['ids'])) {
  $ids = array_values(array_map('intval', $payload['ids']));
}

// 檢查
if (empty($ids)) {
  http_response_code(400);
  echo json_encode(['status' => 'fail', 'message' => '缺少 notification id'], JSON_UNESCAPED_UNICODE);
  exit;
}

// 準備 SQL：只更新屬於該會員、且目前為未讀(0) 的通知
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "
  UPDATE notification
     SET status = '1'
   WHERE receiver_id = ?
     AND status = '0'
     AND notification_id IN ($placeholders)
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['status' => 'fail', 'message' => '資料庫準備失敗'], JSON_UNESCAPED_UNICODE);
  exit;
}

// 綁定參數
// 第一個是 receiver_id，後面是 ids
$types = str_repeat('i', count($ids) + 1);
$params = array_merge([$user_id], $ids);

// bind_param 需要用參考
$stmt->bind_param($types, ...$params);
$ok = $stmt->execute();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['status' => 'fail', 'message' => '更新失敗'], JSON_UNESCAPED_UNICODE);
  exit;
}

// 回傳受影響筆數
echo json_encode([
  'status' => 'success',
  'message' => '已標記為已讀',
  'affected' => $stmt->affected_rows
], JSON_UNESCAPED_UNICODE);
