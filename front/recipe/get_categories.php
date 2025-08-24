<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/conn.php';

try {
  require_method('GET');

  // 依你的欄位調整：name/label、enabled/sort 等
  $sql = "SELECT id, name FROM recipe_category WHERE enabled = 1 ORDER BY sort ASC, id ASC";
  $result = $mysqli->query($sql);

  $rows = [];
  while ($row = $result->fetch_assoc()) {
    $rows[] = [
      'id'    => (int)$row['id'],
      'label' => $row['name'],
    ];
  }

  send_json([
    'status' => 'success',
    'data'   => $rows
  ], 200);

} catch (Throwable $e) {
  send_json(['status' => 'fail', 'message' => $e->getMessage()], 500);
} finally {
  if (isset($mysqli)) $mysqli->close();
}