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

  // 檢查所需的欄位是否存在
  if (empty($input['current_pwd']) || empty($input['new_pwd'])) {
    send_json([
      'status' => 'fail',
      'message' => '請提供舊密碼和新密碼'
    ], 400);
    exit();
  }

  $current_pwd = $input['current_pwd'];
  $new_pwd = $input['new_pwd'];

  // 確保新舊密碼不相同
  if ($current_pwd === $new_pwd) {
    send_json([
      'status' => 'fail',
      'message' => '新密碼不能與舊密碼相同'
    ], 400);
    exit();
  }

  // 從資料庫獲取使用者目前的密碼
  $sql = "SELECT password FROM users WHERE user_id = " . $user_id;
  $result = db_query($mysqli, $sql);
  
  if ($result->num_rows === 0) {
    send_json([
      'status' => 'fail',
      'message' => '找不到使用者'
    ], 404);
    $result->free();
    exit();
  }

  $user = $result->fetch_assoc();
  $stored_hash = $user['password'];
  $result->free();

  // 驗證舊密碼是否正確
  if (!password_verify($current_pwd, $stored_hash)) {
    send_json([
      'status' => 'fail',
      'message' => '舊密碼不正確'
    ], 401);
    exit();
  }
  
  // 新密碼處理
  $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);

  // 更新密碼
  $update_sql = "UPDATE users SET password = '$new_hash' WHERE user_id = $user_id";

  if ($mysqli->query($update_sql) === TRUE) {
    send_json([
      'status' => 'success',
      'message' => '密碼更新成功'
    ], 200);
  } else {
    send_json([
      'status' => 'fail',
      'message' => '密碼更新失敗' . $mysqli->error
    ], 500);
  }

  $mysqli->close();
?>