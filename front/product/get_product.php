<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');

  // 1. 接收前端傳來的參數
  $type = isset($_GET['type']) ? $_GET['type'] : null;
  $categoryId = isset($_GET['category_id']) ? $_GET['category_id'] : null;
  
  $sql = "SELECT * FROM products WHERE 1";

  // 根據參數動態新增篩選條件
  if ($categoryId !== null) {
    // 篩選特定分類 將接收到的分類id變數直接加入sql語法中
    $sql .= " AND product_category_id = '" . $categoryId . "'";
  } else if ($type === 'under100') {
    // 百元商品推薦
    $sql .= " AND unit_price <= 100 ORDER BY RAND() LIMIT 6";
  } else if ($type === 'all_random') {
    // 處理全部推薦商品(隨機)
    $sql .= " ORDER BY RAND() LIMIT 6";
  }

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