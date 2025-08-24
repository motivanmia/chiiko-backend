<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');

  // 檢查是否登入
  $user = checkUserLoggedIn();

  if (!$user) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入'
    ], 401);
  }

  $user_id = $user['user_id'];
  
  $order_id = get_int_param('order_id');

  if (!$order_id) {
    send_json([
      'status' => 'fail',
      'message' => '請提供 order_id'
    ], 400);
  }

  // 先檢查這筆訂單是否屬於該 user
  $check_sql = sprintf(
    "SELECT 
      order_id, 
      recipient, 
      recipient_phone, 
      shopping_address, 
      order_status, 
      payment_status, 
      total_price, 
      freight, 
      final_price, 
      payment_type,
      tracking_number,
      created_at
    FROM orders 
    WHERE order_id = %d AND user_id = %d",
    $order_id,
    $user_id
  );

  $check_result = db_query($mysqli, $check_sql);
  
  if ($check_result->num_rows === 0) {
    send_json([
      'status' => 'fail',
      'message' => '無權限查看此訂單或訂單不存在'
    ], 403);
  }

  
  // 取得訂單資訊
  $order = $check_result->fetch_assoc();

  // 取得訂單商品明細
  $sql_items = sprintf(
    "SELECT 
      oi.order_id,
      oi.product_id,
      oi.name AS product_name,
      oi.quantity,
      oi.unit_price,
      (oi.quantity * oi.unit_price) AS subtotal,
      p.preview_image
    FROM order_item AS oi
    JOIN products AS p ON oi.product_id = p.product_id
    WHERE oi.order_id = %d",
    $order_id
  );

  $result = db_query($mysqli, $sql_items);
  $order_items = $result->fetch_all(MYSQLI_ASSOC);

  // 狀態對應表
  $order_status_map = [
    0 => '待確認',
    1 => '已出貨',
    2 => '已完成',
    3 => '取消/退貨'
  ];

  $payment_status_map = [
    0 => '未付款',
    1 => '已付款'
  ];

  $payment_type_map = [
    0 => '信用卡',
    1 => '貨到付款'
  ];

  // 把數字轉換成文字（避免找不到 key 時報錯）
  $order['order_status_text'] = $order_status_map[$order['order_status']] ?? '未知狀態';
  $order['payment_status_text'] = $payment_status_map[$order['payment_status']] ?? '未知狀態';
  $order['payment_type_text'] = $payment_type_map[$order['payment_type']] ?? '未知付款方式';

  // 回傳結果
  send_json([
    'status' => 'success',
    'message' => '取得訂單明細成功',
    'data' => [
      'order' => [
        'order_id' => $order['order_id'],
        'recipient' => $order['recipient'],
        'recipient_phone' => $order['recipient_phone'],
        'shopping_address' => $order['shopping_address'],
        'order_status' => $order['order_status'],
        'order_status_text' => $order['order_status_text'], 
        'payment_status' => $order['payment_status'],
        'payment_status_text' => $order['payment_status_text'],
        'payment_type' => $order['payment_type'],
        'payment_type_text' => $order['payment_type_text'],
        'total_price' => $order['total_price'],
        'freight' => $order['freight'],
        'final_price' => $order['final_price'],
        'tracking_number' => $order['tracking_number'],
        'created_at' => $order['created_at']
      ],
      'items' => $order_items
    ]
  ]);

  // $sql = sprintf(
  //   "SELECT 
  //     oi.order_id,
  //     oi.product_id,
  //     oi.name AS order_item_name,   -- 下單時商品名稱
  //     oi.quantity,
  //     oi.unit_price AS order_item_price, -- 下單時單價
  //     oi.created_at,
  //     p.product_category_id,
  //     p.preview_image,
  //     p.is_active
  //   FROM order_item AS oi
  //   JOIN products AS p ON oi.product_id = p.product_id
  //   WHERE oi.order_id = %d",
  //   $order_id
  // );

  // $result = db_query($mysqli, $sql);
  // $data = $result->fetch_all(MYSQLI_ASSOC);

  // send_json([
  //   'status' => 'success',
  //   'message' => '取得訂單明細成功',
  //   'data' => $data
  // ]);

?>
