<?php
// /admin/recipe/get_comment_reports.php (最終優化版)

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
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

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_json(['status' => 'error', 'message' => '資料庫查詢失敗: ' . $mysqli->error], 500);
}
$stmt->execute();
$result = $stmt->get_result();
$reports = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

send_json(['status' => 'success', 'data' => $reports]);
$mysqli->close();
?>```