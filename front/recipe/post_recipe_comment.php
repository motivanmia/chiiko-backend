<?php
// =================================================================
//  API: 新增食譜留言 (第2步修正：支援圖片上傳)
// =================================================================

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_method('POST');

// 權限檢查 (保持不變)
if (!isset($_SESSION['user_id'])) {
    send_json(['status' => 'error', 'message' => '請先登入才能留言'], 401);
}
$user_id = $_SESSION['user_id'];

// 【✅ 核心修正 1】
// 不再從 get_json_input() 讀取，而是從 $_POST 讀取文字資料
$recipe_id = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : null;
$parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null; 
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

// 驗證 (保持不變)
if (empty($recipe_id) || empty($content)) {
    send_json(['status' => 'error', 'message' => '缺少食譜ID或留言內容'], 400);
}

// 【✅ 核心修正 2】
// 處理圖片上傳
$image_filename = null; // 初始化圖片檔名為 null
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    // 如果有上傳 'image' 這個欄位的檔案且沒有錯誤，就處理它
    // 這裡直接呼叫您 functions.php 中現成的函式！
    $image_filename = handleFileUpload($_FILES['image']);
}

// 【✅ 核心修正 3】
// SQL INSERT 語句現在也包含 image 欄位
$sql = "INSERT INTO recipe_comment (user_id, recipe_id, parent_id, content, image, status) 
        VALUES (?, ?, ?, ?, ?, 0)";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_json(['status' => 'error', 'message' => '資料庫查詢失敗: ' . $mysqli->error], 500);
    exit;
}

// 【✅ 核心修正 4】
// 綁定參數時，增加對應 image 欄位的 's' (string)
$stmt->bind_param('iiiss', $user_id, $recipe_id, $parent_id, $content, $image_filename);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $new_comment_id = $stmt->insert_id;
    
    // 反查剛才寫入的完整留言資料
    $query_new = "
        SELECT rc.*, u.name as member_name, u.image as member_avatar 
        FROM recipe_comment rc 
        JOIN users u ON rc.user_id = u.user_id
        WHERE rc.comment_id = ?";
    $stmt_new = $mysqli->prepare($query_new);
    $stmt_new->bind_param('i', $new_comment_id);
    $stmt_new->execute();
    $new_comment_data = $stmt_new->get_result()->fetch_assoc();
    $stmt_new->close();

    // 【✅ 核心修正 5】
    // 為新留言的圖片路徑也加上完整的 URL 前綴再回傳
    $base_url = 'http://localhost:8888';
    $uploads_path = '/uploads/';

    if ($new_comment_data) {
        if (!empty($new_comment_data['member_avatar']) && !filter_var($new_comment_data['member_avatar'], FILTER_VALIDATE_URL)) {
            $new_comment_data['member_avatar'] = $base_url . $uploads_path . $new_comment_data['member_avatar'];
        }
        if (!empty($new_comment_data['image']) && !filter_var($new_comment_data['image'], FILTER_VALIDATE_URL)) {
            $new_comment_data['image'] = $base_url . $uploads_path . $new_comment_data['image'];
        }
    }

    send_json(['status' => 'success', 'data' => $new_comment_data], 201);
} else {
    send_json(['status' => 'error', 'message' => '留言發布失敗', 'db_error' => $stmt->error], 500);
}
$stmt->close();
$mysqli->close();
?>