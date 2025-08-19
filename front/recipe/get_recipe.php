<?php
  // 引入共用檔案
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 啟用錯誤回報，方便偵錯
  ini_set('display_errors', 1);
  error_reporting(E_ALL);

  // 限制只允許 GET 請求
  require_method('GET');

  // 取得從 URL 傳來的 recipe_id 參數
  $recipe_id = get_int_param('recipe_id');

  if (!$recipe_id) {
    send_json(['status' => 'error', 'message' => '缺少必要的 recipe_id 參數'], 400); 
    exit;
  }
  
  // --- 第一步：查詢食譜主要資料 ---
  $sql_recipe = "SELECT 
      r.recipe_id, r.user_id, u.name AS author_name, r.name, r.content, 
      r.serving, r.image, r.cooked_time, r.status, r.created_at, r.tag
    FROM recipe AS r
    JOIN users AS u ON r.user_id = u.user_id
    WHERE r.recipe_id = {$recipe_id}";

  $result_recipe = db_query($mysqli, $sql_recipe);
  $recipe_data = $result_recipe->fetch_assoc(); 

  // 如果找到了食譜，才繼續查詢其所有關聯資料
  if ($recipe_data) {
    // --- 第二步：查詢步驟 ---
    $sql_steps = "SELECT step_id, `order`, content FROM steps WHERE recipe_id = {$recipe_id} ORDER BY `order` ASC";
    $result_steps = db_query($mysqli, $sql_steps);
    $recipe_data['steps'] = $result_steps->fetch_all(MYSQLI_ASSOC);

    // --- 第三步：查詢食材 ---
    $sql_ingredients = "SELECT ii.ingredient_item_id, i.name, ii.serving AS amount 
      FROM ingredient_item AS ii
      JOIN ingredients AS i ON ii.ingredient_id = i.ingredient_id
      WHERE ii.recipe_id = {$recipe_id}";
    $result_ingredients = db_query($mysqli, $sql_ingredients);
    $recipe_data['ingredients'] = $result_ingredients->fetch_all(MYSQLI_ASSOC);
    
    // --- 第四步：查詢留言 ---
    $sql_comments = "SELECT
        rc.comment_id,
        rc.parent_id,
        rc.content,
        rc.created_at,
        m.user_id,
        m.name AS member_name
      FROM recipe_comment AS rc
      JOIN users AS m ON rc.member_id = m.user_id
      WHERE rc.recipe_id = {$recipe_id} AND rc.status = 0
      ORDER BY rc.created_at ASC";
      
    $result_comments = db_query($mysqli, $sql_comments);
    $recipe_data['comments'] = $result_comments->fetch_all(MYSQLI_ASSOC);

    // --- ⭐️ 新增的第五步：獨立查詢它的收藏總數 ⭐️ ---
    // 使用 COUNT(*) 來計算 recipe_favorite 表中有幾筆符合的紀錄
    $sql_favorites_count = "SELECT COUNT(*) AS total_favorites FROM recipe_favorite WHERE recipe_id = {$recipe_id}";
    $result_favorites_count = db_query($mysqli, $sql_favorites_count);
    $favorites_count_data = $result_favorites_count->fetch_assoc();
    // 將計算出的總數，作為一個新的 'favorites_count' 欄位，加入到主食譜資料中
    $recipe_data['favorites_count'] = $favorites_count_data['total_favorites'];
  }

  // --- 最終步：回傳合併後的所有資料 ---
  send_json([
    'status' => 'success',
    'message' => '食譜資料取得成功',
    'data' => $recipe_data 
  ]);
?>