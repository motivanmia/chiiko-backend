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

  // 從session中取得當前登入的使用者id
  $user_id = $user['user_id'];

  // SQL 查詢
  $sql = "SELECT 
      user_id,
      name, 
      nickname,
      phone,
      account,
      address,
      image
    FROM users
    WHERE user_id = {$user_id}";

  // 取得資料
  $result = db_query($mysqli, $sql);

  if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    // if (!empty($data['image'])) {
    //   $data['image'] = IMG_BASE_URL  .'/'. $data['image'];
    // } else {
    //   $data['image'] = null;
    // }
    // 成功回應
    send_json([
      'status' => 'success',
      'message' => '資料取得成功',
      'data' => $data
    ]);
  } else {
    // 找不到資料
    send_json([
      'status' => 'fail',
      'message' => '找不到使用者資料'
    ], 404);
  }

  $stmt->close();
?>
