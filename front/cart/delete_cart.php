<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 只允許 DELETE
  $input = get_json_input();

  // 檢查是否登入
  $user = checkUserLoggedIn();

  if (!$user) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入'
    ], 401);
  }

  $user_id = $user['user_id'];

  // 取得必填欄位
  $product_id = isset($input['product_id']) ? intval($input['product_id']) : null;

  // 檢查欄位
  if (!$product_id) {
    send_json([
      'status' => 'fail',
      'message' => '請提供 user_id 與 product_id'
    ], 400);
  }

  // 刪除購物車
  $delete_sql = sprintf(
    "DELETE FROM carts WHERE user_id = %d AND product_id = %d",
    $user_id,
    $product_id
  );

  db_query($mysqli, $delete_sql);

  // 回傳成功訊息
  if ($mysqli->affected_rows > 0) {
    send_json([
      'status' => 'success',
      'message' => '購物車商品已刪除'
    ]);
  } else {
    send_json([
      'status' => 'fail',
      'message' => '找不到該商品或使用者的購物車資料'
    ], 404);
  }
?>
