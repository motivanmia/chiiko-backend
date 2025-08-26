<?php
// /front/recipe/post_comment_report.php (最終優化版)

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_method('POST');

if (!isset($_SESSION['user_id'])) {
    send_json(['status' => 'error', 'message' => '請先登入才能檢舉'], 401);
}
$reporter_user_id = $_SESSION['user_id'];

$data = get_json_input();
$reported_comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : null;
$report_type = isset($data['type']) ? (int)$data['type'] : null;

if (empty($reported_comment_id) || $report_type === null) {
    send_json(['status' => 'error', 'message' => '缺少必要參數'], 400);
}
$allowed_types = [1, 2, 3, 4, 5];
if (!in_array($report_type, $allowed_types, true)) {
    send_json(['status' => 'error', 'message' => '無效的檢舉類型'], 400);
}

// ==========================================================
// 【✅ 核心修改 ✅】
// ==========================================================

$report_content = null; // 初始化變數

try {
    // 第一步：根據 reported_comment_id 查詢出原始留言的 content
    $sql_find_content = "SELECT content FROM recipe_comment WHERE comment_id = ? LIMIT 1";
    $stmt_find = $mysqli->prepare($sql_find_content);
    if (!$stmt_find) throw new Exception('準備查詢留言內容失敗');

    $stmt_find->bind_param('i', $reported_comment_id);
    $stmt_find->execute();
    $result = $stmt_find->get_result();
    $comment_data = $result->fetch_assoc();
    $stmt_find->close();

    if ($comment_data && isset($comment_data['content'])) {
        $report_content = $comment_data['content'];
    } else {
        // 如果找不到對應的留言 (可能已被刪除)，我們就給一個預設值
        $report_content = '[原始留言已被刪除]';
    }

    // 第二步：將包含真實留言內容的檢舉資料寫入資料庫
    $sql_insert = "INSERT INTO comment_report (reported_user_id, reported_comment_id, content, type, status) 
                   VALUES (?, ?, ?, ?, 0)";

    $stmt_insert = $mysqli->prepare($sql_insert);
    if (!$stmt_insert) throw new Exception('準備寫入檢舉資料失敗');

    $stmt_insert->bind_param('iisi', $reporter_user_id, $reported_comment_id, $report_content, $report_type);
    
    if ($stmt_insert->execute() && $stmt_insert->affected_rows > 0) {
        send_json(['status' => 'success', 'message' => '檢舉已成功送出！感謝您的回報。'], 201);
    } else {
        throw new Exception('檢舉提交失敗，您可能已經檢舉過此留言');
    }
    $stmt_insert->close();

} catch (Exception $e) {
    // 捕捉任何資料庫操作的錯誤
    send_json(['status' => 'error', 'message' => $e->getMessage()], 500);
}

$mysqli->close();
?>