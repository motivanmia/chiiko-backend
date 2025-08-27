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

    // ==========================================================
    //  1. 查詢主食譜資料 (您的 JOIN 寫法很好，保持並優化)
    // ==========================================================
    $sql_recipe = "SELECT 
                    r.*, 
                    COALESCE(u.name, m.name) AS author_name, 
                    rc.name AS category_name
                FROM `recipe` r
                LEFT JOIN `users` u ON r.user_id = u.user_id
                LEFT JOIN `managers` m ON r.manager_id = m.manager_id
                LEFT JOIN `recipe_category` rc ON r.recipe_category_id = rc.recipe_category_id
                WHERE r.recipe_id = ?";
    
    $stmt_recipe = $mysqli->prepare($sql_recipe);
    $stmt_recipe->bind_param("i", $recipe_id);
    $stmt_recipe->execute();
    $result_recipe = $stmt_recipe->get_result();
    $recipe_data = $result_recipe->fetch_assoc();
    $stmt_recipe->close();

    if (!$recipe_data) {
        throw new Exception('找不到指定的食譜', 404);
    }

    // ==========================================================
    // 【✅ 核心修正 ✅】
    //  2. 補上查詢食材 (ingredients) 的邏輯
    // ==========================================================
    $sql_ingredients = "SELECT name, serving AS amount FROM ingredient_item WHERE recipe_id = ?";
    $stmt_ingredients = $mysqli->prepare($sql_ingredients);
    $stmt_ingredients->bind_param("i", $recipe_id);
    $stmt_ingredients->execute();
    $result_ingredients = $stmt_ingredients->get_result();
    // 使用 fetch_all 直接獲取所有結果，更簡潔
    $ingredients_data = $result_ingredients->fetch_all(MYSQLI_ASSOC);
    $stmt_ingredients->close();
    
    // ==========================================================
    // 【✅ 核心修正 ✅】
    //  3. 補上查詢步驟 (steps) 的邏輯
    // ==========================================================
    $sql_steps = "SELECT content FROM steps WHERE recipe_id = ? ORDER BY `order` ASC";
    $stmt_steps = $mysqli->prepare($sql_steps);
    $stmt_steps->bind_param("i", $recipe_id);
    $stmt_steps->execute();
    $result_steps = $stmt_steps->get_result();
    // 使用 fetch_all 獲取所有步驟
    $steps_raw_data = $result_steps->fetch_all(MYSQLI_ASSOC);
    $stmt_steps->close();
    
    // 將步驟陣列從 [{content: '...'}, ...] 轉換成 ['...', '...']
    // 這一步是為了匹配您前端 RecipeEditPage.vue 的 watch 邏輯
    $steps_data = array_column($steps_raw_data, 'content');

    // ==========================================================
    //  4. 組合最終的回傳資料
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