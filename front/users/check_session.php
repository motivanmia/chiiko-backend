<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 先給 $response 一個預設值，避免未定義錯誤
  $response = [
    'is_logged_in' => false
  ];

  
  // 檢查 Session 中是否有 'is_logged_in' 且為 true
  if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
      // 如果已登入，回傳使用者資料
      $response = [
          'is_logged_in' => true,
          'user_id' => $_SESSION['user_id'],
          'user_name' => $_SESSION['user_name']
      ];
  } else {
      // 如果未登入，回傳未登入狀態
      $response = [
          'is_logged_in' => false,
      ];
  }
  
  // 如果未登入，回傳 false
  // return false;
  

  // 設定 Content-Type 標頭為 JSON，讓前端知道這是 JSON 資料
  header('Content-Type: application/json');

  // 將 $response 陣列轉換為 JSON 字串並輸出給前端
  echo json_encode($response);
?>