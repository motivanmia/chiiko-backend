<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';

header('Content-Type: application/json');
global $mysqli;

try {
    if (!isset($_GET['category'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Category parameter is missing.']);
        return;
    }

    $categoryName = urldecode($_GET['category']);
    
    // 調試：記錄接收到的分類名稱
    $debugInfo = ['requested_category' => $categoryName];

    $escapedCategoryName = $mysqli->real_escape_string($categoryName);
    $query_category_id = "SELECT recipe_category_id FROM recipe_category WHERE name = '{$escapedCategoryName}'";
    $result_category_id = $mysqli->query($query_category_id);

    if (!$result_category_id) {
        throw new mysqli_sql_exception('Category query failed: ' . $mysqli->error);
    }

    if ($result_category_id->num_rows === 0) {
        // 調試：顯示所有可用分類
        $debug_query = "SELECT recipe_category_id, name FROM recipe_category ORDER BY recipe_category_id";
        $debug_result = $mysqli->query($debug_query);
        $available_categories = [];
        if ($debug_result) {
            while ($row = $debug_result->fetch_assoc()) {
                $available_categories[] = [
                    'id' => $row['recipe_category_id'],
                    'name' => $row['name']
                ];
            }
        }
        
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'data' => [], 
            'message' => 'Category not found.',
            'debug_info' => $debugInfo,
            'available_categories' => $available_categories
        ]);
        return;
    }

    $categoryRow = $result_category_id->fetch_assoc();
    $categoryId = $categoryRow['recipe_category_id'];
    
    // 調試：記錄找到的分類ID
    $debugInfo['found_category_id'] = $categoryId;

    // 先檢查該分類下有多少筆資料
    $count_query = "SELECT COUNT(*) as total FROM recipe WHERE recipe_category_id = '{$categoryId}'";
    $count_result = $mysqli->query($count_query);
    $count_row = $count_result->fetch_assoc();
    $debugInfo['total_recipes_in_category'] = $count_row['total'];

    // 檢查 recipe 表的總筆數
    $total_count_query = "SELECT COUNT(*) as total FROM recipe";
    $total_count_result = $mysqli->query($total_count_query);
    $total_count_row = $total_count_result->fetch_assoc();
    $debugInfo['total_recipes_in_table'] = $total_count_row['total'];

    // 檢查該分類下是否有資料（簡化查詢）
    $simple_query = "SELECT recipe_id, name, recipe_category_id FROM recipe WHERE recipe_category_id = '{$categoryId}' LIMIT 5";
    $simple_result = $mysqli->query($simple_query);
    $simple_recipes = [];
    if ($simple_result && $simple_result->num_rows > 0) {
        while ($row = $simple_result->fetch_assoc()) {
            $simple_recipes[] = $row;
        }
    }
    $debugInfo['simple_query_results'] = $simple_recipes;

    $escapedCategoryId = $mysqli->real_escape_string($categoryId);
    $query_recipes = "SELECT
                            r.recipe_id,
                            r.name,
                            r.content,
                            r.serving,
                            r.image,
                            r.cooked_time,
                            r.status,
                            r.tag,
                        COUNT(rf.recipe_id) AS favorite_count
                        FROM
                            `recipe` AS r
                        LEFT JOIN
                            `recipe_favorite` AS rf ON r.recipe_id = rf.recipe_id
                        WHERE
                            r.recipe_category_id = '{$escapedCategoryId}'
                        AND
                            r.status =1
                        GROUP BY
                            r.recipe_id,
                            r.tag
                        ORDER BY
                            r.created_at DESC
                        ";
    
    // 調試：記錄執行的SQL
    $debugInfo['executed_sql'] = $query_recipes;
                        
    $result_recipes = $mysqli->query($query_recipes);

    if (!$result_recipes) {
        throw new mysqli_sql_exception('Recipes query failed: ' . $mysqli->error);
    }

    $recipes = [];
    if ($result_recipes->num_rows > 0) {
        while ($row = $result_recipes->fetch_assoc()) {
            // 這裡新增處理，移除 tag 字串開頭的 '#'
            if (!empty($row['tag'])) {
                $row['tag'] = ltrim($row['tag'], '#');
            }

            if (!empty($row['image'])) {
                $row['image'] = IMG_BASE_URL . '/' . $row['image'];
            }
            $recipes[] = $row;
        }
    }
    
    $debugInfo['query_result_count'] = $result_recipes->num_rows;

    echo json_encode([
        'success' => true, 
        'data' => $recipes,
        'debug_info' => $debugInfo
    ]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database query failed.', 
        'error_detail' => $e->getMessage()
    ]);

} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>