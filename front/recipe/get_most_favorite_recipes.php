<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/config.php';

global $mysqli;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(["status" => "error", "message" => "不允許的請求方法"], 405);
}

try {
    $query = "SELECT r.recipe_id, r.name, r.image, COUNT(f.member_id) AS favorite_count
              FROM recipe AS r
              LEFT JOIN recipe_favorite AS f ON r.recipe_id = f.recipe_id
              GROUP BY r.recipe_id
              ORDER BY favorite_count DESC
              LIMIT 4;";
    
    $result = $mysqli->query($query);
    
    if (!$result) {
        throw new \mysqli_sql_exception('查詢失敗: ' . $mysqli->error);
    }
    
    $recipes = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($recipes as &$recipe) {
        $recipe['image'] = IMG_BASE_URL . '/' . $recipe['image'];
    }

    send_json(["status" => "success", "data" => $recipes], 200);

} catch (\mysqli_sql_exception $e) {
    send_json(["status" => "error", "message" => "資料庫操作失敗: " . $e->getMessage()], 500);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>