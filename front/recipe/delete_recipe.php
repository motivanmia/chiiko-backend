<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 取得請求方法
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['recipe_id'])) {
            http_response_code(400);
            echo json_encode(['error' => '未提供食譜ID']);
            return;
        }

        $recipe_id = intval($data['recipe_id']);

        $sql = "DELETE FROM `recipe` WHERE `recipe_id` = {$recipe_id}";

        $result = $mysqli->query($sql);

        if ($result) {
            if ($mysqli->affected_rows > 0) {
                http_response_code(200);
                echo json_encode(['message' => '食譜刪除成功！']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '找不到指定的食譜ID']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => '刪除食譜失敗: ' . $mysqli->error]);
        }
    } catch (\mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '刪除食譜失敗: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>