<?php
// /api/admin/update_report_status.php
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_method('POST');

if (!isset($_SESSION['manager_id'])) {
    send_json(['status' => 'error', 'message' => '無權限存取'], 403);
}

$data = get_json_input();
$report_id = isset($data['report_id']) ? (int)$data['report_id'] : null;
$new_status = isset($data['new_status']) ? (int)$data['new_status'] : null;

if (empty($report_id) || $new_status === null) {
    send_json(['status' => 'error', 'message' => '缺少必要參數'], 400);
}
// 根據您的資料庫，status 是 0:待處理, 1:已下架, 2:已恢復
$allowed_statuses = [0, 1, 2];
if (!in_array($new_status, $allowed_statuses)) {
    send_json(['status' => 'error', 'message' => '無效的狀態值'], 400);
}

$sql = "UPDATE comment_report SET status = ? WHERE report_id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ii', $new_status, $report_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    send_json(['status' => 'success', 'message' => '狀態更新成功！']);
} else {
    send_json(['status' => 'error', 'message' => '操作失敗或無變更'], 500);
}
$stmt->close();
$mysqli->close();
?>