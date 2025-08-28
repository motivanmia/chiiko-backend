<?php
// =================================================================
//  API: 新增食譜留言 (已修改為動態網址和安全查詢)
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

// 從 $_POST 讀取文字資料 (保持不變)
$recipe_id = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : null;
$parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null; 
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

// 驗證 (保持不變)
if (empty($recipe_id) || empty($content)) {
    send_json(['status' => 'error', 'message' => '缺少食譜ID或留言內容'], 400);
}

// 處理圖片上傳 (保持不變)
$image_filename = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $image_filename = handleFileUpload($_FILES['image']);
}

// --- 主要修改區域 1: 使用預備陳述式進行 INSERT ---

// 💡 SQL 查詢中的所有變數都改為問號 (?) 佔位符
$sql_insert = "INSERT INTO recipe_comment (user_id, recipe_id, parent_id, content, image, status) 
               VALUES (?, ?, ?, ?, ?, 0)";

// 1. 準備 SQL 模板
$stmt_insert = $mysqli->prepare($sql_insert);
if ($stmt_insert === false) {
    send_json(['status' => 'error', 'message' => 'SQL prepare failed: ' . $mysqli->error], 500);
}

// 2. 綁定參數 (i: integer, s: string)
//    - parent_id 和 image_filename 如果是 null，bind_param 會自動處理
$stmt_insert->bind_param("iisss", $user_id, $recipe_id, $parent_id, $content, $image_filename);

// 3. 執行
if ($stmt_insert->execute()) {
    if ($stmt_insert->affected_rows > 0) {
        $new_comment_id = $stmt_insert->insert_id;
        $stmt_insert->close(); // 完成插入後即可關閉

        // --- 主要修改區域 2: 使用預備陳述式反查新留言 ---
        
        $sql_select = "
            SELECT rc.*, u.name as member_name, u.image as member_avatar 
            FROM recipe_comment rc 
            JOIN users u ON rc.user_id = u.user_id
            WHERE rc.comment_id = ?";
        
        $stmt_select = $mysqli->prepare($sql_select);
        $stmt_select->bind_param('i', $new_comment_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        $new_comment_data = $result->fetch_assoc();
        $stmt_select->close();

        // --- 主要修改區域 3: 動態產生圖片的完整 URL ---
        if ($new_comment_data) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $base_url = "{$protocol}://{$host}";
            $uploads_path = '/uploads/';

            if (!empty($new_comment_data['member_avatar']) && !filter_var($new_comment_data['member_avatar'], FILTER_VALIDATE_URL)) {
                $new_comment_data['member_avatar'] = $base_url . $uploads_path . $new_comment_data['member_avatar'];
            }
            if (!empty($new_comment_data['image']) && !filter_var($new_comment_data['image'], FILTER_VALIDATE_URL)) {
                $new_comment_data['image'] = $base_url . $uploads_path . $new_comment_data['image'];
            }
        }

        send_json(['status' => 'success', 'data' => $new_comment_data], 201);

    } else {
        $stmt_insert->close();
        send_json(['status' => 'error', 'message' => '留言發布失敗，資料未寫入'], 500);
    }
} else {
    $error_message = $stmt_insert->error;
    $stmt_insert->close();
    send_json(['status' => 'error', 'message' => '留言發布失敗', 'db_error' => $error_message], 500);
}

$mysqli->close();
?>