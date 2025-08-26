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
$sql = "SELECT user_id, name, image FROM users WHERE user_id = ? LIMIT 1";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_json(['status' => 'error', 'message' => '資料庫查詢失敗: ' . $mysqli->error], 500);
    exit;
}
        
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    // 【關鍵優化】自動拼接圖片的完整 URL
    $base_url = 'http://localhost:8888'; // 您的後端伺服器網址
    $avatar_path = '/uploads/'; // 您的圖片上傳資料夾


    $avatar_url = $default_avatar;
    if (!empty($user['image'])) {
        // 如果資料庫中的 image 欄位有值，就使用它
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