<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 取得請求方法
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'PATCH') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['recipe_id'])) {
            http_response_code(400);
            echo json_encode(['error' => '未提供食譜ID']);
            return;
        }

        $recipe_id = intval($data['recipe_id']);
        unset($data['recipe_id']);

        $set_clause = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'content', 'recipe_categary_id', 'serving', 'image', 'cooked_time', 'status', 'tag'])) {
                if (is_string($value)) {
                    $set_clause[] = "`{$key}` = '" . $mysqli->real_escape_string($value) . "'";
                } elseif (is_int($value) || is_float($value)) {
                    $set_clause[] = "`{$key}` = " . $value;
                } elseif ($value === null) {
                    $set_clause[] = "`{$key}` = NULL";
                }
            }
        }
        
        if (empty($set_clause)) {
            http_response_code(400);
            echo json_encode(['error' => '沒有可更新的欄位']);
            return;
        }

        $sql = "UPDATE `recipe` SET " . implode(', ', $set_clause) . " WHERE `recipe_id` = {$recipe_id}";
        $result = $mysqli->query($sql);

        if ($result) {
            http_response_code(200);
            echo json_encode(['message' => '食譜更新成功！']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '更新食譜失敗: ' . $mysqli->error]);
        }

    } catch (\mysqli_sql_exception $e) {
        http_response_code(400);
        echo json_encode(['error' => '更新食譜失敗: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>