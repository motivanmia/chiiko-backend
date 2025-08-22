<?php
// ✅ 保持這行，它負責引入 config.php 並定義 IMG_BASE_URL
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';

// ❌ 移除這行多餘的 define，因為它已經在 config.php 中定義過了
// define("IMG_BASE_URL", "http://localhost:8888/uploads"); 

header('Content-Type: application/json');
global $mysqli;

try {
    if (!isset($_GET['category'])) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'message' => 'Category parameter is missing.']);
        return;
    }

    $categoryName = urldecode($_GET['category']);
    
    // 步驟 1: 根據中文分類名稱取得對應的 ID
    $sql_category_id = "SELECT recipe_category_id FROM recipe_category WHERE name = ?";
    $stmt = $mysqli->prepare($sql_category_id);
    $stmt->bind_param("s", $categoryName);
    $stmt->execute();
    $result_category_id = $stmt->get_result();

    if ($result_category_id->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'data' => [], 'message' => 'Category not found or no recipes available.']);
        return;
    }

    $categoryRow = $result_category_id->fetch_assoc();
    $categoryId = $categoryRow['recipe_category_id'];

    // 步驟 2: 使用 ID 查詢所有相關食譜
    $sql_recipes = "SELECT recipe_id, name, content, serving, image, cooked_time, status FROM recipe WHERE recipe_category_id = ?";
    $stmt = $mysqli->prepare($sql_recipes);
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result_recipes = $stmt->get_result();

    $recipes = [];
    if ($result_recipes->num_rows > 0) {
        while ($row = $result_recipes->fetch_assoc()) {
            $row['image'] = IMG_BASE_URL . '/' . $row['image'];
            $recipes[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $recipes]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.', 'error_detail' => $e->getMessage()]);

} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>