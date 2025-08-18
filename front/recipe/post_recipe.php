<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 取得請求方法
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    try {
        // 取得 JSON 格式的請求主體
        $data = json_decode(file_get_contents('php://input'), true);

        // 使用 mysqli_real_escape_string() 對所有字串資料進行轉義
        $user_id = $data['user_id'] ?? 'null';
        $manage_id = $data['manage_id'] ?? 'null';
        $recipe_categary_id = $data['recipe_categary_id'];

        $name = "'" . $mysqli->real_escape_string($data['name']) . "'";
        $content = "'" . $mysqli->real_escape_string($data['content']) . "'";
        
        $serving = $data['serving'] ?? 'null';
        
        $image_value = $data['image'] ?? null;
        $image = ($image_value === null) ? 'NULL' : "'" . $mysqli->real_escape_string($image_value) . "'";
        
        $cooked_time = $data['cooked_time'] ?? 'null';
        $status = $data['status'] ?? 'null';

        $tag_value = $data['tag'] ?? null;
        $tag = ($tag_value === null) ? 'NULL' : "'" . $mysqli->real_escape_string($tag_value) . "'";

        // 拼接 SQL 查詢字串
        $sql = "INSERT INTO `recipe` (
            `user_id`, `manage_id`, `recipe_categary_id`, `name`, `content`, 
            `serving`, `image`, `cooked_time`, `status`, `tag`
        ) VALUES (
            {$user_id}, {$manage_id}, {$recipe_categary_id}, {$name}, {$content},
            {$serving}, {$image}, {$cooked_time}, {$status}, {$tag}
        )";

        $result = $mysqli->query($sql);

        // 檢查查詢是否成功
        if ($result) {
            http_response_code(201);
            echo json_encode(['message' => '食譜新增成功！']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '新增食譜失敗: ' . $mysqli->error]);
        }
    } catch (\mysqli_sql_exception $e) {
        http_response_code(400);
        echo json_encode(['error' => '新增食譜失敗: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>