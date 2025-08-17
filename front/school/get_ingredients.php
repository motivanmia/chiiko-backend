<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';
  
  require_method('GET');

  $sql = "SELECT * FROM ingredients";

  // 取得資料
  $result = db_query($mysqli, $sql);
  $data = $result->fetch_all(MYSQLI_ASSOC);

  // $data = [];
  // while ($row = $result->fetch_assoc()) {
  //   $row['image']   = $row['image']   ? json_decode($row['image'], true) : [];
  //   $row['content'] = $row['content'] ? json_decode($row['content'], true) : [];
  //   $row['status']  = (int)($row['status'] ?? 0);
  //   $row['ingredients_categary_id'] = (int)($row['ingredients_categary_id'] ?? 0);
  //   $data[] = $row;
  // }

  send_json([
    'status' => 'success',
    'message' => '資料取得成功',
    'data' => $data
  ]);
  $conn->close();
?>