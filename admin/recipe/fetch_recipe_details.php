<?php
// /admin/recipe/fetch_recipe_details.php (最終完整版)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!isset($_GET['recipe_id']) || !is_numeric($_GET['recipe_id'])) {
            http_response_code(400);
            echo json_encode(['status' => 'fail', 'message' => '未提供有效的食譜 ID']);
            exit();
        }
        $recipe_id = (int)$_GET['recipe_id'];

        // ==========================================================
        // 【✅ 核心修正 ✅】
        // 升級 SQL 查詢語句，使用 LEFT JOIN 關聯所有需要的表格
        // ==========================================================
        $sql = "SELECT 
                    r.*, 
                    COALESCE(u.name, m.name) AS author_name, 
                    rc.name AS category_name
                FROM `recipe` r
                LEFT JOIN `users` u ON r.user_id = u.user_id
                LEFT JOIN `managers` m ON r.manager_id = m.manager_id
                LEFT JOIN `recipe_category` rc ON r.recipe_category_id = rc.recipe_category_id
                WHERE r.recipe_id = ?";
        
        $stmt_recipe = $mysqli->prepare($sql);
        $stmt_recipe->bind_param("i", $recipe_id);
        $stmt_recipe->execute();
        $result_recipe = $stmt_recipe->get_result();
        $recipe_data = $result_recipe->fetch_assoc();
        $stmt_recipe->close();

        if (!$recipe_data) {
            http_response_code(404);
            echo json_encode(['status' => 'fail', 'message' => '找不到指定的食譜']);
            exit();
        }
        
        // 拼接圖片完整 URL (保持不變)
        $base_url = 'http://localhost:8888';
        $uploads_path = '/uploads/';
        if (!empty($recipe_data['image']) && !filter_var($recipe_data['image'], FILTER_VALIDATE_URL)) {
            $recipe_data['image'] = $base_url . $uploads_path . ltrim($recipe_data['image'], '/');
        }
        
        // --- 查詢食材和步驟 (保持不變) ---
        // ... (您的查詢食材和步驟的程式碼)
        $ingredients = []; // 假設這是您的食材陣列
        $steps = [];       // 假設這是您的步驟陣列

        // 組合資料 (保持不變)
        $response_data = [
            'recipe'      => $recipe_data,
            'ingredients' => $ingredients,
            'steps'       => $steps
        ];

        http_response_code(200);
        echo json_encode(['status' => 'success', 'data' => $response_data]);

    } catch (Throwable $e) {
        // ... (錯誤處理保持不變)
    } finally {
        // ... (關閉連線保持不變)
    }
} else {
    // ... (方法不允許的處理保持不變)
}
?>