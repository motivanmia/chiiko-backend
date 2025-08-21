<?php
// 引入資料庫連線和 CORS 設定
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';

// 假設 IMG_BASE_URL 和 $mysqli 已經在 common/conn.php 和 common/config.php 中正確定義
require_once __DIR__ . '/../../common/config.php';

header('Content-Type: application/json');
global $mysqli; // ✅ 保持 global 宣告，確保可以使用 $mysqli

try {
    // 使用 $_GET 獲取請求資料
    $request_data = $_GET;

    // 檢查是否有傳入 'q' 參數
    if (!isset($request_data['q'])) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'message' => 'Query parameter is missing.']);
        return;
    }

    $searchQuery = $request_data['q'];
    
    // ✅ 步驟 1: 使用 mysqli_real_escape_string 處理輸入，防止 SQL Injection
    $escapedSearchQuery = $mysqli->real_escape_string($searchQuery);
    
    // ✅ 步驟 2: 構建完整的 SQL 語句
    $sql = "SELECT recipe_id, name, cooked_time, image FROM recipe WHERE name LIKE '%{$escapedSearchQuery}%'";
    
    // ✅ 步驟 3: 使用 mysqli_query 執行查詢
    $result = $mysqli->query($sql);
    
    if (!$result) {
        throw new mysqli_sql_exception('Database query failed: ' . $mysqli->error);
    }

    $recipes = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // 拼接完整的圖片 URL
            $row['image'] = IMG_BASE_URL . '/' . $row['image'];
            $recipes[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $recipes]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.', 'error_detail' => $e->getMessage()]);

} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>