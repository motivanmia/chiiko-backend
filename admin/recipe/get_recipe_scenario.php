<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';


// 設定字符集為 UTF-8
mysqli_set_charset($mysqli, "utf8");

// 輔助函式：發送 JSON 回應並終止腳本
function send_json($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // 檢查 mysqli 資料庫連線
    global $mysqli;
    if (!isset($mysqli) || $mysqli->connect_error) {
        send_json([
            'success' => false,
            'message' => 'Database connection failed.',
            'error_detail' => $mysqli->connect_error ?? 'Connection object not found.'
        ], 500);
    }

    // 檢查 category 參數是否存在
    if (!isset($_GET['category'])) {
        send_json([
            'success' => false,
            'message' => 'Category parameter is missing.'
        ], 400);
    }

    $categoryName = urldecode($_GET['category']);
    
    // 調試輸出
    error_log("Received category: " . $categoryName);

    // 步驟 1: 查詢 category_id (使用 query)
    // 使用 mysqli_real_escape_string 處理輸入以防止 SQL 注入
    $escapedCategoryName = mysqli_real_escape_string($mysqli, $categoryName);
        
    $sql_category_id = "SELECT recipe_category_id FROM recipe_category WHERE name = '{$escapedCategoryName}'";
    $result_category_id = mysqli_query($mysqli, $sql_category_id);

    if (!$result_category_id) {
        throw new Exception("Category query failed: " . mysqli_error($mysqli));
    }
        
    $categoryRow = mysqli_fetch_assoc($result_category_id);
    if (!$categoryRow) {
        // 調試：顯示所有可用的分類
        $debug_sql = "SELECT recipe_category_id, name FROM recipe_category";
        $debug_result = mysqli_query($mysqli, $debug_sql);
        $available_categories = [];
        if ($debug_result) {
            while ($row = mysqli_fetch_assoc($debug_result)) {
                $available_categories[] = $row['name'];
            }
        }
        
        send_json([
            'success' => false,
            'data' => [],
            'message' => 'Category not found.',
            'requested_category' => $categoryName,
            'available_categories' => $available_categories
        ], 404);
    }

    $categoryId = $categoryRow['recipe_category_id'];

    // 步驟 2: 根據 category_id 查詢食譜列表 (使用 query)
    $sql_recipes = "SELECT recipe_id, name, content, serving, image, cooked_time, status FROM recipe WHERE recipe_category_id = {$categoryId}";
    $result_recipes = mysqli_query($mysqli, $sql_recipes);
        
    if (!$result_recipes) {
        throw new Exception("Recipes query failed: " . mysqli_error($mysqli));
    }

    $recipes = [];
    if (mysqli_num_rows($result_recipes) > 0) {
        while ($row = mysqli_fetch_assoc($result_recipes)) {
            // 檢查圖片路徑
            if (!empty($row['image'])) {
                $row['image'] = IMG_BASE_URL . '/' . $row['image'];
            } else {
                $row['image'] = null;
            }
            $recipes[] = $row;
        }
    }

    // 步驟 3: 發送成功回應
    send_json([
        'success' => true,
        'data' => $recipes,
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'total_recipes' => count($recipes)
    ]);

} catch (Exception $e) {
    // 捕獲所有其他例外，並回傳 500 錯誤
    send_json([
        'success' => false,
        'message' => 'An error occurred.',
        'error_detail' => $e->getMessage()
    ], 500);
} finally {
    // 無論成功或失敗，都安全地關閉資料庫連線
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>