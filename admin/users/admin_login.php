<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';


  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 獲取前端 JSON 資料
    $data = json_decode(file_get_contents('php://input'), true);

    $account = $data['account'] ?? '';
    $password = $data['password'] ?? '';

    // 必填欄位檢查
    if (empty($account) || empty($password)) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => '請輸入帳號、密碼']);
        exit();
    }

    try {
        // 準備 SQL 查詢，根據帳號查詢使用者資料
        $stmt = $mysqli->prepare("SELECT manager_id, name, password, role FROM managers WHERE account = ?");
        $stmt->bind_param("s", $account);
        $stmt->execute();
        $result = $stmt->get_result();

        // $account = $mysqli -> real_escape_string($account);
        // $sql = "SELECT manager_id, name, password, role FROM managers WHERE account = '${account}'";
        // $result = $mysqli -> query($sql);


        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 驗證密碼
            if (password_verify($password, $user['password'])) {
              // 登入成功

              $_SESSION['manager_id'] = $user['manager_id'];
              $_SESSION['name'] = $user['name'];
              $_SESSION['is_logged_in'] = true; // 標記為已登入
              $_SESSION['role'] = $user['role']; 
              http_response_code(200); // OK
              echo json_encode([
                'message' => '登入成功！',
                'user' => [
                    'manager_id' => $user['manager_id'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ]
              ]);
            } else {
                // 密碼錯誤
                http_response_code(401); // Unauthorized
                echo json_encode(['message' => '帳號或密碼不正確。']);
            }
        } else {
            // 帳號不存在
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => '帳號或密碼不正確。']);
        }

        $stmt->close();
        $mysqli->close();

    } catch (mysqli_sql_exception $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['message' => '伺服器內部錯誤，請稍後再試。']);
    }
}
?>