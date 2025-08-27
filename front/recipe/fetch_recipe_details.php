<?php
// 食譜詳細按鈕點選，撈食譜ID相關所有內文資料用

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 設定回應標頭為 JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 從 URL 參數中取得 recipe_id (例如: ?recipe_id=123)
        if (!isset($_GET['recipe_id']) || !is_numeric($_GET['recipe_id'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'fail', 'message' => '未提供有效的食譜 ID']);
            exit();
        }

        $recipe_id = (int)$_GET['recipe_id'];

        // 💡 1. 查詢主食譜資料
        $sql_recipe = "SELECT * FROM `recipe` WHERE `recipe_id` = {$recipe_id}";
        $result_recipe = $mysqli->query($sql_recipe);
        
        if (!$result_recipe) {
            http_response_code(500);
            echo json_encode(['status' => 'fail', 'message' => '資料庫查詢失敗: ' . $mysqli->error]);
            exit();
        }
        
        $recipe_data = $result_recipe->fetch_assoc();
        $result_recipe->free();

        if (!$recipe_data) {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'fail', 'message' => '找不到指定的食譜']);
            exit();
        }

        // ✅ --- 2. 查詢相關的食材項目 ---
        // 💡 替換成 mysqli_query
        $sql_ingredients = "
            SELECT 
                ii.serving, 
                i.name 
            FROM 
                `ingredient_item` AS ii
            LEFT JOIN 
                `ingredients` AS i ON ii.ingredient_id = i.ingredient_id
            WHERE 
                ii.recipe_id = {$recipe_id}
        ";
        
        $result_ingredients = $mysqli->query($sql_ingredients);
        $ingredients = [];
        if ($result_ingredients) {
            while ($row = $result_ingredients->fetch_assoc()) {
                $ingredients[] = $row;
            }
            $result_ingredients->free();
        }

        // --- 3. 查詢相關的步驟 ---
        // 💡 替換成 mysqli_query
        $sql_steps = "SELECT * FROM `steps` WHERE `recipe_id` = {$recipe_id} ORDER BY `order` ASC";
        $result_steps = $mysqli->query($sql_steps);
        $steps = [];
        if ($result_steps) {
            while ($row = $result_steps->fetch_assoc()) {
                $steps[] = $row;
            }
            $result_steps->free();
        }

        // --- 組合所有資料 (維持不變) ---
        $full_recipe_details = $recipe_data;
        $full_recipe_details['ingredients'] = $ingredients;
        $full_recipe_details['steps'] = $steps;

        http_response_code(200);
        echo json_encode(['status' => 'success', 'data' => $full_recipe_details]);

    } catch (Throwable $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'fail', 'message' => '伺服器錯誤: ' . $e->getMessage()]);
    } finally {
        if (isset($mysqli)) {
            $mysqli->close();
        }
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => '僅允許 GET 方法']);
}