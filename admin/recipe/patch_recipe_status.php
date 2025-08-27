<?php
// /admin/recipe/patch_recipe_status.php

// ✅ 1. 先引入所有必要的文件
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/conn.php';

// ✅ 2. 在所有引入都完成之後，才啟動 session
session_start();

try {
    // ✅ 3. 在 try 塊的最開始，執行所有前置檢查
    
    // a. 檢查請求方法
    require_method('POST');

    // b. 檢查管理員登入狀態
    if (!isset($_SESSION['manager_id'])) {
        throw new Exception('未經授權，請先登入。', 401);
    }

    // c. 獲取並解析 JSON 輸入
    $data = get_json_input();

    // d. 嚴謹地驗證傳入的資料
    $recipe_id = isset($data['id']) && is_numeric($data['id']) ? (int)$data['id'] : null;
    $new_status = isset($data['newStatus']) && is_numeric($data['newStatus']) ? (int)$data['newStatus'] : null;

    if (!$recipe_id || $new_status === null) {
        throw new Exception('缺少有效的食譜 ID 或新的狀態碼。', 400);
    }

    // e. 檢查狀態碼是否在允許的範圍內 (0, 1, 2, 3)
    if (!in_array($new_status, [0, 1, 2, 3], true)) {
        throw new Exception('無效的狀態碼。', 400);
    }

    // --- 執行資料庫更新 ---
    $sql = "UPDATE recipe SET status = ? WHERE recipe_id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL 預處理失敗: " . $mysqli->error, 500);
    }

    $stmt->bind_param("ii", $new_status, $recipe_id);
    
    if (!$stmt->execute()) {
        throw new Exception("更新食譜狀態失敗: " . $stmt->error, 500);
    }
    
    $stmt->close();
    
    // 回傳成功的訊息
    send_json(['status' => 'success', 'message' => '食譜狀態已成功更新！'], 200);

} catch (Exception $e) {
    // 統一的錯誤處理
    $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
    send_json([
        'status'  => 'fail',
        'message' => $e->getMessage()
    ], $code);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>