<?php
//食譜詳細按紐點選撈食譜id相關所有內文資料用

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


        $stmt_recipe = $mysqli->prepare("SELECT * FROM `recipe` WHERE `recipe_id` = ?");
        $stmt_recipe->bind_param("i", $recipe_id);
        $stmt_recipe->execute();
        $result_recipe = $stmt_recipe->get_result();
        $recipe_data = $result_recipe->fetch_assoc();
        $stmt_recipe->close();

        // 如果找不到主食譜，就直接回傳錯誤
        if (!$recipe_data) {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'fail', 'message' => '找不到指定的食譜']);
            exit();
        }

        // ✅ --- 2. 查詢相關的食材項目 (已修正) ---
        // 使用 LEFT JOIN，根據 ingredient_id 去 `ingredients` 表中取得食材的 `name`
        $sql_ingredients = "
            SELECT 
                ii.serving, 
                i.name 
            FROM 
                `ingredient_item` AS ii
            LEFT JOIN 
                `ingredients` AS i ON ii.ingredient_id = i.ingredient_id
            WHERE 
                ii.recipe_id = ?
        ";
        
        $stmt_ingredients = $mysqli->prepare($sql_ingredients);
        $stmt_ingredients->bind_param("i", $recipe_id);
        $stmt_ingredients->execute();
        $result_ingredients = $stmt_ingredients->get_result();
        $ingredients = [];
        while ($row = $result_ingredients->fetch_assoc()) {
            $ingredients[] = $row;
        }
        $stmt_ingredients->close();

        // --- 3. 查詢相關的步驟 (維持不變) ---
        $stmt_steps = $mysqli->prepare("SELECT * FROM `steps` WHERE `recipe_id` = ? ORDER BY `order` ASC");
        $stmt_steps->bind_param("i", $recipe_id);
        $stmt_steps->execute();
        $result_steps = $stmt_steps->get_result();
        $steps = [];
        while ($row = $result_steps->fetch_assoc()) {
            $steps[] = $row;
        }
        $stmt_steps->close();

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
?>