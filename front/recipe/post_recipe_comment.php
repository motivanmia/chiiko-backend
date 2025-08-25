<?php
  // =================================================================
  //  API: 新增食譜留言
  // =================================================================

  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  ini_set('display_errors', 1);
  error_reporting(E_ALL);

  require_method('POST');

  if (!isset($_SESSION['member_id'])) {
      send_json(['status' => 'error', 'message' => '請先登入才能留言'], 401); 
      exit;
  }

  $data = get_json_input();
  
  $member_id = $_SESSION['member_id'];
  $recipe_id = isset($data['recipe_id']) ? (int)$data['recipe_id'] : null;
  $parent_id = isset($data['parent_id']) ? (int)$data['parent_id'] : null; 
  $content = isset($data['content']) ? trim($data['content']) : '';

  if (empty($recipe_id)) {
    send_json(['status' => 'error', 'message' => '缺少食譜 ID'], 400);
    exit;
  }
  if (empty($content)) {
    send_json(['status' => 'error', 'message' => '留言內容不能為空'], 400);
    exit;
  }


  $sql = "INSERT INTO recipe_comment (member_id, recipe_id, parent_id, content, status) 
          VALUES (?, ?, ?, ?, 0)";
  
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) {
    send_json(['status' => 'error', 'message' => '資料庫查詢準備失敗: ' . $mysqli->error], 500);
    exit;
  }

  $stmt->bind_param('iiis', $member_id, $recipe_id, $parent_id, $content);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    send_json([
        'status' => 'success',
        'message' => '留言發布成功！'
    ], 201); // 201 Created
  } else {
    send_json(['status' => 'error', 'message' => '留言發布失敗，請稍後再試'], 500);
  }

  $stmt->close();
  $mysqli->close();
?>