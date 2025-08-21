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

  // 從session中取得當前登入的使用者id
  $user_id = $user['user_id'];

  $input = get_json_input();

  if (empty($input)) {
    send_json(['status' => 'fail', 'message' => '請求內容不能為空'], 400);
  }

  // 如果name欄位是空值就回傳錯誤
  if (isset($input['name']) && empty(trim($input['name']))) {
    send_json([
      'status' => 'fail',
      'message' => '姓名欄位不能為空'
    ], 400);
  }

  // 允許修改的欄位清單
  $allowed_fields = ['name', 'nickname', 'phone', 'address'];
  $set_parts = [];

  // 檢查資料庫連線變數是否存在
  if (!isset($mysqli) || !$mysqli) {
    send_json([
      'status' => 'fail',
      'message' => '資料庫連線失敗'
    ], 500);
  }

  foreach ($input as $key => $value) {
    if (in_array($key, $allowed_fields)) {
      // 使用 mysqli_real_escape_string() 手動轉義
      $escaped_key = $mysqli->real_escape_string($key);
      $escaped_value = $mysqli->real_escape_string($value);
      $set_parts[] = "`{$escaped_key}` = '{$escaped_value}'";
    }
  }

  if (empty($set_parts)) {
    send_json([
      'status' => 'fail', 
      'message' => '沒有提供有效的更新欄位'
    ], 400);
  }

  $escaped_user_id = $mysqli->real_escape_string($user_id);
  $sql = "UPDATE users SET " . implode(', ', $set_parts) . " WHERE user_id = '{$escaped_user_id}'";

  $result = db_query($mysqli, $sql);

  // 檢查更新是否成功
  if ($mysqli->affected_rows > 0) {
    send_json([
      'status' => 'success', 
      'message' => '會員資料更新成功'
    ], 200);
  } else {
    send_json([
      'status' => 'fail', 
      'message' => '沒有任何資料被更新'
    ], 200);
  }
?>