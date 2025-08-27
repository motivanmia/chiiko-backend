<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';

header('Content-Type: application/json');

global $mysqli;

try {
    // 驗證 GET 參數：user_id 與 product_id
    if (!isset($_GET['user_id']) || !isset($_GET['product_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'isFavorited' => false, 'message' => '缺少必要參數']);
        exit(); 
    }

    // 避免 SQL 注入
    $userId = $mysqli->real_escape_string($_GET['user_id']);
    $productId = $mysqli->real_escape_string($_GET['product_id']);

    // 查詢 wishlist 表，判斷是否已收藏
    $sql = "SELECT COUNT(*) FROM `wishlist` WHERE `user_id` = '{$userId}' AND `product_id` = '{$productId}'";

    $result = mysqli_query($mysqli, $sql);

    if ($result) {
        $row = mysqli_fetch_row($result);
        mysqli_free_result($result);

        // 判斷是否已收藏
        $isFavorited = ($row[0] > 0);

        echo json_encode(['success' => true, 'isFavorited' => $isFavorited]);
    } else {
        throw new \mysqli_sql_exception('查詢失敗: ' . mysqli_error($mysqli));
    }

} catch (\mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'isFavorited' => false,
        'message' => '資料庫操作失敗',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($mysqli)) {
        mysqli_close($mysqli);
    }
}
?>
