<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  function checkUserLoggedIn() {
    // 檢查 Session 中是否有 'is_logged_in' 且為 true
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
        // 如果已登入，回傳使用者資料
        return [
            'user_id' => $_SESSION['user_id'],
            'user_name' => $_SESSION['user_name']
        ];
    }
    
    // 如果未登入，回傳 false
    return false;
  }
?>