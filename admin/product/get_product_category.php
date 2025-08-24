<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');
  
  $sql = "SELECT * FROM product_categories";

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