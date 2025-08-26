<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';

header('Content-Type: application/json');
global $mysqli;

try {
    $request_data = $_GET;

    if (!isset($request_data['q'])) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'message' => 'Query parameter is missing.']);
        return;
    }

    $searchQuery = $request_data['q'];
    
    $escapedSearchQuery = $mysqli->real_escape_string($searchQuery);
    
    // 使用 LEFT JOIN 和 GROUP BY 來計算每個食譜的收藏數
    $sql = "SELECT 
                r.recipe_id, 
                r.name, 
                r.cooked_time, 
                r.image,
                r.tag,
                COUNT(fr.recipe_id) AS favorite_count
            FROM 
                recipe AS r
            LEFT JOIN 
                recipe_favorite AS fr ON r.recipe_id = fr.recipe_id
            WHERE 
                r.name LIKE '%{$escapedSearchQuery}%'
                OR r.tag LIKE '%{$escapedSearchQuery}%'
            GROUP BY 
                r.recipe_id
            ORDER BY 
                favorite_count DESC";
    
    $result = $mysqli->query($sql);
    
    if (!$result) {
        throw new mysqli_sql_exception('Database query failed: ' . $mysqli->error);
    }

    $recipes = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
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