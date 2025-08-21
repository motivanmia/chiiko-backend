<?php
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 設定 Content-Type 標頭為 JSON，讓前端知道這是 JSON 資料
  header('Content-Type: application/json');

  // 檢查 Session 中是否存在 'manager_id'
  // 這是判斷使用者是否登入最可靠的方式
  if (isset($_SESSION['manager_id'])) {
      // 如果已登入，回傳使用者資料
      // ✅ 確保回傳的鍵名 (key) 與前端 Pinia Store 預期的 'user.name' 一致
      $response = [
          'is_logged_in' => true,
          'user' => [
              'manager_id' => $_SESSION['manager_id'],
              'name' => $_SESSION['name'],
              'role' => $_SESSION['role'],
          ]
      ];
  } else {
      // 如果未登入，回傳未登入狀態
      $response = [
          'is_logged_in' => false
      ];
  }

  // 將 $response 陣列轉換為 JSON 字串並輸出
  echo json_encode($response);
  exit(); // 💡 確保腳本在輸出後立即停止
  ?>