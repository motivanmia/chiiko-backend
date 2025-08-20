<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // echo (IMG_BASE_URL);
  // echo (DB_PORT);
  require_method('GET');
  
  $sql = "SELECT * FROM ingredient_category";

  // 取得資料
  $result = db_query($mysqli, $sql);
  $data = $result->fetch_all(MYSQLI_ASSOC);


  send_json([
    'status' => 'success',
    'message' => '資料取得成功',
    'data' => $data
  ]);
  $conn->close();
?>