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

        // --- ⭐️ 核心修正：對所有欄位進行更嚴謹的處理 ---

        // 這些是數字型別的欄位。如果前端沒給，就當作 NULL 存入資料庫。
        // 使用 intval() 確保它們是數字，防止 SQL 注入。
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 'NULL';
        $manage_id = isset($data['manage_id']) ? intval($data['manage_id']) : 'NULL';
        $recipe_category_id = isset($data['recipe_category_id']) ? intval($data['recipe_category_id']) : 'NULL';
        $status = isset($data['status']) ? intval($data['status']) : 0; // status 通常給個預設值 0

        // 這些是字串型別的欄位。
        // 必須使用 real_escape_string 進行安全轉義，並在兩側加上單引號 "'"。
        // 使用 ?? '' 確保即使前端沒給值，我們也會傳入一個安全的空字串，而不是 null。
        $name = "'" . $mysqli->real_escape_string($data['name'] ?? '') . "'";
        $content = "'" . $mysqli->real_escape_string($data['content'] ?? '') . "'";
        $serving = "'" . $mysqli->real_escape_string($data['serving'] ?? '') . "'";
        $image = "'" . $mysqli->real_escape_string($data['image'] ?? '') . "'";
        $cooked_time = "'" . $mysqli->real_escape_string($data['cooked_time'] ?? '') . "'";
        $tag = "'" . $mysqli->real_escape_string($data['tag'] ?? '') . "'";

        // 拼接 SQL 查詢字串
        $sql = "INSERT INTO `recipe` (
            `user_id`, `manage_id`, `recipe_category_id`, `name`, `content`, 
            `serving`, `image`, `cooked_time`, `status`, `tag`
        ) VALUES (
            {$user_id}, {$manage_id}, {$recipe_category_id}, {$name}, {$content},
            {$serving}, {$image}, {$cooked_time}, {$status}, {$tag}
        )";

        $result = $mysqli->query($sql);

        // 檢查查詢是否成功
        if ($result) {
            // ⭐️ 功能擴充：取得剛剛建立的那筆資料的 ID
            $new_recipe_id = $mysqli->insert_id; 
            
            http_response_code(201); // 201 Created
            
            // ⭐️ 功能擴充：將新建立的 recipe_id 一併回傳給前端
            // 這樣前端才能接著去新增屬於這篇食譜的食材和步驟
            echo json_encode([
                'message' => '食譜新增成功！',
                'recipe_id' => $new_recipe_id
            ]);
        } else {
            // 如果是 SQL 語法錯誤，回傳 400 Bad Request 會更語意化
            http_response_code(400); 
            echo json_encode(['error' => '新增食譜失敗: ' . $mysqli->error]);
        }
    } catch (\Exception $e) {
        // 捕捉其他所有未預期的錯誤，例如 JSON 解析失敗等
        http_response_code(500);
        echo json_encode(['error' => '伺服器發生未預期錯誤: ' . $e->getMessage()]);
    }
} else {
    // 如果請求方法不是 POST，回傳 405 Method Not Allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>