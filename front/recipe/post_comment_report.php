<?php
// /front/recipe/post_comment_report.php (最終優化版)

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    // 💡 第一步：根據 reported_comment_id 查詢出原始留言的 content
    // 使用 mysqli_query 並直接嵌入變數
    $sql_find_content = "SELECT content FROM recipe_comment WHERE comment_id = {$reported_comment_id} LIMIT 1";
    $result = $mysqli->query($sql_find_content);
    
    if (!$result) {
        throw new Exception('查詢留言內容失敗');
    }

    $comment_data = $result->fetch_assoc();
    $result->free();

    if ($comment_data && isset($comment_data['content'])) {
        $report_content = $comment_data['content'];
    } else {
        $report_content = '[原始留言已被刪除]';
    }

    // 💡 第二步：將包含真實留言內容的檢舉資料寫入資料庫
    // 使用 mysqli_query 並直接嵌入變數
    $safe_content = $mysqli->real_escape_string($report_content);
    $sql_insert = "INSERT INTO comment_report (reported_user_id, reported_comment_id, content, type, status) 
                   VALUES ('{$reporter_user_id}', '{$reported_comment_id}', '{$safe_content}', '{$report_type}', 0)";

    if ($mysqli->query($sql_insert)) {
        if ($mysqli->affected_rows > 0) {
            send_json(['status' => 'success', 'message' => '檢舉已成功送出！感謝您的回報。'], 201);
        } else {
            throw new Exception('檢舉提交失敗，您可能已經檢舉過此留言');
        }
    } else {
        throw new Exception('寫入檢舉資料失敗：' . $mysqli->error);
    }

} catch (Exception $e) {
    // 捕捉任何資料庫操作的錯誤
    send_json(['status' => 'error', 'message' => $e->getMessage()], 500);
}

$mysqli->close();
?>