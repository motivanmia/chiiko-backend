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

//   require_method('GET');


//   $recipe_id = get_int_param('recipe_id');

  
//   if (!$recipe_id) {
//     send_json([
//         'status' => 'error',
//         'message' => '缺少必要的 recipe_id 參數'
//     ], 400); 
//     exit;
//   }
  
  
//   // 查詢 recipe 資料表，並透過 JOIN 順便把作者的名字也抓出來
//   // 使用 r 和 u 作為資料表的別名，可以讓 SQL 語句更簡潔
//   $sql = "SELECT 
//       r.recipe_id,
//       r.user_id,
//       u.name AS author_name, -- 從 users 表抓作者名字，並重新命名為 author_name
//       r.name,
//       r.content,
//       r.serving,
//       r.image, 
//       r.cooked_time,
//       r.status,
//       r.created_at
//     FROM recipe AS r
//     JOIN users AS u ON r.user_id = u.user_id
//     WHERE r.recipe_id = {$recipe_id} -- 關鍵！只查詢 URL 參數指定的那一筆食譜
//   ";

//   //我們預期只會找到 "一筆" 資料，而不是像購物車那樣是個列表
//   $result = db_query($mysqli, $sql);
  
//   // 使用 fetch_assoc() 來取得單一筆結果
//   $data = $result->fetch_assoc(); 

  
//   send_json([
//     'status' => 'success',
//     'message' => '食譜資料取得成功',
//     'data' => $data 
//   ]);
?>