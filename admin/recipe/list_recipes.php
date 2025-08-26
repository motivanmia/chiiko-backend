<?php
// 顯示後台食譜的資料與判斷篩選條件
// 強制開啟錯誤回報
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

try {
  // 核心 SQL 查詢
  $sql = "
    SELECT 
      r.recipe_id, 
      r.name, 
      r.created_at, 
      r.status, 
      r.manager_id, 
      r.user_id, -- 把 user_id 也選出來，方便做更精準的判斷
      COALESCE(u.name, m.name) AS author_name, 
      rc.name AS category_name
    FROM recipe r
    LEFT JOIN users u ON r.user_id = u.user_id 
    LEFT JOIN managers m ON r.manager_id = m.manager_id
    LEFT JOIN recipe_category rc ON r.recipe_category_id = rc.recipe_category_id
    
    -- 【✅ 唯一的核心修正 ✅】
    -- 我們要排除的是「由會員建立(user_id IS NOT NULL)」且「狀態為草稿(status = 3)」的食譜
    -- 管理員自己建立的草稿 (manager_id IS NOT NULL AND status = 3) 則會被保留
    WHERE NOT (r.user_id IS NOT NULL AND r.status = 3)

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
      'status'     => map_status($row), // 將整個 $row 傳入，方便判斷
      'manager_id' => $row['manager_id'],
      'user_id'    => $row['user_id'], // 也將 user_id 傳給前端
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

// 【✅ 優化建議 ✅】
// 讓 map_status 函式可以根據作者類型回傳不同的狀態文字
// 這會讓前端的顯示更精準
function map_status($row) {
  $code = (int)$row['status'];
  $is_manager_post = !empty($row['manager_id']);

  if ($is_manager_post) {
    // 如果是管理員發的文
    return match ($code) {
      0 => '草稿 (管理員)', // 假設管理員的 0 也是草稿
      1 => '已發佈',
      default => '未知',
    };
  } else {
    // 如果是會員發的文
    return match ($code) {
      0 => '待審核',
      1 => '已審核',
      // status = 3 的情況已經被 SQL 過濾掉了
      default => '未知',
    };
  }
}