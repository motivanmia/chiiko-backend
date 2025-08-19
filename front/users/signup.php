<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 檢查 $mysqli 變數是否被成功建立
if (!$mysqli) {
    // 這裡使用 $mysqli->connect_errno 和 $mysqli->connect_error 來取得錯誤訊息
    // 但因為連線失敗， $mysqli 可能是 null，所以必須先檢查
    // 假設 send_json 函式在 functions.php 中
    send_json([
        'status' => 'fail',
        'message' => '資料庫連線失敗',
        'error_code' => 500
    ], 500);
}

  // 處理 OPTIONS 預檢請求
  //詢問是否允許接下來的跨域請求，請求的目的只是為了確認權限，而不是要取得任何資料。
  if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    //204請求成功，但回傳主體中沒有任何內容。
    //通常用於不需要回傳任何資料的操作，如 DELETE、PUT 或 OPTIONS 請求。

    //200請求成功，且回傳主體（Response Body）中包含資料。
    //通常用於 GET 請求，回傳如 JSON、HTML 等資料。
    exit();
  }

// 取得前端送來的 JSON 資料
$data = json_decode(file_get_contents('php://input'), true);

// 統一從 $data 陣列中取得變數，並確保其存在
$name = $data['name'] ?? null;
$password = $data['password'] ?? null;
$account = $data['account'] ?? null;
$phone = $data['phone'] ?? null;

// 必填欄位檢查
if (empty($name) || empty($password) || empty($account)) {
    http_response_code(400);
    echo json_encode(['message' => '使用者名稱、密碼與電子郵件為必填。']);
    exit();
}

// 進行資料驗證
if (!filter_var($account, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['message' => '電子郵件格式不正確。']);
    exit();
}

try {
    // 檢查電子郵件是否已被註冊
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE account = ?");
    $stmt->bind_param("s", $account); // MySQLi 參數綁定語法
    $stmt->execute();
    $stmt->store_result(); // 儲存結果以檢查行數

    if ($stmt->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['message' => '此電子郵件已被註冊。']);
        $stmt->close();
        exit();
    }
    $stmt->close(); // 關閉舊的語句

    // 密碼加密
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 寫入資料庫
    // 確保你的欄位數量與 bind_param 數量一致
    $stmt = $mysqli->prepare("INSERT INTO users (name, password, account, phone) VALUES (?, ?, ?, ?)");
    
    // "ssss" 代表四個參數都是字串
    $stmt->bind_param("ssss", $name, $hashed_password, $account, $phone);

    $stmt->execute();

    // 成功回應
    http_response_code(201); // Created
    echo json_encode(['message' => '註冊成功！']);

    $stmt->close();

} catch (mysqli_sql_exception $e) {
    // 處理資料庫錯誤
    http_response_code(500); // Internal Server Error
    echo json_encode(['message' => '伺服器內部錯誤，請稍後再試。' . $e->getMessage()]);
    if (isset($stmt)) $stmt->close();
}
?>