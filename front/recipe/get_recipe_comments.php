<?php
// =================================================================
//  API: 取得巢狀留言列表 (已新增預設頭像邏輯)
// =================================================================

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('GET');
$recipe_id = get_int_param('recipe_id');
if (empty($recipe_id)) { 
    send_json(['status' => 'error', 'message' => 'Missing recipe_id'], 400); 
}

// --- 資料庫查詢邏輯 (保持不變) ---
$sql = "SELECT 
            rc.comment_id, rc.user_id, rc.parent_id, rc.content, rc.image, rc.created_at,
            u.name AS member_name, 
            u.image AS member_avatar
        FROM recipe_comment rc
        JOIN users u ON rc.user_id = u.user_id
        WHERE rc.recipe_id = ? AND rc.status = 0
        ORDER BY rc.created_at ASC";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) { send_json(['status' => 'error', 'message' => 'SQL prepare failed: ' . $mysqli->error], 500); }
$stmt->bind_param('i', $recipe_id);
if (!$stmt->execute()) { send_json(['status' => 'error', 'message' => 'SQL execute failed: ' . $stmt->error], 500); }
$result = $stmt->get_result();
$all_comments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// --- 資料庫查詢結束 ---


// --- 動態產生 URL & 圖片路徑處理 ---

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = "{$protocol}://{$host}";
$uploads_path = '/uploads/';

// --- 【✅ 核心修改處 ✅】 ---
// 💡 定義您的預設頭像檔名
$default_avatar_filename = 'default_avatar.png';

foreach ($all_comments as &$comment) {
    // --- 處理留言者頭像 ---
    if (!empty($comment['member_avatar']) && !filter_var($comment['member_avatar'], FILTER_VALIDATE_URL)) {
        // 情況1: 使用者有上傳頭像 (DB 存的是檔名) -> 拼接完整 URL
        $comment['member_avatar'] = $base_url . $uploads_path . $comment['member_avatar'];

    } elseif (empty($comment['member_avatar'])) {
        // 情況2: 使用者沒有頭像 (DB 欄位為空) -> 使用預設頭像
        $comment['member_avatar'] = $base_url . $uploads_path . $default_avatar_filename;
    }
    // 情況3 (隱含): DB 中已是完整 URL (如 Google 登入頭像)，則不處理

    // --- 處理留言附圖 (邏輯不變) ---
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