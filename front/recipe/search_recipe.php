<?php
// 引入資料庫連線和 CORS 設定
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';

header('Content-Type: application/json');
global $mysqli;

try {

$request_data = $_GET;

    // 檢查是否有傳入 'query' 參數
    // if (!isset($_POST['q'])) {
    //     http_response_code(400); 
    //     echo json_encode(['success' => false, 'message' => 'Query parameter is missing.']);
    //     return;
    // }
    if (!isset($request_data['q'])) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Query parameter is missing.']);
    return;
}

    // $searchQuery = urldecode($_POST['q']);
    $searchQuery = $request_data['q'];
    
    // 使用 LIKE 語句進行模糊搜尋，並使用參數化查詢來防止 SQL Injection
    $sql = "SELECT recipe_id, name, cooked_time, image FROM recipe WHERE name LIKE ?";
    $stmt = $mysqli->prepare($sql);

    // 在關鍵字前後加上 % 符號
    $searchTerm = "%" . $searchQuery . "%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $recipes = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // 拼接完整的圖片 URL (請根據你之前的討論，使用正確的路徑)
            $row['image'] = IMG_BASE_URL . '/' . $row['image'];
            $recipes[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $recipes]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database q failed.', 'error_detail' => $e->getMessage()]);

} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>