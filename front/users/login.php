<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';
  //
  require_method('POST');
  //
  //獲取前端 JSON 資料
  $data = json_decode(file_get_contents('php://input'), true);
  //
  $account = $data['account'] ?? '';
  $password = $data['password'] ?? '';
  //
  // 必填欄位檢查
  if (empty($account) || empty($password)) {
    http_response_code(400);
    echo json_encode(['message' => '請輸入帳號及密碼']);
    exit();
  }
  //
  try {
    $safe_account = mysqli_real_escape_string($mysqli, $account);
    //
    $sql = "SELECT `user_id`, `name`, `password`, `status` FROM `users` WHERE `account` = '{$safe_account}'";
    //
    $result = mysqli_query($mysqli, $sql);
    //
    // 檢查查詢是否成功
    if (!$result) {
      throw new Exception("SQL 查詢失敗: " . mysqli_error($mysqli));
    }
    //
    if (mysqli_num_rows($result) === 1) {
      $user = mysqli_fetch_assoc($result);
      // 驗證密碼
      if (password_verify($password, $user['password'])) {
        // 會員狀態檢查
        if ($user['status'] == 1) {
          http_response_code(403); 
          echo json_encode([
            'status' => 'fail',
            'message' => '此帳號已被停權，請聯繫客服。'
          ]);
          // 
          mysqli_free_result($result);
          mysqli_close($mysqli);
          exit();
        }
        // 登入成功
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['is_logged_in'] = true;
        http_response_code(200);
        echo json_encode([
          'message' => '登入成功！',
          'user' => [
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'account' => $account,
          ]
        ]);
      } else {
        // 密碼錯誤
        http_response_code(401); 
        echo json_encode(['message' => '帳號或密碼不正確。']);
      }
    } else {
      // 帳號不存在
      http_response_code(401); 
      echo json_encode(['message' => '帳號或密碼不正確。']);
    }
    //
    mysqli_free_result($result);
    //
    mysqli_close($mysqli);
    //
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => '伺服器內部錯誤，請稍後再試。']);
    // error_log("SQL 錯誤: " . $e->getMessage());//可以記錄錯誤
    //
    // 在發生錯誤時，確保連線關閉
    if (isset($mysqli)) {
      mysqli_close($mysqli);
    }
  }
?>