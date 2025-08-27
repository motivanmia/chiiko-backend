<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 允許跨域請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 驗證使用者是否登入
    if (!isset($_SESSION['user_id'])) {
        send_json(['status' => 'fail', 'message' => '使用者未登入'], 401);
        exit;
    }
    $user_id = $_SESSION['user_id'];

    // 驗證請求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        send_json(['status' => 'fail', 'message' => '不允許的請求方法'], 405);
        exit;
    }
    
    // 從請求主體獲取 recipe_id
    $data = json_decode(file_get_contents('php://input'), true);
    $recipe_id = isset($data['recipe_id']) ? (int)$data['recipe_id'] : null;

    // 驗證 recipe_id 的有效性
    if ($recipe_id === null || !is_numeric($recipe_id) || $recipe_id <= 0) {
        send_json(['status' => 'fail', 'message' => '無效的食譜 ID'], 400);
        exit;
    }

    // 使用 real_escape_string 進行基本防護
    $clean_recipe_id = $mysqli->real_escape_string($recipe_id);
    $clean_user_id = $mysqli->real_escape_string($user_id);

    // 啟動資料庫交易，確保所有操作要么都成功，要么都失敗
    $mysqli->begin_transaction();

    // 1. 檢查食譜是否存在且屬於當前用戶
    $check_sql = "SELECT `recipe_id` FROM `recipe` WHERE `recipe_id` = '{$clean_recipe_id}' AND `user_id` = '{$clean_user_id}'";
    $result_check = $mysqli->query($check_sql);
    
    if (!$result_check || $result_check->num_rows === 0) {
        $mysqli->rollback();
        send_json(['status' => 'fail', 'message' => '食譜不存在或不屬於此使用者'], 404);
        exit;
    }

    // 2. 刪除所有與該食譜相關的「收藏記錄」
    $delete_favorites_sql = "DELETE FROM `recipe_favorite` WHERE `recipe_id` = '{$clean_recipe_id}'";
    $result_favorites = $mysqli->query($delete_favorites_sql);
    if (!$result_favorites) {
        $mysqli->rollback();
        throw new Exception("SQL 刪除收藏失敗: " . $mysqli->error);
    }
    
    // 3. 刪除所有與該食譜相關的「食材項目」
    $delete_ingredients_sql = "DELETE FROM `ingredient_item` WHERE `recipe_id` = '{$clean_recipe_id}'";
    $result_ingredients = $mysqli->query($delete_ingredients_sql);
    if (!$result_ingredients) {
        $mysqli->rollback();
        throw new Exception("SQL 刪除食材失敗: " . $mysqli->error);
    }
    
    // 4. 接著刪除所有與該食譜相關的「步驟」
    $delete_steps_sql = "DELETE FROM `steps` WHERE `recipe_id` = '{$clean_recipe_id}'";
    $result_steps = $mysqli->query($delete_steps_sql);
    if (!$result_steps) {
        $mysqli->rollback();
        throw new Exception("SQL 刪除步驟失敗: " . $mysqli->error);
    }

    // 5. 最後再刪除食譜本身
    $delete_recipe_sql = "DELETE FROM `recipe` WHERE `recipe_id` = '{$clean_recipe_id}' AND `user_id` = '{$clean_user_id}'";
    $result_recipe = $mysqli->query($delete_recipe_sql);
    if (!$result_recipe) {
        $mysqli->rollback();
        throw new Exception("SQL 刪除食譜失敗: " . $mysqli->error);
    }
    $affected_rows = $mysqli->affected_rows;

    // 6. 如果所有操作都成功，提交交易
    $mysqli->commit();

    // 傳回成功或失敗訊息
    if ($affected_rows > 0) {
        send_json(['status' => 'success', 'message' => '食譜已成功刪除！'], 200);
    } else {
        send_json(['status' => 'fail', 'message' => '食譜不存在或已被刪除'], 404);
    }

} catch (Throwable $e) {
    // 如果任何步驟失敗，回滾交易
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    send_json([
        'status' => 'fail',
        'message' => '伺服器發生錯誤: ' . $e->getMessage()
    ], 500);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>