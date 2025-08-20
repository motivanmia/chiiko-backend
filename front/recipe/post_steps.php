<?php
// ⭐️ 關鍵：確保引入了所有必要的共用檔案！
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['recipe_id']) || !isset($data['steps']) || !is_array($data['steps'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少 recipe_id 或 steps 格式不正確']);
            exit;
        }

        $recipe_id = intval($data['recipe_id']);
        
        $sql = "INSERT INTO `steps` (`recipe_id`, `order`, `content`) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        
        if (!$stmt) {
             throw new Exception("SQL 準備失敗: " . $mysqli->error);
        }

        // 使用 index 當作步驟的順序 (`order`)
        foreach ($data['steps'] as $index => $content) {
            $order = $index + 1; // 順序從 1 開始
            $stmt->bind_param("iis", $recipe_id, $order, $content);
            $stmt->execute();
        }

        $stmt->close();

        http_response_code(201);
        echo json_encode(['message' => '步驟新增成功！']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '伺服器錯誤: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>