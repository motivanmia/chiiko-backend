<?php
// 引入必要的檔案，用於資料庫連線、CORS 處理和通用函式
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 確保請求方法是 GET
require_method('GET');

try {
    $sql = "SELECT * FROM `recipe_category`";
    $result = $mysqli->query($sql);
    
    if ($result) {
        // 將查詢結果轉換為關聯陣列的集合
        $categories = $result->fetch_all(MYSQLI_ASSOC);
        
        // 處理圖片路徑
        foreach ($categories as &$category) {
            if (!empty($category['image'])) {
                // 將圖片名稱與基礎 URL 拼接成完整路徑
                $category['image'] = IMG_BASE_URL . '/' . $category['image'];
            }
        }
        // 必須在 foreach 循環後解除引用，以避免意外行為
        unset($category);
        
        // 將結果以 JSON 格式回傳給客戶端
        echo json_encode($categories);
    } else {
        // 如果查詢失敗，設定 HTTP 狀態碼為 500
        http_response_code(500);
        // 回傳一個包含錯誤訊息的 JSON 物件
        echo json_encode(['error' => '查詢資料失敗: ' . $mysqli->error]);
    }
} catch (\mysqli_sql_exception $e) {
    // 捕獲任何 mysqli 相關的例外，例如連線失敗
    http_response_code(500);
    echo json_encode(['error' => '查詢資料失敗: ' . $e->getMessage()]);
}

// 你的程式碼中沒有 'else' 區塊來處理非 GET 請求，
// 但在 require_method('GET') 之後，這一部分已經被處理了。
// 這裡可以省略，因為 require_method() 已經完成了同樣的驗證。

?>