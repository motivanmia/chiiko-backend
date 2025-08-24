<?php
  // 步驟 1: 遵循專案的標準引入順序
  // (路徑是 ../../../ 因為它在 admin/recipe/ 內)
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/functions.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/conn.php'; 

  try {
    // 步驟 2: 使用輔助函式檢查請求方法
    require_method('POST');

    // 步驟 3: 驗證是否有 'image' 檔案被上傳
    if (!isset($_FILES['image'])) {
        throw new RuntimeException('請求中未包含名為 "image" 的檔案欄位。');
    }
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        // 根據錯誤碼給出更詳細的提示
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => '檔案大小超過了 php.ini 的限制。',
            UPLOAD_ERR_FORM_SIZE  => '檔案大小超過了 HTML 表單的限制。',
            UPLOAD_ERR_PARTIAL    => '檔案只有部分被上傳。',
            UPLOAD_ERR_NO_FILE    => '沒有檔案被上傳。',
            UPLOAD_ERR_NO_TMP_DIR => '找不到暫存資料夾。',
            UPLOAD_ERR_CANT_WRITE => '檔案寫入磁碟失敗。',
            UPLOAD_ERR_EXTENSION  => '一個 PHP 擴充功能停止了檔案上傳。',
        ];
        $error_message = $upload_errors[$_FILES['image']['error']] ?? '未知的上傳錯誤。';
        throw new RuntimeException($error_message);
    }

    // 步驟 4: 使用您 functions.php 中現有的輔助函式來處理檔案上傳
    // handleFileUpload 會自動處理命名、檢查格式、移動檔案，並回傳新檔名
    $saved_filename = handleFileUpload($_FILES['image']);

    // 步驟 5: 檢查檔案是否成功儲存
    if ($saved_filename) {
      // 如果成功，使用 send_json 回傳標準的成功訊息
      send_json([
        'status'    => 'success',
        'message'   => '圖片上傳成功',
        'imagePath' => $saved_filename 
      ], 200);
    } else {
      // 如果 handleFileUpload 回傳 null，代表儲存失敗
      throw new RuntimeException('儲存圖片失敗，請確認檔案格式是否為 JPG, PNG, GIF，且伺服器 /uploads 資料夾具有寫入權限。');
    }

  } catch (RuntimeException $e) {
    // 步驟 6: 統一的錯誤處理出口
    send_json([
        'status' => 'fail',
        'message' => $e->getMessage()
    ], 400); // 400 Bad Request
  }
?>