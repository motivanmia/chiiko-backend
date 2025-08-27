<?php
// /admin/recipe/update_report_status.php (最終聯動版)

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$allowed_statuses = [0, 1, 2]; // 0:待處理, 1:已下架, 2:已恢復
if (!in_array($new_status, $allowed_statuses)) {
    send_json(['status' => 'error', 'message' => '無效的狀態值'], 400);
}

// ==========================================================
// 【✅ 核心修改 ✅】 使用資料庫交易來確保資料一致性
// ==========================================================

// 關閉自動提交
$mysqli->autocommit(FALSE);

try {
    // === 第一步：更新 comment_report 表的狀態 ===
    // 💡 替換成 mysqli_query，並直接嵌入變數
    $sql_update_report = "UPDATE comment_report SET status = {$new_status} WHERE report_id = {$report_id}";
    if (!$mysqli->query($sql_update_report)) {
        throw new Exception('執行更新檢舉狀態失敗: ' . $mysqli->error);
    }

    // === 第二步：聯動更新 recipe_comment 表的狀態 ===
    // 💡 替換成 mysqli_query
    $sql_find_comment_id = "SELECT reported_comment_id FROM comment_report WHERE report_id = {$report_id} LIMIT 1";
    $result = $mysqli->query($sql_find_comment_id);

    if (!$result) {
        throw new Exception('查詢留言ID失敗: ' . $mysqli->error);
    }
    
    $report_data = $result->fetch_assoc();
    $result->free();

    if ($report_data && !empty($report_data['reported_comment_id'])) {
        $comment_to_update_id = $report_data['reported_comment_id'];

        $new_comment_status = ($new_status === 1) ? 1 : 0;
        
        // 💡 替換成 mysqli_query，並直接嵌入變數
        $sql_update_comment = "UPDATE recipe_comment SET status = {$new_comment_status} WHERE comment_id = {$comment_to_update_id}";
        if (!$mysqli->query($sql_update_comment)) {
            throw new Exception('執行更新留言狀態失敗: ' . $mysqli->error);
        }
    } else {
        throw new Exception('找不到對應的檢舉記錄');
    }

    // 如果以上所有操作都成功，提交交易
    $mysqli->commit();
    send_json(['status' => 'success', 'message' => '狀態更新成功！']);

} catch (Exception $e) {
    // 如果中間有任何一步失敗，回滾所有操作
    $mysqli->rollback();
    send_json(['status' => 'error', 'message' => $e->getMessage()], 500);
}

// 恢復自動提交
$mysqli->autocommit(TRUE);
$mysqli->close();
?>