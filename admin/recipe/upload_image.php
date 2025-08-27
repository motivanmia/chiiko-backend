<?php
// ✅ 步驟 1: 引入必要的檔案
// (路徑是 ../../ 因為它在 admin/recipe/ 內)
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

try {
    // 步驟 2: 使用輔助函式檢查請求方法
    require_method('POST');

    // 步驟 3: 驗證是否有 'image' 檔案被上傳
    if (!isset($_FILES['image'])) {
        throw new Exception('請求中未包含名為 "image" 的檔案欄位。', 400);
    }
    // handleFileUpload 內部會處理錯誤，這裡可以簡化
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        throw new Exception('檔案上傳時發生未知錯誤。', 500);
    }

    // ✅ 步驟 4: 直接呼叫 functions.php 中現有的輔助函式來處理檔案上傳
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
      // 如果 handleFileUpload 回傳 null 或 false，代表儲存失敗
      throw new Exception('儲存圖片失敗，請確認檔案格式是否為 JPG, PNG, GIF 等支援的格式。', 400);
    }

} catch (Exception $e) {
    // 步驟 6: 統一的錯誤處理出口
    $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
    send_json([
        'status' => 'fail',
        'message' => $e->getMessage()
    ], $code);
}
?>