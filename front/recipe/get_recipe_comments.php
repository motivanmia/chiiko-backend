<?php
// =================================================================
//  API: 取得巢狀留言列表 (第1步修正：回傳完整 URL)
// =================================================================

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('GET');
$recipe_id = get_int_param('recipe_id');
if (empty($recipe_id)) { 
    send_json(['status' => 'error', 'message' => 'Missing recipe_id'], 400); 
}

// SQL 查詢 (保持不變)
$sql = "SELECT 
            rc.comment_id, rc.user_id, rc.parent_id, rc.content, rc.image, rc.created_at,
            u.name AS member_name, 
            u.image AS member_avatar
        FROM recipe_comment rc
        JOIN users u ON rc.user_id = u.user_id
        WHERE rc.recipe_id = ? AND rc.status = 0
        ORDER BY rc.created_at ASC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $recipe_id);
$stmt->execute();
$all_comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 【✅ 核心修正 ✅】
// 為所有圖片路徑加上完整的 URL 前綴
$base_url = 'http://localhost:8888'; // 您的後端伺服器網址
$uploads_path = '/uploads/';         // 您的圖片上傳資料夾

foreach ($all_comments as &$comment) {
    // 處理留言者頭像
    if (!empty($comment['member_avatar']) && !filter_var($comment['member_avatar'], FILTER_VALIDATE_URL)) {
        $comment['member_avatar'] = $base_url . $uploads_path . $comment['member_avatar'];
    }
    // 處理留言附圖
    if (!empty($comment['image']) && !filter_var($comment['image'], FILTER_VALIDATE_URL)) {
        $comment['image'] = $base_url . $uploads_path . $comment['image'];
    }
}
unset($comment); // 斷開最後一個元素的引用

// --- 巢狀結構處理邏輯 (保持不變) ---
$threaded = []; $map = [];
foreach ($all_comments as $c) { $c['replies'] = []; $map[$c['comment_id']] = $c; }
foreach ($map as $id => &$c) {
    if ($c['parent_id'] !== null && isset($map[$c['parent_id']])) {
        $map[$c['parent_id']]['replies'][] = &$c;
    } else { $threaded[] = &$c; }
}
unset($c);

send_json(['status' => 'success', 'data' => array_reverse($threaded)]);
$mysqli->close();
?>