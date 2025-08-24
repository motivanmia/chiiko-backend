<?php
// 請確保你的 common/conn.php 檔案已經引入了你的 config.php
// 如果沒有，請在 get_recipe.php 的開頭加上這一行：
// require_once __DIR__ . '/../../common/config.php'; 

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $mostBookmarkedRecipes = [];
        $seasonalHotRecipes = [];
        $latestRecipes = [];

        // SQL 查詢 1: 最多收藏
        $mostBookmarkedQuery = "SELECT
            r.*,
            COUNT(rf.recipe_id) AS favorite_count
        FROM
            `recipe` AS r
        LEFT JOIN
            `recipe_favorite` AS rf ON r.recipe_id = rf.recipe_id
        GROUP BY
            r.recipe_id
        ORDER BY
            favorite_count DESC;";
        $mostBookmarkedResult = $mysqli->query($mostBookmarkedQuery);
        if ($mostBookmarkedResult) {
            $mostBookmarkedRecipes = $mostBookmarkedResult->fetch_all(MYSQLI_ASSOC);
            // 遍歷結果集，為每筆資料的圖片路徑加上 IMG_BASE_URL
            foreach ($mostBookmarkedRecipes as &$recipe) {
                // ✅ 直接使用已經定義的 IMG_BASE_URL 常量
                $recipe['image'] = IMG_BASE_URL .'/'. $recipe['image'];
            }
        }
        
        // SQL 查詢 2: 當季熱門
        $seasonalHotQuery = "SELECT
            r.*,
            COUNT(rf.recipe_id) AS favorite_count
        FROM
            `recipe` AS r
        LEFT JOIN
            `recipe_favorite` AS rf ON r.recipe_id = rf.recipe_id
        WHERE
            r.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) 
        GROUP BY
            r.recipe_id
        ORDER BY
            favorite_count DESC;";
        $seasonalHotResult = $mysqli->query($seasonalHotQuery);
        if ($seasonalHotResult) {
            $seasonalHotRecipes = $seasonalHotResult->fetch_all(MYSQLI_ASSOC);
            // 遍歷結果集，為每筆資料的圖片路徑加上 IMG_BASE_URL
            foreach ($seasonalHotRecipes as &$recipe) {
                // ✅ 直接使用已經定義的 IMG_BASE_URL 常量
                $recipe['image'] = IMG_BASE_URL  .'/'. $recipe['image'];
            }
        }

        // SQL 查詢 3: 最新投稿
        $latestRecipesQuery = "SELECT
            r.*,
            COUNT(rf.recipe_id) AS favorite_count
        FROM
            `recipe` AS r
        LEFT JOIN
            `recipe_favorite` AS rf ON r.recipe_id = rf.recipe_id
        GROUP BY
            r.recipe_id
        ORDER BY
            r.created_at DESC;";
        
        $latestRecipesResult = $mysqli->query($latestRecipesQuery);
        if ($latestRecipesResult) {
            $latestRecipes = $latestRecipesResult->fetch_all(MYSQLI_ASSOC);
            // 遍歷結果集，為每筆資料的圖片路徑加上 IMG_BASE_URL
            foreach ($latestRecipes as &$recipe) {
                // ✅ 直接使用已經定義的 IMG_BASE_URL 常量
                $recipe['image'] = IMG_BASE_URL . '/' . $recipe['image'];
            }
        }

        // 建立一個包含三個結果的 PHP 陣列
        $response = [
            'success' => true,
            'data' => [
                'mostBookmarked' => $mostBookmarkedRecipes,
                'seasonalHot' => $seasonalHotRecipes,
                'latest' => $latestRecipes,
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        
    } catch (\mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '查詢資料失敗: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}


//   require_once __DIR__ . '/../../common/conn.php';
//   require_once __DIR__ . '/../../common/functions.php';
  // 引入共用檔案
//   require_once __DIR__ . '/../../common/conn.php';
//   require_once __DIR__ . '/../../common/cors.php';
//   require_once __DIR__ . '/../../common/functions.php';

//   // 啟用錯誤回報，方便偵錯
//   ini_set('display_errors', 1);
//   error_reporting(E_ALL);

//   // 限制只允許 GET 請求
//   require_method('GET');

//   // 取得從 URL 傳來的 recipe_id 參數
//   $recipe_id = get_int_param('recipe_id');

//   if (!$recipe_id) {
//     send_json(['status' => 'error', 'message' => '缺少必要的 recipe_id 參數'], 400); 
//     exit;
//   }
  
//   // --- 第一步：查詢食譜主要資料 ---
//   $sql_recipe = "SELECT 
//       r.recipe_id, r.user_id, u.name AS author_name, r.name, r.content, 
//       r.serving, r.image, r.cooked_time, r.status, r.created_at, r.tag
//     FROM recipe AS r
//     JOIN users AS u ON r.user_id = u.user_id
//     WHERE r.recipe_id = {$recipe_id}";

//   $result_recipe = db_query($mysqli, $sql_recipe);

//   if (!$result_recipe) {
//     send_json(['status' => 'error', 'message' => '查詢食譜時發生資料庫錯誤'], 500);
//     exit;
//   }

//   $recipe_data = $result_recipe->fetch_assoc(); 
//   if (!$recipe_data) {
//     send_json([
//       'status' => 'error', 
//       'message' => '找不到指定的食譜',
//       'data' => null
//     ], 404);
//     exit;
//   }

//   // --- 如果找到了食譜，才繼續查詢其所有關聯資料 ---

//   // --- 第二步：查詢步驟 ---
//   $sql_steps = "SELECT step_id, `order`, content FROM steps WHERE recipe_id = {$recipe_id} ORDER BY `order` ASC";
//   $result_steps = db_query($mysqli, $sql_steps);
//   $recipe_data['steps'] = $result_steps ? $result_steps->fetch_all(MYSQLI_ASSOC) : [];

//   // --- 第三步：查詢食材 ---
//   $sql_ingredients = "SELECT ii.ingredient_item_id, i.name, ii.serving AS amount 
//     FROM ingredient_item AS ii
//     JOIN ingredients AS i ON ii.ingredient_id = i.ingredient_id
//     WHERE ii.recipe_id = {$recipe_id}";
//   $result_ingredients = db_query($mysqli, $sql_ingredients);
//   $recipe_data['ingredients'] = $result_ingredients ? $result_ingredients->fetch_all(MYSQLI_ASSOC) : [];
  
//   // --- 第四步：查詢留言 ---
//   $sql_comments = "SELECT
//       rc.comment_id,
//       rc.parent_id,
//       rc.content,
//       rc.created_at,
//       m.user_id,
//       m.name AS member_name
//     FROM recipe_comment AS rc
//     JOIN users AS m ON rc.member_id = m.user_id
//     WHERE rc.recipe_id = {$recipe_id} AND rc.status = 0
//     ORDER BY rc.created_at ASC";
//   $result_comments = db_query($mysqli, $sql_comments);
//   $recipe_data['comments'] = $result_comments ? $result_comments->fetch_all(MYSQLI_ASSOC) : [];

//   // --- 第五步：查詢收藏總數 (修正後) ---
//   $sql_favorites_count = "SELECT COUNT(*) AS total_favorites FROM recipe_favorite WHERE recipe_id = {$recipe_id}";
//   $result_favorites_count = db_query($mysqli, $sql_favorites_count);
  
//   // ⭐️ 核心修正：加入保護，確保即使查詢失敗也不會產生致命錯誤 ⭐️
//   if ($result_favorites_count) {
//       $favorites_count_data = $result_favorites_count->fetch_assoc();
//       $recipe_data['favorites_count'] = isset($favorites_count_data['total_favorites']) ? (int) $favorites_count_data['total_favorites'] : 0;
//   } else {
//       // 如果查詢失敗，給一個預設值 0
//       $recipe_data['favorites_count'] = 0;
//   }

//   // --- 最終步：回傳合併後的所有資料 ---
//   send_json([
//     'status' => 'success',
//     'message' => '食譜資料取得成功',
//     'data' => $recipe_data 
//   ]);
?>