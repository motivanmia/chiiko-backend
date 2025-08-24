<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';

header('Content-Type: application/json');
global $mysqli; 

try {
    if (!isset($_GET['category'])) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'message' => 'Category parameter is missing.']);
        return;
    }

    $categoryName = urldecode($_GET['category']);
    
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

    $escapedCategoryId = $mysqli->real_escape_string($categoryId);
    $query_recipes = "SELECT
                            r.recipe_id,
                            r.name,
                            r.content,
                            r.serving,
                            r.image,
                            r.cooked_time,
                            r.status,
                            r.tag,
                        COUNT(rf.recipe_id) AS favorite_count
                        FROM
                            `recipe` AS r
                        LEFT JOIN
                            `recipe_favorite` AS rf ON r.recipe_id = rf.recipe_id
                        WHERE
                            r.recipe_category_id = '{$escapedCategoryId}'
                        GROUP BY
                            r.recipe_id,
                            r.tag
                        ORDER BY
                            r.created_at DESC
                        ";
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
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>