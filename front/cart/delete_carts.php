<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('DELETE');

  // 檢查是否登入
  $user = checkUserLoggedIn();

  if (!$user) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入'
    ], 401);
  }

  $user_id = $user['user_id'];

  // 刪除該使用者所有購物車商品
  $sql = sprintf(
    "DELETE FROM carts WHERE user_id = %d",
    $user_id
  );

  db_query($mysqli, $sql);

  if ($mysqli->affected_rows > 0) {
    send_json([
      'status' => 'success',
      'message' => '購物車已清空'
    ]);
  } else {
    send_json([
      'status' => 'fail',
      'message' => '找不到該使用者的購物車資料'
    ], 404);
  }
?>
