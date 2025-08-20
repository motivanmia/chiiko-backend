<?php
// --- 步驟 1: 引入所有必要的共用檔案 ---
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';

try {
    // --- 步驟 2: 驗證是否有檔案被上傳 ---
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('沒有收到圖片檔案或上傳過程中發生錯誤。');
    }

    $file = $_FILES['image'];

    // --- 步驟 3: 安全性檢查 - 驗證檔案類型 ---
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new RuntimeException('不支援的檔案格式，僅限 JPG, PNG, GIF。');
    }

    // --- 步驟 4: 準備儲存路徑並確保資料夾存在 (⭐️ 核心修正) ---
    // 我們不再使用 __DIR__ 來猜測相對路徑，
    // 而是直接使用 MAMP 伺服器定義的 Document Root 作為基礎，
    // 然後在後面接上 '/uploads/'。這是一個絕對可靠的儲存路徑。
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    
    if (!is_dir($upload_dir)) {
        // 0777 是一個比較寬鬆的權限，確保 PHP 有權限寫入檔案
        mkdir($upload_dir, 0777, true);
    }

    // --- 步驟 5: 產生一個獨一無二的新檔名 ---
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = 'recipe_' . uniqid() . '.' . $extension;
    $target_path = $upload_dir . $new_filename;

    // --- 步驟 6: 將暫存檔案移動到我們的最終目的地 ---
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new RuntimeException('移動已上傳的檔案失敗，請檢查伺服器資料夾權限。');
    }

    // --- 步驟 7: 成功後，只回傳相對路徑 (檔名) ---
    http_response_code(200); // 200 OK
    echo json_encode([
        'message' => '圖片上傳成功！',
        'imagePath' => $new_filename // 我們只回傳檔名，並把 key 改成 imagePath 更語意化
    ]);

} catch (RuntimeException $e) {
    // --- 統一的錯誤處理 ---
    http_response_code(400); // 400 Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}
?>