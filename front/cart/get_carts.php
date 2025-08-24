<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 只允許使用 GET
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

  // SQL 查詢
  $sql = sprintf("SELECT 
      c.product_id,
      p.name AS product_name,
      p.unit_price,
      p.preview_image,
      c.quantity,
      c.created_at
    FROM carts AS c
    JOIN users AS u ON c.user_id = u.user_id
    JOIN products AS p ON c.product_id = p.product_id
    WHERE c.user_id = {$user_id}
    ORDER BY c.created_at DESC",
    $user_id
  );

  // 取得資料
  $result = db_query($mysqli, $sql);
  $data = $result->fetch_all(MYSQLI_ASSOC);
  
  // 成功回應
  send_json([
    'status' => 'success',
    'message' => '資料取得成功',
    'data' => $data
  ]);

?>
