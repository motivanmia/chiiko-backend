<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('PATCH');

  // 後台登入檢查
  if (!isset($_SESSION['manager_id'])) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入後台'
    ], 401);
  }

  // 讀取 raw JSON
  $input = get_json_input();

  // 必填欄位：order_id, order_status_text
  $order_id = isset($input['order_id']) ? intval($input['order_id']) : null;
  $order_status_text = isset($input['order_status']) ? trim($input['order_status']) : null;

  if (!$order_id || !$order_status_text) {
    send_json(['status' => 'fail', 'message' => '請提供 order_id 與 order_status'], 400);
  }

  // 中文 → key 對照表
  $status_map = [
    '待確認'    => 0,
    '已出貨'    => 1,
    '已完成'    => 2,
    '取消/退貨' => 3
  ];

  if (!isset($status_map[$order_status_text])) {
    send_json(['status' => 'fail', 'message' => 'order_status 錯誤'], 400);
  }

  $order_status = $status_map[$order_status_text];

  // 查詢舊狀態 & user_id
  $sql = "SELECT order_status, user_id FROM orders WHERE order_id = {$order_id} LIMIT 1";
  $res = db_query($mysqli, $sql);
  $row = $res->fetch_assoc();
  if (!$row) {
    send_json(['status' => 'fail', 'message' => '找不到該訂單'], 404);
  }
  $old_status = (int)$row['order_status'];
  $user_id    = (int)$row['user_id'];

  // 更新訂單
  $sql = sprintf(
    "UPDATE orders SET order_status = %d WHERE order_id = %d",
    $order_status,
    $order_id
  );

  db_query($mysqli, $sql);
  
  // 建立通知（只有在狀態轉換時才通知）
  notify_order_on_status_change($mysqli, $old_status, $order_status, $order_id, $user_id);

  send_json([
    'status' => 'success',
    'message' => '訂單狀態更新成功',
    'data' => [
      'order_id' => $order_id,
      'order_status' => $order_status,
      'order_status_text' => $order_status_text
    ]
]);
?>
