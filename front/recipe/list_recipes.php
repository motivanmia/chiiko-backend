<?php
// 顯示後台食譜的資料與判斷篩選條件 現在管理員id的定義有問題待修
// 強制開啟錯誤回報
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

try {
  // 核心 SQL 查詢維持不變，只在 SELECT 中多增加一個欄位
  $sql = "
    SELECT 
      r.recipe_id, 
      r.name, 
      r.created_at, 
      r.status, 
      r.manager_id, -- 【唯一的添加】把 manager_id 也選取出來，供前端判斷
      COALESCE(u.name, m.name) AS author_name, 
      rc.name AS category_name
    FROM recipe r
    LEFT JOIN users u ON r.user_id = u.user_id 
    LEFT JOIN managers m ON r.manager_id = m.manager_id
    LEFT JOIN recipe_category rc ON r.recipe_category_id = rc.recipe_category_id
    ORDER BY r.created_at DESC
  ";

  $result = $mysqli->query($sql);
  
  if (!$result) {
    throw new Exception("SQL 查詢失敗: " . $mysqli->error);
  }

  $recipes = [];

  while ($row = $result->fetch_assoc()) {
    $recipes[] = [
      'number'     => $row['recipe_id'],
      'name'       => $row['name'],
      'author'     => $row['author_name'] ?? '未知',
      'category'   => $row['category_name'] ?? '未分類',
      'date'       => $row['created_at'],
      'status'     => map_status($row['status']),
      // 【唯一的添加】將 manager_id 欄位加入到回傳的 JSON 中
      // 前端 Vue 程式會接收這個欄位來判斷作者類型
      'manager_id' => $row['manager_id'],
    ];
  }

  header('Content-Type: application/json');
  echo json_encode([
    'status' => 'success',
    'data'   => $recipes,
  ]);

} catch (Throwable $e) {
  http_response_code(500); 
  header('Content-Type: application/json');
  echo json_encode([
    'status'  => 'fail',
    'message' => $e->getMessage(),
  ]);
} finally {
  if (isset($mysqli)) {
      $mysqli->close();
  }
}

function map_status($code) {
  return match ((int)$code) {
    0 => '未審核',
    1 => '已審核',
    2 => '已下架',
    3 => '草稿',
    default => '未知',
  };
}