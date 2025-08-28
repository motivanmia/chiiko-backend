<?php
// /front/recipe/get_recipe_comments.php (已修改為動態網址和安全的 get_result)

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('GET');
$recipe_id = get_int_param('recipe_id');
if (empty($recipe_id)) { 
    send_json(['status' => 'error', 'message' => 'Missing recipe_id'], 400); 
}

// --- 主要修改區域 1: 使用預備陳述式 (Prepared Statements) ---

// 💡 SQL 查詢中的使用者輸入（$recipe_id）改為問號 (?) 作為佔位符，更安全
$sql = "SELECT 
            rc.comment_id, rc.user_id, rc.parent_id, rc.content, rc.image, rc.created_at,
            u.name AS member_name, 
            u.image AS member_avatar
        FROM recipe_comment rc
        JOIN users u ON rc.user_id = u.user_id
        WHERE rc.recipe_id = ? AND rc.status = 0
        ORDER BY rc.created_at ASC";

// 1. 準備 SQL 模板
$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    send_json(['status' => 'error', 'message' => 'SQL prepare failed: ' . $mysqli->error], 500);
}

// 2. 綁定參數到佔位符（'i' 代表 integer）
$stmt->bind_param('i', $recipe_id);

// 3. 執行查詢
if (!$stmt->execute()) {
    send_json(['status' => 'error', 'message' => 'SQL execute failed: ' . $stmt->error], 500);
}

// 4. 💡 使用 get_result() 取得查詢結果
$result = $stmt->get_result();

// 5. 從結果中獲取所有資料
$all_comments = $result->fetch_all(MYSQLI_ASSOC);

//