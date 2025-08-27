<?php

session_start();
header("Access-Control-Allow-Origin: *"); // 允許所有來源，開發用
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    // 取得登入會員 ID
    $user_id = $_SESSION['user_id'] ?? 0;

    if ($user_id <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '請先登入會員']);
        exit;
    }

    // 取得 DELETE 請求的 JSON body
    $data = json_decode(file_get_contents("php://input"), true);
    $wishlist_id = $data['wishlist_id'] ?? 0; // 要刪除的收藏 ID

    if ($wishlist_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少有效的收藏編號']);
        exit;
    }

    try {
        // 避免 SQL 注入
        $escaped_user_id = $mysqli->real_escape_string($user_id);
        $escaped_wishlist_id = $mysqli->real_escape_string($wishlist_id);

        // 刪除 wishlist 資料
        $query = "
            DELETE FROM `wishlist`
            WHERE `wishlist_id` = '$escaped_wishlist_id'
            AND `user_id` = '$escaped_user_id'
        ";

        $result = $mysqli->query($query);

        if (!$result) {
            throw new \mysqli_sql_exception('刪除失敗: ' . $mysqli->error);
        }

        // 回傳成功訊息
        echo json_encode(['success' => true, 'message' => '刪除成功']);

    } catch (\mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '刪除失敗', 'message' => $e->getMessage()]);
    }

} else {
    // 非 DELETE 方法回傳 405
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}
?>
