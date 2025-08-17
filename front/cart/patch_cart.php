<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 只允許 PATCH
  require_method('PATCH');

  $input = get_json_input();

  // 取得必要欄位
  $product_id = isset($input['product_id']) ? intval($input['product_id']) : null;
  $user_id = isset($input['user_id']) ? intval($input['user_id']) : null;
  $quantity = isset($input['quantity']) ? intval($input['quantity']) : null;

  // 檢查必填欄位
  if (!$product_id || !$user_id || !$quantity) {
    send_json([
      'status' => 'fail',
      'message' => '請輸入必填選項!'
    ], 400);
  }

  // 先確認該商品是否存在購物車
  $check_sql = sprintf(
    "SELECT quantity FROM carts WHERE user_id = %d AND product_id = %d",
    $user_id,
    $product_id
  );
  $result = db_query($mysqli, $check_sql);
  $existing = $result->fetch_assoc();

  if (!$existing) {
    send_json([
      'status' => 'fail',
      'message' => '此商品不存在購物車中'
    ], 404);
  }

  // 更新購物車數量
  $update_sql = sprintf(
    "UPDATE carts SET quantity = %d WHERE user_id = %d AND product_id = %d",
    $quantity,
    $user_id,
    $product_id
  );
  db_query($mysqli, $update_sql);

  if ($mysqli->affected_rows > 0) {
    send_json([
      'status' => 'success',
      'message' => '購物車更新成功'
    ]);
  } else {
    send_json([
      'status' => 'fail',
      'message' => '找不到該商品或使用者的購物車資料'
    ], 404);
  }
?>
