<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('POST');

  // 檢查是否登入
  $user = checkUserLoggedIn();
  
  if (!$user) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入'
    ], 401);
  }

  $user_id = $user['user_id'];

  // 讀取 raw JSON
  $input = get_json_input();

  $product_id = isset($input['product_id']) ? intval($input['product_id']) : null;
  $quantity = isset($input['quantity']) ? intval($input['quantity']) : null;

  // 檢查必填欄位
  if (!$product_id || !$quantity) {
    send_json([
      'status' => 'fail',
      'message' => '請輸入必填選項!'
    ], 400);
  }

  // 檢查商品是否存在
  $check_product = $mysqli->query("SELECT product_id FROM products WHERE product_id = $product_id");
  if ($check_product->num_rows === 0) {
      send_json([
          'status' => 'fail',
          'message' => '商品不存在'
      ], 400);
  }

  // 檢查購物車是否已有此商品
  $check_sql = sprintf(
    "SELECT quantity FROM carts WHERE user_id = %d AND product_id = %d",
    $user_id,
    $product_id
  );

  $result = db_query($mysqli, $check_sql);
  $existing = $result->fetch_assoc();

  if ($existing) {
    // 已有商品 → 累加數量
    $new_quantity = $existing['quantity'] + $quantity;
    $update_sql = sprintf(
      "UPDATE carts SET quantity = %d WHERE user_id = %d AND product_id = %d",
      $new_quantity,
      $user_id,
      $product_id
    );
    db_query($mysqli, $update_sql);
  } else {
    // 沒有商品 → 新增
    $insert_sql = sprintf(
      "INSERT INTO carts (product_id, user_id, quantity) VALUES (%d, %d, %d)",
      $product_id,
      $user_id,
      $quantity
    );
    db_query($mysqli, $insert_sql);
  }
  
  // 回傳成功訊息
  send_json([
    'status' => 'success',
    'message' => '購物車新增成功'
  ]);
?>
