<?php
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/conn.php';

try {
    // 檢查請求方法是否為 POST
    require_method('POST');
    $data = get_json_input();

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        throw new Exception('使用者未登入', 401);
    }

    // 變數初始化與嚴謹的資料驗證
    $name = $data['name'] ?? '';
    $content = $data['content'] ?? '';
    $status_code = $data['status'] ?? 3;
    $manager_id = $data['manager_id'] ?? null;
    $recipe_category_id = $data['recipe_category_id'] ?? null;
    $serving = $data['serving'] ?? null;
    $image = $data['image'] ?? null;
    $cooked_time = $data['cooked_time'] ?? null;
    $tag = $data['tag'] ?? null;
    $steps = $data['steps'] ?? [];
    $ingredients = $data['ingredients'] ?? [];

    if ($status_code == 0 || $status_code == 1) {
        if (empty($name)) {
            throw new Exception('食譜名稱為必填欄位', 400);
        }
    }
    
    // 輔助函式：根據值是 NULL 還是字串來決定是否加上引號
    function get_sql_value($value, $mysqli) {
        if ($value === null) {
            return 'NULL';
        }
        return "'" . $mysqli->real_escape_string($value) . "'";
    }

    // --- 啟動資料庫交易 ---
    $mysqli->begin_transaction();

    // 1. 儲存食譜主資料到 `recipe` 資料表
    $sql = "INSERT INTO `recipe`
                (`user_id`, `manager_id`, `recipe_category_id`, `name`, `content`, `serving`, `image`, `cooked_time`, `status`, `tag`, `created_at`)
            VALUES (
                " . get_sql_value($user_id, $mysqli) . ",
                " . get_sql_value($manager_id, $mysqli) . ",
                " . get_sql_value($recipe_category_id, $mysqli) . ",
                " . get_sql_value($name, $mysqli) . ",
                " . get_sql_value($content, $mysqli) . ",
                " . get_sql_value($serving, $mysqli) . ",
                " . get_sql_value($image, $mysqli) . ",
                " . get_sql_value($cooked_time, $mysqli) . ",
                " . get_sql_value($status_code, $mysqli) . ",
                " . get_sql_value($tag, $mysqli) . ",
                NOW()
            )";
    $result = $mysqli->query($sql);
    if (!$result) {
        throw new Exception("資料庫操作失敗: " . $mysqli->error, 500);
    }
    $new_recipe_id = $mysqli->insert_id;
    if (!$new_recipe_id) {
        throw new Exception("後端未能取得新增食譜的 ID。", 500);
    }
    
    // 2. 儲存步驟到 `steps` 資料表
    if (!empty($steps)) {
        foreach ($steps as $step) {
            $clean_content = get_sql_value($step['content'], $mysqli);
            $step_order = (int)$step['order'];
            $step_sql = "INSERT INTO `steps` (`recipe_id`, `order`, `content`) VALUES (
                '{$new_recipe_id}',
                '{$step_order}',
                {$clean_content}
            )";
            if (!$mysqli->query($step_sql)) {
                throw new Exception("新增步驟失敗: " . $mysqli->error, 500);
            }
        }
    }

    // 3. 儲存食材到 `ingredient_item` 資料表
    if (!empty($ingredients)) {
        foreach ($ingredients as $item) {
            $user_ingredient_name = trim($item['name'] ?? null);
            $amount = $item['amount'] ?? null;
            
            if (empty($user_ingredient_name)) {
                continue;
            }
            
            $clean_name = get_sql_value($user_ingredient_name, $mysqli);
            $clean_amount = get_sql_value($amount, $mysqli);

            $ingredient_item_sql = "INSERT INTO `ingredient_item` (`ingredient_id`, `recipe_id`, `name`, `serving`) VALUES (
                NULL,
                '{$new_recipe_id}',
                {$clean_name},
                {$clean_amount}
            )";
            
            if (!$mysqli->query($ingredient_item_sql)) {
                throw new Exception("新增食材項目失敗: " . $mysqli->error, 500);
            }
        }
    }

    // --- 提交交易，如果所有步驟都成功 ---
    $mysqli->commit();
    
    // 傳回成功訊息
    send_json([
        'status' => 'success',
        'message' => '食譜新增成功！',
        'recipe_id' => $new_recipe_id
    ], 201);
    
} catch (Throwable $e) {
    // --- 錯誤處理：回滾交易 ---
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    
    $code = $e->getCode() ?: 500;
    $code = is_numeric($code) && $code >= 400 && $code < 600 ? $code : 500;
    send_json([
        'status' => 'fail',
        'message' => $e->getMessage() ?: '伺服器發生未預期錯誤',
    ], $code);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>