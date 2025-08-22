<?php
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


?>