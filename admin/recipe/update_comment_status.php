<?php
// =================================================================
// API: 更新留言狀態 (update_comment_status.php)
// Actor: Admin (Manager)
// =================================================================

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_method('POST');

// 權限檢查
if (!isset($_SESSION['manager_id'])) {
send_json(['status' => 'error', 'message' => '無權限存取'], 403);
exit;
}

$data = get_json_input();
$comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : null;
$new_status = isset($data['new_status']) ? (int)$data['new_status'] : null;

// 驗證輸入
if (empty($comment_id)) {
send_json(['status' => 'error', 'message' => '缺少留言 ID'], 400);
exit;
}
// 確保 new_status 是我們允許的值 (0:正常, 1:隱藏)
if (!in_array($new_status, [0, 1])) {
send_json(['status' => 'error', 'message' => '無效的狀態值'], 400);
exit;
}

// 更新資料庫
$sql = "UPDATE recipe_comment SET status = ? WHERE comment_id = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
send_json(['status' => 'error', 'message' => '資料庫查詢準備失敗: ' . $mysqli->error], 500);
exit;
}

$stmt->bind_param('ii', $new_status, $comment_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
send_json(['status' => 'success', 'message' => '留言狀態更新成功！']);
} else {
send_json(['status' => 'error', 'message' => '操作失敗或該留言不存在'], 404);
}

$stmt->close();
$mysqli->close();
?>