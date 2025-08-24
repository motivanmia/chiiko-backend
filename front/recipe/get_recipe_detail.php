<?php
<<<<<<< HEAD
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(["status" => "error", "message" => "不允許的請求方法"], 405);
}

if (!isset($_GET['recipe_id'])) {
    send_json(["status" => "error", "message" => "缺少食譜ID"], 400);
}

$recipe_id = $_GET['recipe_id'];

if (!is_numeric($recipe_id) || $recipe_id <= 0) {
    send_json(["status" => "error", "message" => "無效的食譜ID"], 400);
}
=======

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
>>>>>>> 9124f6dc27371c6364171623ee6ae7e3359d0d07

$safe_recipe_id = (int)$recipe_id;

<<<<<<< HEAD
try {
    // 步驟 1: 取得食譜基本資訊，包含收藏數
    $sql_recipe = "
        SELECT 
            r.*,
            COUNT(rf.recipe_id) AS favorites_count,
            COALESCE(u.name, m.name) AS author_name,
            COALESCE(u.image, NULL) AS author_image
        FROM recipe r
        LEFT JOIN users u ON r.user_id = u.user_id
        LEFT JOIN managers m ON r.manager_id = m.manager_id
        LEFT JOIN recipe_favorite rf ON r.recipe_id = rf.recipe_id
        WHERE r.recipe_id = $safe_recipe_id
        GROUP BY r.recipe_id
    ";
    
    $result_recipe = mysqli_query($mysqli, $sql_recipe);
    
    if (!$result_recipe) {
        throw new Exception("資料庫查詢失敗: " . mysqli_error($mysqli));
    }

    if (mysqli_num_rows($result_recipe) === 0) {
        send_json(["status" => "error", "message" => "找不到指定的食譜", "data" => null], 404);
    }
    
    $recipe = mysqli_fetch_assoc($result_recipe);
    mysqli_free_result($result_recipe);

    // 步驟 2: 取得食譜食材
    $sql_ingredients = "
        SELECT 
            ii.ingredient_item_id,
            ii.serving,
            i.name
        FROM ingredient_item ii
        LEFT JOIN ingredients i ON ii.ingredient_id = i.ingredient_id
        WHERE ii.recipe_id = $safe_recipe_id
    ";
    $result_ingredients = mysqli_query($mysqli, $sql_ingredients);
    
    $ingredients = [];
    if ($result_ingredients) {
        while ($row = mysqli_fetch_assoc($result_ingredients)) {
            $ingredients[] = [
                'ingredient_item_id' => $row['ingredient_item_id'],
                'name' => $row['name'],
                'amount' => $row['serving']
            ];
        }
        mysqli_free_result($result_ingredients);
    }

    // 步驟 3: 取得食譜步驟
    $sql_steps = "SELECT step_id, `order`, content FROM steps WHERE recipe_id = $safe_recipe_id ORDER BY `order` ASC";
    $result_steps = mysqli_query($mysqli, $sql_steps);
    
    $steps = [];
    if ($result_steps) {
        while ($row = mysqli_fetch_assoc($result_steps)) {
            $steps[] = $row;
        }
        mysqli_free_result($result_steps);
    }

    // 步驟 4: 取得食譜留言 (主留言)
    $sql_comments = "
        SELECT
            c.*,
            COALESCE(u.name, m.name) AS author_name,
            COALESCE(u.image, NULL) AS author_image
        FROM recipe_comment c
        LEFT JOIN users u ON c.member_id = u.user_id
        LEFT JOIN managers m ON c.member_id = m.manager_id
        WHERE c.recipe_id = $safe_recipe_id AND c.parent_id IS NULL
        ORDER BY c.created_at ASC
    ";
    $result_comments = mysqli_query($mysqli, $sql_comments);
    
    $comments = [];
    if ($result_comments) {
        while ($row = mysqli_fetch_assoc($result_comments)) {
            $comments[] = $row;
        }
        mysqli_free_result($result_comments);
    }

    // 步驟 5: 取得每個主留言的回覆
    foreach ($comments as &$comment) {
        $comment_id = (int)$comment['comment_id'];
        $sql_replies = "
            SELECT
                c.*,
                COALESCE(u.name, m.name) AS author_name,
                COALESCE(u.image, NULL) AS author_image
            FROM recipe_comment c
            LEFT JOIN users u ON c.member_id = u.user_id
            LEFT JOIN managers m ON c.member_id = m.manager_id
            WHERE c.parent_id = $comment_id
            ORDER BY c.created_at ASC
        ";
        $result_replies = mysqli_query($mysqli, $sql_replies);
        
        $replies = [];
        if ($result_replies) {
            while ($row_reply = mysqli_fetch_assoc($result_replies)) {
                $replies[] = $row_reply;
            }
            mysqli_free_result($result_replies);
        }
        $comment['replies'] = $replies;
    }
    unset($comment);

    // 步驟 6: 組裝最終的 JSON 資料
    $recipe_detail = [
        "recipe_id" => $recipe['recipe_id'],
        "name" => $recipe['name'],
        "content" => $recipe['content'],
        "image" => $recipe['image'],
        "author_name" => $recipe['author_name'],
        "author_image" => isset($recipe['author_image']) ? $recipe['author_image'] : null,
        "cooked_time" => $recipe['cooked_time'],
        "serving" => $recipe['serving'],
        "tag" => $recipe['tag'],
        "favorites_count" => (int)$recipe['favorites_count'],
        "comments" => $comments,
        "ingredients" => $ingredients,
        "steps" => $steps,
    ];

    // 步驟 7: 回傳成功的回應
    send_json(["status" => "success", "message" => "成功取得食譜資料", "data" => $recipe_detail]);

} catch (Exception $e) {
    send_json(["status" => "error", "message" => "處理請求時發生錯誤: " . $e->getMessage()], 500);
}
=======

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
>>>>>>> 9124f6dc27371c6364171623ee6ae7e3359d0d07
?>