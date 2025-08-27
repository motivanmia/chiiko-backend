<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('PATCH');

// 取得前端傳來的 JSON 資料
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

// 驗證必要資料
if (!isset($data['product_id']) || !isset($data['is_active'])) {
    send_json(['error' => "缺少必要欄位: product_id 或 is_active"], 400);
}

$product_id = intval($data['product_id']);
$is_active = intval($data['is_active']);

// 更新資料庫
$sql = "UPDATE products SET is_active = ? WHERE product_id = ?";
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    send_json(['error' => '資料庫預處理失敗: ' . $mysqli->error], 500);
}

$stmt->bind_param("ii", $is_active, $product_id);

if ($stmt->execute()) {
    send_json([
        'status' => 'success',
        'message' => '商品狀態更新成功',
    ], 200);
} else {
    send_json(['error' => '資料庫更新失敗: ' . $stmt->error], 500);
}


$stmt->close();
$mysqli->close();
?>