<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';

header('Content-Type: application/json');
global $mysqli; // ✅ 保持 global 宣告，確保可以使用 $mysqli

try {
    if (!isset($_GET['category'])) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'message' => 'Category parameter is missing.']);
        return;
    }

    $categoryName = urldecode($_GET['category']);
    
    // ✅ 步驟 1: 使用 mysqli_real_escape_string 和 mysqli_query 取得 ID
    $escapedCategoryName = $mysqli->real_escape_string($categoryName);
    $query_category_id = "SELECT recipe_category_id FROM recipe_category WHERE name = '{$escapedCategoryName}'";
    $result_category_id = $mysqli->query($query_category_id);

    if (!$result_category_id) {
        throw new mysqli_sql_exception('Category query failed: ' . $mysqli->error);
    }

    if ($result_category_id->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'data' => [], 'message' => 'Category not found or no recipes available.']);
        return;
    }

    $categoryRow = $result_category_id->fetch_assoc();
    $categoryId = $categoryRow['recipe_category_id'];

    // ✅ 步驟 2: 使用 mysqli_real_escape_string 和 mysqli_query 查詢食譜
    $escapedCategoryId = $mysqli->real_escape_string($categoryId);
    $query_recipes = "SELECT recipe_id, name, content, serving, image, cooked_time, status FROM recipe WHERE recipe_category_id = '{$escapedCategoryId}'";
    $result_recipes = $mysqli->query($query_recipes);

    if (!$result_recipes) {
        throw new mysqli_sql_exception('Recipes query failed: ' . $mysqli->error);
    }

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
    // 這裡的 close() 可以留著，但如果 conn.php 有設定持久連接則不適用
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>