<?php

  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';


  ini_set('display_errors', 1);
  error_reporting(E_ALL);


  require_method('GET');


  $recipe_id = get_int_param('recipe_id');
  if (!$recipe_id) {
    send_json(['status' => 'error', 'message' => '缺少必要的 recipe_id 參數'], 400); 
    exit;
  }
  

  $sql_recipe = "SELECT 
      r.recipe_id, 
      r.user_id, 
      r.manager_id,
      COALESCE(u.name, m.name) AS author_name, 
      r.name, 
      r.content, 
      r.serving, 
      r.image, 
      r.cooked_time, 
      r.status, 
      r.created_at, 
      r.tag
    FROM recipe AS r
    LEFT JOIN users AS u ON r.user_id = u.user_id
    LEFT JOIN managers AS m ON r.manager_id = m.manager_id
    WHERE r.recipe_id = {$recipe_id}";

  $result_recipe = db_query($mysqli, $sql_recipe);


  if (!$result_recipe) {
    send_json(['status' => 'error', 'message' => '查詢食譜時發生資料庫錯誤'], 500);
    exit;
  }

 
  $recipe_data = $result_recipe->fetch_assoc(); 


  if (!$recipe_data) {
    send_json([
      'status' => 'error', 
      'message' => '找不到指定的食譜',
      'data' => null
    ], 404);
    exit;
  }

 
  $sql_steps = "SELECT step_id, `order`, content FROM steps WHERE recipe_id = {$recipe_id} ORDER BY `order` ASC";
  $result_steps = db_query($mysqli, $sql_steps);
  $recipe_data['steps'] = $result_steps ? $result_steps->fetch_all(MYSQLI_ASSOC) : [];

 
  $sql_ingredients = "SELECT ii.ingredient_item_id, i.name, ii.serving AS amount 
    FROM ingredient_item AS ii
    JOIN ingredients AS i ON ii.ingredient_id = i.ingredient_id
    WHERE ii.recipe_id = {$recipe_id}";
  $result_ingredients = db_query($mysqli, $sql_ingredients);
  $recipe_data['ingredients'] = $result_ingredients ? $result_ingredients->fetch_all(MYSQLI_ASSOC) : [];
  
  
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
  $recipe_data['comments'] = $result_comments ? $result_comments->fetch_all(MYSQLI_ASSOC) : [];

  $sql_favorites_count = "SELECT COUNT(*) AS total_favorites FROM recipe_favorite WHERE recipe_id = {$recipe_id}";
  $result_favorites_count = db_query($mysqli, $sql_favorites_count);
  
  if ($result_favorites_count) {
      $favorites_count_data = $result_favorites_count->fetch_assoc();
      $recipe_data['favorites_count'] = isset($favorites_count_data['total_favorites']) ? (int) $favorites_count_data['total_favorites'] : 0;
  } else {

      $recipe_data['favorites_count'] = 0;
  }


  send_json([
    'status' => 'success',
    'message' => '食譜資料取得成功',
    'data' => $recipe_data 
  ], 200);


  $mysqli->close();
?>