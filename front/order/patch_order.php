<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('PATCH');

  // 檢查是否登入
  $user = checkUserLoggedIn();

  if (!$user) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入'
    ], 401);
  }

  $user_id = $user['user_id'];

  $input = get_json_input();

  $order_id = intval($input['order_id'] ?? 0);
  $order_status_value = $mysqli->real_escape_string($input['order_status'] ?? '');

  if (!$order_id || $order_status_value === '') {
    send_json(['status' => 'fail', 'message' => '缺少必要欄位'], 400);
  }

  // 狀態映射表
  $status_map = [
    "待確認" => 0,
    "已出貨" => 1,
    "已完成" => 2,
    "取消/退貨" => 3
  ];

  // 驗證傳入的狀態是否存在
  if (!array_key_exists($order_status_value, $status_map)) {
    send_json(['status' => 'fail', 'message' => '不合法的訂單狀態'], 400);
  }

  $order_status_key = $status_map[$order_status_value];

  // 確認訂單屬於該用戶
  $sql_check = "SELECT order_id FROM orders WHERE order_id = {$order_id} AND user_id = {$user_id}";
  
  $result = db_query($mysqli, $sql_check);

  if ($result->num_rows === 0) {
    send_json(['status' => 'fail', 'message' => '訂單不存在或無權限修改'], 403);
  }

  // 更新訂單狀態
  $sql_update = "UPDATE orders SET order_status = {$order_status_key} WHERE order_id = {$order_id}";
  db_query($mysqli, $sql_update);

  create_notification($mysqli, [
    'receiver_id' => $user_id,
    'order_id'    => $order_id,
    'type'        => 22,
    'title'       => '訂單已取消',
    'content'     => "您的訂單 #{$order_id} 已取消，如有疑問請聯繫客服。"
  ]);

  send_json([
    'status' => 'success',
    'message' => '訂單狀態已更新',
    'data' => [
      'order_id' => $order_id,
      'order_status' => $order_status_key
    ]
  ]);
?>
