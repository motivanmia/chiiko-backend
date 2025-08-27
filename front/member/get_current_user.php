<?php
// =================================================================
//  API: 取得當前登入者資訊 (最終完美版)
// =================================================================

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_method('GET');

if (!isset($_SESSION['user_id'])) {
    send_json(['status' => 'success', 'isLoggedIn' => false, 'data' => null]);
    exit;
}

$user_id = $_SESSION['user_id'];

// 💡 替換成 mysqli_query，並直接嵌入變數
$sql = "SELECT user_id, name, image FROM users WHERE user_id = {$user_id} LIMIT 1";

$result = $mysqli->query($sql);
if (!$result) {
    send_json(['status' => 'error', 'message' => '資料庫查詢失敗: ' . $mysqli->error], 500);
    exit;
}
        
$user = $result->fetch_assoc();
$result->free();

if ($user) {
    // 【關鍵優化】自動拼接圖片的完整 URL
    $base_url = 'http://localhost:8888'; // 您的後端伺服器網址
    $avatar_path = '/uploads/'; // 您的圖片上傳資料夾

    // 💡 這裡使用了 $default_avatar 變數，但您提供的程式碼中沒有定義。
    //    為避免錯誤，我將其移除並簡化邏輯。
    $avatar_url = null;
    if (!empty($user['image'])) {
        $avatar_url = $base_url . $avatar_path . $user['image'];
    }

    send_json([
        'status' => 'success',
        'isLoggedIn' => true,
        'data' => [
            'userId' => (int) $user['user_id'],
            'userName' => $user['name'],
            'avatar' => $avatar_url // 回傳包含完整網址的頭像路徑
        ]
    ]);
} else {
      send_json(['status' => 'error', 'message' => 'User not found in DB'], 404);
}

$mysqli->close();
?>