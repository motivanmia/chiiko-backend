<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

try {
    // 啟用 session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'fail', 'message' => '使用者未登入']);
        exit;
    }
    $user_id = $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'fail', 'message' => 'Method Not Allowed']);
        exit;
    }

    $recipe_id = isset($_GET['recipe_id']) ? (int)$_GET['recipe_id'] : 0;
    if ($recipe_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'fail', 'message' => '無效的食譜 ID']);
        exit;
    }

    // --- 步驟 1: 查詢主食譜資料，並驗證所有權 ---
    $stmt_recipe = $mysqli->prepare("SELECT * FROM `recipe` WHERE recipe_id = ? AND user_id = ?");
    $stmt_recipe->bind_param("ii", $recipe_id, $user_id);
    $stmt_recipe->execute();
    $result_recipe = $stmt_recipe->get_result();

    if ($result_recipe->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'fail', 'message' => '找不到該食譜或無權限存取']);
        exit;
    }
    
    $recipeData = $result_recipe->fetch_assoc();
    $stmt_recipe->close();

    // --- 步驟 2: 查詢相關的食材 ---
    $stmt_ingredients = $mysqli->prepare("SELECT name, serving AS amount FROM `ingredient_item` WHERE recipe_id = ?");
    $stmt_ingredients->bind_param("i", $recipe_id);
    $stmt_ingredients->execute();
    $result_ingredients = $stmt_ingredients->get_result();
    $ingredients = [];
    while ($row = $result_ingredients->fetch_assoc()) {
        $ingredients[] = $row;
    }
    $stmt_ingredients->close();

    // --- 步驟 3: 查詢相關的步驟 ---
    $stmt_steps = $mysqli->prepare("SELECT * FROM `steps` WHERE recipe_id = ? ORDER BY `order` ASC");
    $stmt_steps->bind_param("i", $recipe_id);
    $stmt_steps->execute();
    $result_steps = $stmt_steps->get_result();
    $steps = [];
    while ($row = $result_steps->fetch_assoc()) {
        $steps[] = $row;
    }
    $stmt_steps->close();

    // --- 步驟 4: 組合所有資料並回傳 ---
    $recipeData['ingredients'] = $ingredients;
    $recipeData['steps'] = $steps;
    
    // 處理圖片路徑
    if (!empty($recipeData['image'])) {
        // 檢查是否已經是完整的 URL
        if (!preg_match('/^https?:\/\//', $recipeData['image'])) {
            $recipeData['image'] = IMG_BASE_URL . '/' . htmlspecialchars($recipeData['image']);
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'data' => $recipeData]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => '伺服器發生錯誤: ' . $e->getMessage()]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>