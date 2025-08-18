<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 只允許使用 GET
  require_method('GET');

  // 取得哪個使用者
  $user_id = get_int_param('user_id');

  // SQL 查詢
  $sql = "SELECT 
      user_id,
      name, 
      nikname,
      phone,
      account,
      address,
      image
      FROM users";

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
