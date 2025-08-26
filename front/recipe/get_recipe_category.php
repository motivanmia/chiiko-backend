<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('GET');

try {
    $sql = "SELECT * FROM `recipe_category`";
    $result = $mysqli->query($sql);
    
    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
        
        foreach ($categories as &$category) {
            if (!empty($category['image'])) {
                $category['image'] = IMG_BASE_URL . '/' . $category['image'];
            }
        }
        unset($category);
        
        echo json_encode(['success' => true, 'data' => $categories]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '查詢資料失敗: ' . $mysqli->error]);
    }
} catch (\mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '查詢資料失敗: ' . $e->getMessage()]);
}

?>