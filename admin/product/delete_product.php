<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('POST'); // 確保是 POST 請求

// 解析 JSON
$input = json_decode(file_get_contents('php://input'), true);
$product_id = $input['product_id'] ?? null;

write_log($product_id);
if (!$product_id) {
    send_json([
        "status" => "error",
        "message" => "缺少商品 ID"
    ]);
    exit;
}

// 刪除 SQL
$sql = "DELETE FROM products WHERE product_id = ?";
$stmt = $mysqli->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $product_id); // "s" 表示字串，支援中文英文數字
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        send_json([
            "status" => "success",
            "message" => "刪除成功"
        ]);
    } else {
        send_json([
            "status" => "error",
            "message" => "找不到這筆資料"
        ]);
    }

    $stmt->close();
} else {
    send_json([
        "status" => "error",
        "message" => "SQL 執行失敗"
    ]);
}

$mysqli->close();
?>
