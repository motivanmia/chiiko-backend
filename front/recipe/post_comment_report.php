<?php
// =================================================================
//  API: 新增一筆留言檢舉 (增強除錯版)
// =================================================================



require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_method('POST');

// 權限檢查
if (!isset($_SESSION['user_id'])) {
    send_json(['status' => 'error', 'message' => '請先登入才能檢舉'], 401);
}
$reporter_user_id = $_SESSION['user_id'];

// 讀取資料
$data = get_json_input();
$reported_comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : null;
$report_type = isset($data['type']) ? (int)$data['type'] : null;

// 驗證資料
if (empty($reported_comment_id) || $report_type === null) {
    send_json(['status' => 'error', 'message' => '缺少被檢舉的留言 ID 或檢舉類型'], 400);
    exit;
}
$allowed_types = [1, 2, 3, 4, 5];
if (!in_array($report_type, $allowed_types, true)) {
    send_json(['status' => 'error', 'message' => '無效的檢舉類型'], 400);
    exit;
}

$report_content = '使用者透過燈箱快速檢舉';

// 準備 SQL
$sql = "INSERT INTO comment_report (reported_user_id, reported_comment_id, content, type, status) 
        VALUES (?, ?, ?, ?, 0)";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_json(['status' => 'error', 'message' => 'DB Prepare 失敗', 'db_error' => $mysqli->error], 500);
    exit;
}

$stmt->bind_param('iisi', $reporter_user_id, $reported_comment_id, $report_content, $report_type);

// 【✅ 核心除錯點 ✅】
// 我們將 execute() 的結果存起來，並在失敗時回傳詳細的資料庫錯誤訊息
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        send_json(['status' => 'success', 'message' => '檢舉已成功送出！感謝您的回報。'], 201);
    } else {
        send_json(['status' => 'error', 'message' => '操作已執行，但沒有任何資料被寫入'], 500);
    }
} else {
    // 如果 execute() 失敗 (例如外鍵約束失敗)，就會進入這裡
    send_json([
        'status' => 'error', 
        'message' => '資料庫寫入失敗 (可能是外鍵約束問題)', 
        'db_error_code' => $stmt->errno,
        'db_error_message' => $stmt->error, // 這會告訴我們確切的錯誤原因
        'attempted_data' => [ // 這會顯示我們嘗試寫入的資料
            'reporter_user_id' => $reporter_user_id,
            'reported_comment_id' => $reported_comment_id,
            'type' => $report_type
        ]
    ], 500);
}

$stmt->close();
$mysqli->close();
?>