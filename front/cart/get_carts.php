<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 只允許使用 GET
  require_method('GET');

  // 取得哪個使用者
  $user_id = get_int_param('user_id');

  // 如果沒有 user_id，就直接回傳錯誤
  if (!$user_id) {
    send_json([
      'status' => 'fail',
      'message' => '請提供 user_id'
    ], 400);
  }

  $where = "WHERE c.user_id = {$user_id}";

  // SQL 查詢
  $sql = "SELECT 
      c.user_id,
      u.name,
      u.nikname,
      u.email,
      u.phone,
      u.address,
      c.product_id,
      p.name AS product_name,
      p.unit_price,
      p.preview_image,
      c.quantity,
      c.created_at
    FROM carts AS c
    JOIN users AS u ON c.user_id = u.user_id
    JOIN products AS p ON c.product_id = p.product_id
    {$where}
    ORDER BY c.created_at DESC
  ";

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
