<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 取得請求方法
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $result = $mysqli->query('SELECT * FROM recipe');
        
        // 檢查查詢是否成功
        if ($result) {
            $recipes = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($recipes);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '查詢資料失敗: ' . $mysqli->error]);
        }
    } catch (\mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '查詢資料失敗: ' . $e->getMessage()]);
    }
} else {
    // 如果不是 GET 請求，回傳錯誤
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>