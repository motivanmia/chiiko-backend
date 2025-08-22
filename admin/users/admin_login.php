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
        echo json_encode([
            'status' => 'fail',
            'message' => '請輸入帳號、密碼']);
        exit();
    }

    try {
        // ⚠️ 在使用 mysqli_query() 時，必須手動處理輸入以防止 SQL 注入
        $safe_account = mysqli_real_escape_string($mysqli, $account);

        // 準備 SQL 查詢
        // 💡 確保 SQL 語法正確，表名是 managers
        $sql = "SELECT `manager_id`, `name`, `password`, `role`, `status` FROM `managers` WHERE `account` = '$safe_account'";
        $result = mysqli_query($mysqli, $sql);

        // 檢查查詢是否成功
        if (!$result) {
            http_response_code(500); // Internal Server Error
            echo json_encode([
                'status' => 'fail',
                'message' => '查詢失敗，請稍後再試。']);
            exit();
        }

        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            
            // 驗證密碼
            if (password_verify($password, $user['password'])) {

                // ✅ 新增狀態檢查
                if ($user['status'] == 1) {
                    http_response_code(403); // Forbidden
                    echo json_encode([
                        'status' => 'fail',
                        'message' => '此帳號已被停用，無法登入。'
                    ]);
                    exit();
                }
                
                // 登入成功
                $_SESSION['manager_id'] = $user['manager_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['is_logged_in'] = true;
                $_SESSION['role'] = (int)$user['role'];
                http_response_code(200); // OK
                echo json_encode([
                    'status' => 'success',
                    'message' => '登入成功！',
                    'user' => [
                        'manager_id' => $user['manager_id'],
                        'name' => $user['name'],
                        'role' => $user['role'],
                        'role' => (int)$user['role']
                    ]
                ]);
            } else {
                // 密碼錯誤
                http_response_code(401); // Unauthorized
                echo json_encode([
                    'status' => 'fail',
                    'message' => '帳號或密碼不正確。']);
            }
        } else {
            // 帳號不存在
            http_response_code(401); // Unauthorized
            echo json_encode([
                'status' => 'fail',
                'message' => '帳號或密碼不正確。']);
        }

        // 釋放結果集
        mysqli_free_result($result);
        mysqli_close($mysqli);

    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'status' => 'fail',
            'message' => '伺服器內部錯誤，請稍後再試。']);
    }
}
?>