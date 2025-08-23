<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  // 檢查 $mysqli 變數是否被成功建立
  if (!$mysqli) {
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
      echo json_encode(['status' => 'fail','message' => '管理員名稱、帳號與密碼為必填。']);
      exit();
  }

  // 進行資料驗證
  $pattern = '/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,20}$/';

  if (!preg_match($pattern, $account)) {
    http_response_code(400);
    echo json_encode(['status' => 'fail','message' => '帳號必須為6到20個字元，且包含英文字母與數字。']);
    exit();
  }

  if (!preg_match($pattern, $password)) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => '密碼必須為6到20個字元，且包含英文字母與數字。']);
    exit();
  }


  try {

    $safe_account = mysqli_real_escape_string($mysqli, $account);
    $safe_name = mysqli_real_escape_string($mysqli, $name);
    // $safe_password = mysqli_real_escape_string($mysqli, $password);

    // 檢查帳號是否已被註冊
    $check_account_sql = "SELECT `account` FROM `managers` WHERE `account` = '{$safe_account}'";
    $result_account = mysqli_query($mysqli, $check_account_sql);

    //   if (!$result) {
    //   throw new Exception(mysqli_error($mysqli));
    // }

    if (mysqli_num_rows($result_account) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'fail', 'message' => '此帳號已被註冊。']);
        mysqli_free_result($result_account);
        exit();
    }
    mysqli_free_result($result_account); 

        // ✅ 新增：檢查名稱是否已存在
    $check_name_sql = "SELECT `name` FROM `managers` WHERE `name` = '{$safe_name}'";
    $result_name = mysqli_query($mysqli, $check_name_sql);

    if (mysqli_num_rows($result_name) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'fail', 'message' => '此名稱已被使用。']);
        mysqli_free_result($result_name);
        exit();
    }
    mysqli_free_result($result_name);

    // 密碼加密
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $role = 1;

    // 寫入資料庫
    $insert_sql = "INSERT INTO `managers` (`name`, `password`, `account`, `role`) VALUES ('{$safe_name}', '{$hashed_password}', '{$safe_account}', {$role})";
    $insert_result = mysqli_query($mysqli, $insert_sql);
    
    if ($insert_result) {
        // 成功回應
        http_response_code(201); // Created
        echo json_encode(['status' => 'success','message' => '新增成功！']);
    } else {
        throw new Exception(mysqli_error($mysqli));
    }

    mysqli_close($mysqli);

  } catch (Exception $e) {
      // 處理資料庫錯誤
      http_response_code(500); // Internal Server Error
      echo json_encode(['status' => 'fail','message' => '伺服器內部錯誤，請稍後再試。' . $e->getMessage()]);
  }
?>