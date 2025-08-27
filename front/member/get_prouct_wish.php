<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost:5174"); // 指定允許的前端來源
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true"); // 如果要帶 cookie/session

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // ✅ GET 請求：取得收藏清單
    $user_id = $_GET['user_id'] ?? 0;

    if ($user_id <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '請先登入會員']);
        exit;
    }

    try {
        $escaped_user_id = $mysqli->real_escape_string($user_id);

        $result = $mysqli->query("
    SELECT w.wishlist_id, w.product_id, p.name AS product_name, p.unit_price AS price
    FROM wishlist w
    JOIN products p ON w.product_id = p.product_id
    WHERE w.user_id = '$escaped_user_id'
");


        $wishList = [];
        while ($row = $result->fetch_assoc()) {
            $wishList[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $wishList]);

    } catch (\mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => '資料庫查詢失敗',
            'message' => $e->getMessage()
        ]);
    }

} elseif ($method === 'POST') {
    // ✅ POST 請求：新增或刪除收藏
    $input = json_decode(file_get_contents('php://input'), true);

    $user_id = $input['user_id'] ?? 0;
    $product_id = $input['product_id'] ?? 0;
    $action = $input['action'] ?? '';

    if ($user_id <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '請先登入會員']);
        exit;
    }

    if (!$product_id || !in_array($action, ['add', 'delete'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '參數錯誤']);
        exit;
    }

    try {
        $user_id = $mysqli->real_escape_string($user_id);
        $product_id = $mysqli->real_escape_string($product_id);

        if ($action === 'add') {
            $mysqli->query("INSERT IGNORE INTO wishlist (user_id, product_id, created_at) VALUES ('$user_id', '$product_id', NOW())");
        } else { // delete
            $mysqli->query("DELETE FROM wishlist WHERE user_id='$user_id' AND product_id='$product_id'");
        }

        echo json_encode(['success' => true, 'action' => $action]);

    } catch (\mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => '資料庫操作失敗',
            'message' => $e->getMessage()
        ]);
    }

} else {
    // ✅ 其他 HTTP 方法
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}
