<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'fail', 'message' => 'Method Not Allowed']);
        exit;
    }

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($user_id === null || !is_numeric($user_id) || $user_id <= 0) {
        http_response_code(401); // 401 Unauthorized
        echo json_encode(['status' => 'fail', 'message' => '使用者未登入或 user_id 不合法']);
        exit;
    }

    $clean_user_id = $mysqli->real_escape_string($user_id);

    $sql = "
        SELECT 
            r.recipe_id, 
            r.name, 
            r.image,
            r.created_at, 
            r.status,
            COALESCE(COUNT(DISTINCT fr.user_id), 0) AS favorite_count,
            COALESCE(COUNT(DISTINCT c.comment_id), 0) AS comment_count
        FROM 
            `recipe` AS r
        LEFT JOIN 
            `recipe_favorite` AS fr ON r.recipe_id = fr.recipe_id
        LEFT JOIN
            `recipe_comment` AS c ON r.recipe_id = c.recipe_id
        WHERE 
            r.user_id = '{$clean_user_id}'
        GROUP BY 
            r.recipe_id
        ORDER BY
            r.created_at DESC;
    ";

    $result = $mysqli->query($sql);
    if (!$result) {
        throw new Exception("SQL 查詢失敗: " . $mysqli->error);
    }

    $recipes = [];
    while ($row = $result->fetch_assoc()) {

        $full_image_url = IMG_BASE_URL.'/'.htmlspecialchars($row['image']); 


        $recipes[] = [
            'recipe_id' => (int)$row['recipe_id'],
            'name' => htmlspecialchars($row['name']),
            'image' => $full_image_url,
            'created_at' => $row['created_at'],
            'status' => (int)$row['status'],
            'favorite_count' => (int)$row['favorite_count'],
            'comment_count' => (int)$row['comment_count'],
        ];
    }

    $result->free();

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => '成功獲取會員食譜列表',
        'data' => $recipes,
    ]);

} catch (Throwable $e) {
    $code = $e->getCode() ?: 500;
    $code = is_numeric($code) && $code >= 400 && $code < 600 ? $code : 500;
    http_response_code($code);
    echo json_encode([
        'status' => 'fail',
        'message' => $e->getMessage() ?: '伺服器發生未預期錯誤',
    ]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>