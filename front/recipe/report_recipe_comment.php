<?php
  // =================================================================
  //  API: 檢舉食譜留言 (report_comment.php)
  // =================================================================

  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  
  require_method('POST');

  if (!isset($_SESSION['member_id'])) {
      send_json(['status' => 'error', 'message' => '請先登入才能檢舉'], 401);
      exit;
  }

  $data = get_json_input();
  $comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : null;

  if (empty($comment_id)) {
    send_json(['status' => 'error', 'message' => '缺少留言 ID'], 400);
    exit;
  }

  $reported_status = 2; 

  $sql = "UPDATE recipe_comment SET status = ? WHERE comment_id = ?";
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) {
    send_json(['status' => 'error', 'message' => '資料庫查詢準備失敗: ' . $mysqli->error], 500);
    exit;
  }

  $stmt->bind_param('ii', $reported_status, $comment_id);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    send_json([
        'status' => 'success',
        'message' => '感謝您的回報，我們將會盡快審核！'
    ]);
  } else {
    send_json(['status' => 'error', 'message' => '操作失敗，可能該留言不存在或已被檢舉'], 404);
  }

  $stmt->close();
  $mysqli->close();
?>