<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/config.php';

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

        // 轉換為整數，確保安全
        $recipe_id = (int)$_GET['recipe_id'];

        // 使用 mysqli_query 查詢食譜主資料
        $sql_recipe = "SELECT * FROM `recipe` WHERE `recipe_id` = '$recipe_id'";
        $result_recipe = mysqli_query($mysqli, $sql_recipe);

        // 檢查查詢是否成功
        if (!$result_recipe) {
            throw new Exception('查詢食譜資料失敗: ' . mysqli_error($mysqli));
        }

        $recipe_data = mysqli_fetch_assoc($result_recipe);

        // 如果找不到主食譜，就直接回傳錯誤
        if (!$recipe_data) {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'fail', 'message' => '找不到指定的食譜']);
            exit();
        }

        // ✅ --- 2. 查詢相關的食材項目 ---
        $sql_ingredients = "
            SELECT 
                ii.serving, 
                i.name 
            FROM 
                `ingredient_item` AS ii
            LEFT JOIN 
                `ingredients` AS i ON ii.ingredient_id = i.ingredient_id
            WHERE 
                ii.recipe_id = '$recipe_id'
        ";
        $result_ingredients = mysqli_query($mysqli, $sql_ingredients);

        if (!$result_ingredients) {
            throw new Exception('查詢食材資料失敗: ' . mysqli_error($mysqli));
        }
        
        $ingredients = [];
        while ($row = mysqli_fetch_assoc($result_ingredients)) {
            $ingredients[] = $row;
        }

        // --- 3. 查詢相關的步驟 ---
        $sql_steps = "SELECT * FROM `steps` WHERE `recipe_id` = '$recipe_id' ORDER BY `order` ASC";
        $result_steps = mysqli_query($mysqli, $sql_steps);
        
        if (!$result_steps) {
            throw new Exception('查詢步驟資料失敗: ' . mysqli_error($mysqli));
        }

        $steps = [];
        while ($row = mysqli_fetch_assoc($result_steps)) {
            $steps[] = $row;
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
?>