<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';
  
  require_method('POST');

  // 檢查是否登入
  $user = checkUserLoggedIn();

  if (!$user) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入'
    ], 401);
  }

  $user_id = $user['user_id'];

  // 檢查是否有檔案上傳
  if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => '檔案上傳失敗或沒有檔案']);
    exit();
  }

  // 舊檔案清理
  try {
    // 從資料庫查詢舊的頭像檔名
    $stmt = $mysqli->prepare("SELECT image FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($oldAvatarFile);

    if ($stmt->fetch()) {
      // 檢查舊頭像檔名是否存在
      if ($oldAvatarFile) {
        $uploadDir = '../../uploads/';
        $oldFilePath = $uploadDir . $oldAvatarFile;

        // 檢查檔案是否存在並刪除他
        if (file_exists($oldFilePath)) {
          unlink($oldFilePath);
        }
      }
    }
    $stmt -> close();
  } catch (Exception $e) {
    // 處理資料庫錯誤 但不要中斷檔案上傳
    error_log('Error fetching old avatar:' . $e->getMessage());
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
      $stmt -> close();
    }
  }

  // 用圖片處理函式處理照片上傳 因為是單一檔案上傳 第二個參數傳入false
  $newAvatarFilename = handleFileUpload($_FILES['avatar'], false);

  if ($newAvatarFilename) {
    // 檔案上傳成功 更新資料庫
    $stmt = $mysqli->prepare("UPDATE users SET image = ? WHERE user_id = ?");
    $stmt->bind_param("si", $newAvatarFilename, $user_id);

    if ($stmt->execute()) {
      // 拼接圖檔url
      $newAvatarUrl = IMG_BASE_URL . '/' . $newAvatarFilename;
      // 成功回傳結果
      echo json_encode([
        'status' => 'success',
        'message' => '頭像更新成功',
        'newAvatarUrl' => $newAvatarUrl
      ]);
    }else {
      // 檔案上傳失敗
      echo json_encode(['status' => 'error', 'message' => '檔案驗證或上傳失敗']);
    } 
    $stmt->close();
  } else {
    // 檔案上傳失敗
    echo json_encode(['status' => 'error', 'message' => '檔案驗證或上傳失敗']);
  }
?>