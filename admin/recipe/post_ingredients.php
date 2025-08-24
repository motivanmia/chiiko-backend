<?php
// ⭐️ 關鍵：確保引入了所有必要的共用檔案！
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 驗證必要的 recipe_id 和 ingredients 陣列是否存在
        if (!isset($data['recipe_id']) || !isset($data['ingredients']) || !is_array($data['ingredients'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少 recipe_id 或 ingredients 格式不正確']);
            exit;
        }

        $recipe_id = intval($data['recipe_id']);

        // 準備 SQL 語句
        // 我們一次性插入多筆資料，效率更高
        $sql = "INSERT INTO `ingredient_item` (`recipe_id`, `ingredient_id`, `serving`) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {
             throw new Exception("SQL 準備失敗: " . $mysqli->error);
        }

        // 遍歷前端傳來的每一個食材
        foreach ($data['ingredients'] as $item) {
            // 在真實應用中，您需要先去 `ingredients` 資料表查詢或新增，以取得 `ingredient_id`
            // 這裡我們先用一個假 ID (例如 1) 來做示範
            $ingredient_id = 1; 
            
            $name = $item['name']; // 食材名稱，這裡暫時沒用到，但可以做日誌
            $amount = $item['amount'];

            // 綁定參數並執行
            $stmt->bind_param("iis", $recipe_id, $ingredient_id, $amount);
            $stmt->execute();
        }

        $stmt->close();

        http_response_code(201);
        echo json_encode(['message' => '食材新增成功！']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '伺服器錯誤: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>