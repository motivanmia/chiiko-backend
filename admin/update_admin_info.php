<?php
  require_once __DIR__ . '/../common/cors.php';
  require_once __DIR__ . '/../common/conn.php';
  require_once __DIR__ . '/../common/functions.php';

  require_method('POST');

  // 檢查是否已登入
  if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
      http_response_code(401); // Unauthorized
      echo json_encode(['status' => 'fail', 'message' => '請先登入。']);
      exit();
  }

  // 獲取前端 JSON 資料
  $data = json_decode(file_get_contents('php://input'), true);

  $loggedInRole = $_SESSION['role'];
  $loggedInManagerId = $_SESSION['manager_id']; // 取得當前登入者的 ID

  $manager_id = $data['manager_id'] ?? null;
  $newName = $data['name'] ?? null;
  $newAccount = $data['account'] ?? null;
  $newPassword = $data['password'] ?? null;

  // 檢查必填欄位
  if (empty($manager_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'fail', 'message' => '缺少管理員ID。']);
    exit();
  }

  try {
  // 建立更新語句和欄位陣列
    $updateFields = [];
  // 對 manager_id 進行轉義，防止 SQL 注入
    $safeManagerId = mysqli_real_escape_string($mysqli, $manager_id);

  // 1. 處理名稱更新
  if (!empty($newName)) {
    $safeNewName = mysqli_real_escape_string($mysqli, $newName);

    // 新增：檢查新名稱是否已存在
    $sql_check_name = "SELECT `manager_id` FROM `managers` WHERE `name` = '$safeNewName' AND `manager_id` != '$safeManagerId'";
    $result_check_name = mysqli_query($mysqli, $sql_check_name);

    if (mysqli_num_rows($result_check_name) > 0) {
      http_response_code(409); // Conflict
      echo json_encode(['status' => 'fail', 'message' => '名稱已被使用，請選擇其他名稱。']);
      exit();
    }

    $updateFields[] = "`name` = '$safeNewName'";
  }

        

      // 2. 處理帳號更新 (只允許超級管理員修改)
      if (!empty($newAccount) && $loggedInRole == 0) {
          $safeNewAccount = mysqli_real_escape_string($mysqli, $newAccount);

          // 檢查新帳號是否已存在
          $sql_check = "SELECT `manager_id` FROM `managers` WHERE `account` = '$safeNewAccount' AND `manager_id` != '$manager_id'";
          $result_check = mysqli_query($mysqli, $sql_check);
          
          if (mysqli_num_rows($result_check) > 0) {
              http_response_code(409); // Conflict
              echo json_encode(['status' => 'fail', 'message' => '新帳號已被使用，請選擇其他帳號。']);
              exit();
          }
      
          $updateFields[] = "`account` = '$safeNewAccount'";
      }

      // 3. 處理密碼更新
      if (!empty($newPassword)) {
          $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
          $safeHashedPassword = mysqli_real_escape_string($mysqli, $hashedPassword);
          $updateFields[] = "`password` = '$safeHashedPassword'";
      }

      // 如果沒有任何欄位需要更新，直接回傳成功
      if (empty($updateFields)) {
          http_response_code(200);
          echo json_encode(['status' => 'success', 'message' => '沒有任何資料被更新。']);
          exit();
      }

      // 組合 SQL 語句
      $sql = "UPDATE `managers` SET " . implode(', ', $updateFields) . " WHERE `manager_id` = '$manager_id'";

      // 執行 SQL 查詢
      $result = mysqli_query($mysqli, $sql);

      if ($result) {
          if (mysqli_affected_rows($mysqli) > 0) {
            if (!empty($newName) && $manager_id == $loggedInManagerId) {
                $_SESSION['name'] = $newName;
            }
              // 更新成功
              http_response_code(200);
              echo json_encode(['status' => 'success', 'message' => '管理員資料已成功更新！']);
          } else {
              // 資料沒有變動
              http_response_code(200);
              echo json_encode(['status' => 'success', 'message' => '資料沒有任何變動。']);
          }
      } else {
          // 查詢失敗
          http_response_code(500);
          echo json_encode(['status' => 'fail', 'message' => '伺服器內部錯誤，請稍後再試。']);
      }
      
      mysqli_close($mysqli);

  } catch (Exception $e) {
      http_response_code(500); // Internal Server Error
      echo json_encode(['status' => 'fail', 'message' => '伺服器內部錯誤，請稍後再試。']);
  }
?>