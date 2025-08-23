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

  // 撈訂單 + 第一個商品 + 商品數量
  $sql = sprintf(
    "SELECT 
      o.order_id,
      o.created_at,
      o.order_status,
      o.payment_status,
      o.final_price,
      o.tracking_number,
      (
        SELECT p.preview_image 
        FROM order_item oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = o.order_id
        ORDER BY oi.created_at ASC
        LIMIT 1
      ) AS first_preview_image,
      (
        SELECT oi.name 
        FROM order_item oi
        WHERE oi.order_id = o.order_id
        ORDER BY oi.created_at ASC
        LIMIT 1
      ) AS first_product_name,
      (
        SELECT SUM(oi.quantity) 
        FROM order_item oi
        WHERE oi.order_id = o.order_id
      ) AS total_items
    FROM orders o
    WHERE o.user_id = %d
    ORDER BY o.created_at DESC",
    $user_id
  );

  $result = db_query($mysqli, $sql);
  $orders = $result->fetch_all(MYSQLI_ASSOC);

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

  // 把數字轉換成文字（避免找不到 key 時報錯）
// 把每一筆訂單的數字轉換成文字
foreach ($orders as &$order) {
  $order['order_status_text'] = $order_status_map[$order['order_status']] ?? '未知狀態';
  $order['payment_status_text'] = $payment_status_map[$order['payment_status']] ?? '未知狀態';
}


  send_json([
    'status' => 'success',
    'message' => '取得訂單列表成功',
    'data' => $orders
  ]);
?>
