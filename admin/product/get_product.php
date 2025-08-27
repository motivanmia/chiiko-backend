<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');
  
  // SQL 查詢
  $sql = "SELECT
    p.*,
    pc.name AS category_name
  FROM products AS p
  INNER JOIN
    product_categories AS pc
  ON p.product_category_id = pc.product_category_id
  ORDER BY p.product_id ";

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