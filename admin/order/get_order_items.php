<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');

  // 後台登入檢查
  if (!isset($_SESSION['manager_id'])) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入後台'
    ], 401);
  }

  // 取得 order_id
  $order_id = get_int_param('order_id');
  if (!$order_id) {
    send_json([
      'status' => 'fail',
      'message' => '請提供 order_id'
    ], 400);
  }

  // 先取得訂單資訊
  $sql_order = sprintf(
    "SELECT order_id, user_id, created_at, final_price, recipient, recipient_phone, shopping_address, order_status, freight, tracking_number
    FROM orders
    WHERE order_id = %d",
    $order_id
  );

  $result_order = db_query($mysqli, $sql_order);
  $order = $result_order->fetch_assoc();

  
  if (!$order) {
    send_json([
      'status' => 'fail',
      'message' => '訂單不存在'
    ], 404);
  }

  // 取得訂單商品
  $sql_items = sprintf(
    "SELECT oi.product_id, oi.name AS product_name, oi.quantity, oi.unit_price
    FROM order_item oi
    WHERE oi.order_id = %d",
    $order_id
  );

  $result_items = db_query($mysqli, $sql_items);
  $items = $result_items->fetch_all(MYSQLI_ASSOC);

  
  // 計算每個商品小計
  foreach ($items as &$item) {
    $item['subtotal'] = $item['quantity'] * $item['unit_price'];
  }

  // 狀態對應表（0: 待確認 1: 已出貨 2: 已完成 3: 取消/退貨）
  $status_map = [
    0 => '待確認',
    1 => '已出貨',
    2 => '已完成',
    3 => '取消/退貨'
  ];
  $order['order_status_text'] = $status_map[$order['order_status']] ?? '未知狀態';
  
// 回傳結果
  send_json([
    'status' => 'success',
    'message' => '取得訂單明細成功',
    'data' => [
      'order_id'         => $order['order_id'],
      'user_id'          => $order['user_id'],
      'created_at'       => $order['created_at'],
      'final_price'      => $order['final_price'],
      'freight'          => $order['freight'],
      'order_status'     => $order['order_status'],
      'order_status_text'=> $order['order_status_text'],
      'recipient'        => $order['recipient'],
      'recipient_phone'  => $order['recipient_phone'],
      'shopping_address' => $order['shopping_address'],
      'tracking_number'  => $order['tracking_number'],
      'items'            => $items
    ]
  ]);

?>
