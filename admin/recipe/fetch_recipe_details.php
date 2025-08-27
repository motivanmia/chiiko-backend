<?php
// 強制開啟錯誤回報，方便開發時偵錯
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入所有必要的通用檔案
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/conn.php';

// 使用 try...catch 包裹所有邏輯，以進行統一的錯誤處理
try {
    // 使用輔助函式檢查請求方法
    require_method('GET');

    // 使用輔助函式獲取並驗證 recipe_id
    $recipe_id = get_int_param('recipe_id'); // 假設前端傳來的參數是 recipe_id
    if (!$recipe_id || $recipe_id <= 0) {
        throw new Exception('未提供有效的食譜 ID', 400);
    }
    
    // 將 recipe_id 轉換為安全的整數，防止 SQL 注入
    $safe_recipe_id = (int)$recipe_id;

    // ==========================================================
    // 1. 查詢主食譜資料 (已修改為 mysqli_query)
    // ==========================================================
    $sql_recipe = "SELECT 
                        r.*, 
                        COALESCE(u.name, m.name) AS author_name, 
                        rc.name AS category_name
                    FROM `recipe` r
                    LEFT JOIN `users` u ON r.user_id = u.user_id
                    LEFT JOIN `managers` m ON r.manager_id = m.manager_id
                    LEFT JOIN `recipe_category` rc ON r.recipe_category_id = rc.recipe_category_id
                    WHERE r.recipe_id = {$safe_recipe_id}";
    
    $result_recipe = $mysqli->query($sql_recipe);
    if (!$result_recipe) {
        throw new Exception('查詢食譜資料失敗：' . $mysqli->error, 500);
    }
    
    $recipe_data = $result_recipe->fetch_assoc();
    $result_recipe->free();

    if (!$recipe_data) {
        throw new Exception('找不到指定的食譜', 404);
    }

    // ==========================================================
    // 【✅ 核心修正 ✅】
    // 2. 查詢食材 (ingredients) 的邏輯 (已修改為 mysqli_query)
    // ==========================================================
    $sql_ingredients = "SELECT name, serving AS amount FROM ingredient_item WHERE recipe_id = {$safe_recipe_id}";
    $result_ingredients = $mysqli->query($sql_ingredients);
    if (!$result_ingredients) {
        throw new Exception('查詢食材資料失敗：' . $mysqli->error, 500);
    }
    
    $ingredients_data = $result_ingredients->fetch_all(MYSQLI_ASSOC);
    $result_ingredients->free();
    
    // ==========================================================
    // 【✅ 核心修正 ✅】
    // 3. 查詢步驟 (steps) 的邏輯 (已修改為 mysqli_query)
    // ==========================================================
    $sql_steps = "SELECT content FROM steps WHERE recipe_id = {$safe_recipe_id} ORDER BY `order` ASC";
    $result_steps = $mysqli->query($sql_steps);
    if (!$result_steps) {
        throw new Exception('查詢步驟資料失敗：' . $mysqli->error, 500);
    }
    
    $steps_raw_data = $result_steps->fetch_all(MYSQLI_ASSOC);
    $result_steps->free();
    
    // 將步驟陣列從 [{content: '...'}, ...] 轉換成 ['...', '...']
    $steps_data = array_column($steps_raw_data, 'content');

    // ==========================================================
    // 4. 組合最終的回傳資料
    // ==========================================================
    $response_data = [
        'recipe'      => $recipe_data,
        'ingredients' => $ingredients_data,
        'steps'       => $steps_data
    ];

    // 使用 send_json 函式來發送成功的回應
    send_json(['status' => 'success', 'data' => $response_data], 200);

} catch (Exception $e) {
    // 統一的錯誤處理
    $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
    send_json([
        'status'  => 'fail',
        'message' => $e->getMessage()
    ], $code);
} finally {
    // 確保資料庫連線總是會被關閉
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>