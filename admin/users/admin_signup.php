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

  $data = json_decode(file_get_contents('php://input'), true);

  $name = $data['name'] ?? null;
  $account = $data['account'] ?? null;
  $password = $data['password'] ?? null;


  // 必填欄位檢查
  if (empty($name) || empty($password) || empty($account)) {
      http_response_code(400);
      echo json_encode(['message' => '管理員名稱、帳號與密碼為必填。']);
      exit();
  }

  // 進行資料驗證
  $pattern = '/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,20}$/';

  if (!preg_match($pattern, $account)) {
    http_response_code(400);
    echo json_encode(['message' => '帳號必須為6到20個字元，且包含英文字母與數字。']);
    exit();
  }


  try {
    // 檢查帳號是否已被註冊
    $stmt = $mysqli->prepare("SELECT * FROM managers WHERE account = ?");
    $stmt->bind_param("s", $account); 
    $stmt->execute();
    $stmt->store_result(); // 儲存結果以檢查行數

    if ($stmt->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['message' => '此帳號已被註冊。']);
        $stmt->close();
        exit();
    }
    $stmt->close(); // 關閉舊的語句

    // 密碼加密
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $role = 1;

    // 寫入資料庫
    // 確保你的欄位數量與 bind_param 數量一致
    $stmt = $mysqli->prepare("INSERT INTO managers (name, password, account, role ) VALUES (?, ?, ?, ?)");
    
    // "ssss" 代表四個參數都是字串
    $stmt->bind_param("sssi", $name, $hashed_password, $account, $role);

    $stmt->execute();

    // 成功回應
    http_response_code(201); // Created
    echo json_encode(['message' => '新增成功！']);

    $stmt->close();

  } catch (mysqli_sql_exception $e) {
      // 處理資料庫錯誤
      http_response_code(500); // Internal Server Error
      echo json_encode(['message' => '伺服器內部錯誤，請稍後再試。' . $e->getMessage()]);
      if (isset($stmt)) $stmt->close();
  }
?>