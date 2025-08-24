<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');
  
  if (!isset($_SESSION['manager_id'])) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入後台'
    ], 401);
  }

  $sql = "SELECT 
    o.order_id,
    o.user_id,
    o.created_at,
    o.final_price,
    o.order_status
  FROM orders o
  ORDER BY o.created_at DESC";

  $result = db_query($mysqli, $sql);
  $orders = $result->fetch_all(MYSQLI_ASSOC);

  // 狀態對應表
  $order_status_map = [
    0 => '待確認',
    1 => '已出貨',
    2 => '已完成',
    3 => '取消/退貨'
  ];

  // 把每一筆訂單的數字轉換成文字
  foreach ($orders as &$order) {
    $order['order_status_text'] = $order_status_map[$order['order_status']] ?? '未知狀態';
  }

  
  send_json([
    'status' => 'success',
    'message' => '取得訂單列表成功',
    'data' => $orders
  ]);
?>
