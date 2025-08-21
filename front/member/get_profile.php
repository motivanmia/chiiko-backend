<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 只允許使用 GET
  require_method('GET');

  // 檢查session中是否有使用者id 如果沒有表示未登入
  if(!isset($_SESSION['user_id'])){
    // 傳送未授權回應 並終止程式
    http_response_code(401);
    send_json([
      'status' => 'error',
      'message' => '未授權，請先登入'
    ]);
    exit;
  }

  // 從session中取得當前登入的使用者id
  $current_user_id = $_SESSION['user_id'];

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
    WHERE user_id = ?";

  $stmt = $mysqli -> prepare($sql);
  $stmt -> bind_param('i', $current_user_id);
  $stmt -> execute();
  $result = $stmt -> get_result();

  if ($result -> num_rows > 0){
    // 成功取得資料，只會有一筆
    $data = $result -> fetch_assoc();
    // 成功回應
    send_json([
      'status' => 'success',
      'message' => '資料取得成功',
      'data' => $data
    ]);
  }else{
    // 找不到使用者資料
    send_json([
      'status' => 'error',
      'message' => '找不到使用者資料'
    ]);
  }

  // 關閉語法與連線
  $stmt->close();
  $mysqli->close();

?>
