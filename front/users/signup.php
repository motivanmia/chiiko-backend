<?php
    require_once __DIR__ . '/../../common/conn.php';
    require_once __DIR__ . '/../../common/cors.php';
    require_once __DIR__ . '/../../common/functions.php';
    //
    // 檢查 $mysqli 變數是否被成功建立
    if (!$mysqli) {
        send_json([
            'status' => 'fail',
            'message' => '資料庫連線失敗',
            'error_code' => 500
        ], 500);
    }
    // 取得前端送來的 JSON 資料
    $data = json_decode(file_get_contents('php://input'), true);
    //
    // 統一從 $data 陣列中取得變數，並確保其存在
    $name = $data['name'] ?? null;
    $password = $data['password'] ?? null;
    $account = $data['account'] ?? null;
    $phone = $data['phone'] ?? null;
    //
    // 必填欄位檢查
    if (empty($name) || empty($password) || empty($account)) {
        http_response_code(400);
        echo json_encode(['message' => '姓名、電子信箱與密碼為必填。']);
        exit();
    }
    //
    // 進行資料驗證
    if (!filter_var($account, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['message' => '電子郵件格式不正確。']);
        exit();
    }
    //
    try {
        $safe_name = mysqli_real_escape_string($mysqli, $name);
        $safe_password = mysqli_real_escape_string($mysqli, $password);
        $safe_account = mysqli_real_escape_string($mysqli, $account);
        $safe_phone = mysqli_real_escape_string($mysqli, $phone);
        //
        $check_account_sql = "SELECT * FROM `users` WHERE `account` = '{$safe_account}'";
        $check_result = mysqli_query($mysqli, $check_account_sql);
        //
        if (!$check_result) {
            throw new Exception("SQL 查詢失敗: " . mysqli_error($mysqli));
        }
        //
        if (mysqli_num_rows($check_result) > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['message' => '此電子信箱已被註冊。']);
            mysqli_free_result($check_result);
            exit();
        }
        // ✅ 釋放結果集
        mysqli_free_result($check_result);
        //
        // 密碼加密
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $safe_hashed_password = mysqli_real_escape_string($mysqli, $hashed_password);

        $insert_sql = "INSERT INTO `users` (`name`, `password`, `account`, `phone`) VALUES ('{$safe_name}', '{$safe_hashed_password}', '{$safe_account}', '{$safe_phone}')";
        //
        $insert_result = mysqli_query($mysqli, $insert_sql);
        //
        if ($insert_result) {
            // 成功回應
            http_response_code(201); // Created
            echo json_encode(['message' => '註冊成功！']);
        } else {
            throw new Exception("SQL 插入失敗: " . mysqli_error($mysqli));
        }
        // ✅ 關閉連線
        mysqli_close($mysqli);
        //
    } catch (Exception $e) {
        // 處理資料庫錯誤
        http_response_code(500); // Internal Server Error
        echo json_encode(['message' => '伺服器內部錯誤，請稍後再試。']);
        error_log("SQL 錯誤: " . $e->getMessage());
        // 在發生錯誤時，確保連線被關閉
        if (isset($mysqli)) {
            mysqli_close($mysqli);
        }
    }
?>