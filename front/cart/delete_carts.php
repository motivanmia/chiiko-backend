<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('DELETE');

  // 取得 JSON 輸入
  $input = get_json_input();
  $user_id = isset($input['user_id']) ? intval($input['user_id']) : null;

  if (!$user_id) {
    send_json([
      'status' => 'fail',
      'message' => '請提供 user_id'
    ], 400);
  }

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
