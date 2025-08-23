<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';
  //
  // 移除所有SESSION變數
  $_SESSION = array();
  //
  // 如果需要，也刪除 Session Cookie
  // 為了確保瀏覽器端的 Session ID 也被移除
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      $params["secure"], $params["httponly"]
    );
  }
  //
  // 3. 徹底銷毀 Session
  session_destroy();
  //
  // 回傳成功響應給前端
  header('Content-Type: application/json');
  echo json_encode(['status' => 'success', 'message' => '登出成功']);
  //
  // 確保沒有額外的輸出
  exit;
?>