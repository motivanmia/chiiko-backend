<?php
// /admin/recipe/get_comment_reports.php (最終優化版)

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_method('GET');

if (!isset($_SESSION['manager_id'])) {
    send_json(['status' => 'error', 'message' => '無權限存取'], 403);
}

// 【✅ 核心優化 ✅】
// 使用 LEFT JOIN 確保即使關聯的留言或使用者被刪除，檢舉記錄本身依然能被查詢到。
$sql = "SELECT 
            cr.report_id,
            cr.created_at AS report_date,
            cr.reported_user_id AS reporter_user_id,
            cr.type AS report_type,
            rc.user_id AS offender_user_id,
            rc.content AS comment_content,
            cr.status
        FROM comment_report cr
        LEFT JOIN recipe_comment rc ON cr.reported_comment_id = rc.comment_id
        LEFT JOIN users u_offender ON rc.user_id = u_offender.user_id
        ORDER BY cr.created_at DESC";

// 💡 將 prepare 和 execute 替換為 mysqli_query
$result = $mysqli->query($sql);

if (!$result) {
    // 檢查查詢是否失敗，並提供錯誤訊息
    send_json(['status' => 'error', 'message' => '資料庫查詢失敗: ' . $mysqli->error], 500);
}

// 💡 取得所有結果
$reports = $result->fetch_all(MYSQLI_ASSOC);

// 💡 釋放結果集
$result->free();

send_json(['status' => 'success', 'data' => $reports]);

// 💡 關閉資料庫連線
$mysqli->close();
?>