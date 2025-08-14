<?php
  header('Access-Control-Allow-Origin: http://localhost:5173');

  require_once __DIR__ . '/common/config.php';
  
  $conn = new mysqli(
      DB_HOST,
      DB_USER,
      DB_PSW,
      DB_NAME,
      DB_PORT
  );
  if ($conn->connect_error) {
      die(json_encode(["error" => "連線失敗: " . $conn->connect_error]));
  }

  header('Content-Type: application/json; charset=utf-8');

  $sql = "SELECT * FROM ingredients";
  $result = $conn->query($sql);

  $data = [];
  while ($row = $result->fetch_assoc()) {
    // 若欄位是 JSON/TEXT 都可以這樣處理
    $row['image']   = $row['image']   ? json_decode($row['image'], true) : [];
    $row['content'] = $row['content'] ? json_decode($row['content'], true) : [];
    $row['status']  = (int)($row['status'] ?? 0);
    $row['ingredients_categary_id'] = (int)($row['ingredients_categary_id'] ?? 0);
    $data[] = $row;
  }
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

  $conn->close();
?>