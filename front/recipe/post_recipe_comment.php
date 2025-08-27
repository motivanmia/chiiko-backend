<?php
// =================================================================
//  API: 新增食譜留言 (第2步修正：支援圖片上傳)
// =================================================================

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    $image_filename = handleFileUpload($_FILES['image']);
}

// 💡 替換成 mysqli_query 的寫法
// 這裡將所有變數都直接嵌入到 SQL 字串中，並用 real_escape_string 進行轉義
$safe_content = $mysqli->real_escape_string($content);
$safe_image_filename = $mysqli->real_escape_string($image_filename);

$sql = "INSERT INTO recipe_comment (user_id, recipe_id, parent_id, content, image, status) 
        VALUES ({$user_id}, {$recipe_id}, " . ($parent_id === null ? "NULL" : "'{$parent_id}'") . ", '{$safe_content}', " . ($image_filename === null ? "NULL" : "'{$safe_image_filename}'") . ", 0)";

if ($mysqli->query($sql)) {
    if ($mysqli->affected_rows > 0) {
        $new_comment_id = $mysqli->insert_id;
        
        // 💡 替換成 mysqli_query 的寫法
        // 反查剛才寫入的完整留言資料
        $query_new = "
            SELECT rc.*, u.name as member_name, u.image as member_avatar 
            FROM recipe_comment rc 
            JOIN users u ON rc.user_id = u.user_id
            WHERE rc.comment_id = {$new_comment_id}";
        $result_new = $mysqli->query($query_new);

        $new_comment_data = null;
        if ($result_new && $result_new->num_rows > 0) {
            $new_comment_data = $result_new->fetch_assoc();
            $result_new->free();
        }

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
        send_json(['status' => 'error', 'message' => '留言發布失敗，affected_rows 為 0'], 500);
    }
} else {
    send_json(['status' => 'error', 'message' => '留言發布失敗', 'db_error' => $mysqli->error], 500);
}
$mysqli->close();
?>