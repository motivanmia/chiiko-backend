<?php
  // =================================================================
  //  API: 取得被檢舉的留言列表
  // =================================================================

  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  ini_set('display_errors', 1);
  error_reporting(E_ALL);

  require_method('GET');

  if (!isset($_SESSION['manager_id'])) {
      send_json(['status' => 'error', 'message' => '無權限存取'], 403); // 
      exit;
  }

  $reported_status = 2; 
  $sql = "SELECT 
            rc.comment_id, 
            rc.content, 
            rc.created_at, 
            rc.recipe_id,
            m.name AS member_name
          FROM recipe_comment AS rc
          JOIN users AS m ON rc.member_id = m.user_id
          WHERE rc.status = ?
          ORDER BY rc.created_at DESC";
  
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) {
    send_json(['status' => 'error', 'message' => '資料庫查詢準備失敗: ' . $mysqli->error], 500);
    exit;
  }

  $stmt->bind_param('i', $reported_status);
  $stmt->execute();
  $result = $stmt->get_result();
  $comments = $result->fetch_all(MYSQLI_ASSOC);

  send_json([
      'status' => 'success',
      'data' => $comments
  ]);

  $stmt->close();
  $mysqli->close();
?>