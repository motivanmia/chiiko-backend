<?php
// /admin/recipe/update_report_status.php (最終聯動版)

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
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
    // === 第一步：更新 comment_report 表的狀態 (與之前相同) ===
    $sql_update_report = "UPDATE comment_report SET status = ? WHERE report_id = ?";
    $stmt_report = $mysqli->prepare($sql_update_report);
    if (!$stmt_report) throw new Exception('準備更新檢舉狀態失敗: ' . $mysqli->error);
    
    $stmt_report->bind_param('ii', $new_status, $report_id);
    if (!$stmt_report->execute()) throw new Exception('執行更新檢舉狀態失敗: ' . $stmt_report->error);
    $stmt_report->close();


    // === 第二步：聯動更新 recipe_comment 表的狀態 ===
    // 我們需要先從 comment_report 表中找出被檢舉的留言 ID (reported_comment_id)
    $sql_find_comment_id = "SELECT reported_comment_id FROM comment_report WHERE report_id = ? LIMIT 1";
    $stmt_find = $mysqli->prepare($sql_find_comment_id);
    if (!$stmt_find) throw new Exception('準備查詢留言ID失敗: ' . $mysqli->error);
    
    $stmt_find->bind_param('i', $report_id);
    $stmt_find->execute();
    $result = $stmt_find->get_result();
    $report_data = $result->fetch_assoc();
    $stmt_find->close();

    if ($report_data && !empty($report_data['reported_comment_id'])) {
        $comment_to_update_id = $report_data['reported_comment_id'];

        // 根據新的檢舉狀態，決定留言的狀態
        // 檢舉狀態 1 (已下架) -> 留言狀態 1 (隱藏)
        // 檢舉狀態 0 (待處理) 或 2 (已恢復) -> 留言狀態 0 (正常)
        $new_comment_status = ($new_status === 1) ? 1 : 0;

        $sql_update_comment = "UPDATE recipe_comment SET status = ? WHERE comment_id = ?";
        $stmt_comment = $mysqli->prepare($sql_update_comment);
        if (!$stmt_comment) throw new Exception('準備更新留言狀態失敗: ' . $mysqli->error);

        $stmt_comment->bind_param('ii', $new_comment_status, $comment_to_update_id);
        if (!$stmt_comment->execute()) throw new Exception('執行更新留言狀態失敗: ' . $stmt_comment->error);
        $stmt_comment->close();
    } else {
        // 雖然不太可能發生，但如果找不到檢舉記錄，就拋出錯誤
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