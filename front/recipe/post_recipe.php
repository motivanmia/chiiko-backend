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
    $views = $data['views'] ?? 0;
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
                (`user_id`, `manager_id`, `recipe_category_id`, `name`, `content`, `serving`, `image`, `cooked_time`, `status`, `tag`, `views`, `created_at`)
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
                " . get_sql_value($views, $mysqli) . ",
                
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
    // 區域正規化：折疊全形/半形空白為單一半形空白
    $norm = function (string $s): string {
        $s = trim($s);
        return preg_replace('/[\x{3000}\s]+/u', ' ', $s);
    };

    foreach ($ingredients as $item) {
        $rawName = isset($item['name']) ? (string)$item['name'] : '';
        $name = $norm($rawName);
        $amount = isset($item['amount']) ? trim((string)$item['amount']) : '';

        // 名稱或數量為空就跳過
        if ($name === '' || $amount === '') continue;

        $ingredient_id = null;

        // 先做精準比對（不分大小寫）
        $esc = $mysqli->real_escape_string($name);
        $sql1 = "
            SELECT ingredient_id
            FROM ingredients
            WHERE LOWER(name) = LOWER('{$esc}')
            LIMIT 1
        ";
        $res1 = $mysqli->query($sql1);
        if ($res1 === false) {
            throw new Exception('精準查詢失敗：' . $mysqli->error, 500);
        }
        if ($row = $res1->fetch_assoc()) {
            $ingredient_id = (int)$row['ingredient_id'];
        }
        $res1->free();

        // 若沒找到再用模糊比對，並用智慧排序挑最接近的一筆
        if ($ingredient_id === null) {
            $sql2 = "
                SELECT ingredient_id, name
                FROM ingredients
                WHERE name LIKE '%{$esc}%'
                ORDER BY
                  CASE WHEN INSTR(name, '{$esc}') > 0 THEN 0 ELSE 1 END,
                  ABS(CHAR_LENGTH(name) - CHAR_LENGTH('{$esc}')) ASC,
                  CASE WHEN INSTR('{$esc}', name) > 0 THEN 0 ELSE 1 END,
                  INSTR(name, '{$esc}') ASC,
                  name ASC
                LIMIT 1
            ";
            $res2 = $mysqli->query($sql2);
            if ($res2 === false) {
                throw new Exception('模糊查詢失敗：' . $mysqli->error, 500);
            }
            if ($row2 = $res2->fetch_assoc()) {
                $ingredient_id = (int)$row2['ingredient_id'];
            }
            $res2->free();
        }

        // 寫入 ingredient_item（ingredient_id 可能為 NULL）
        $ingId = $ingredient_id === null ? 'NULL' : (int)$ingredient_id;
        $safeName = $mysqli->real_escape_string($rawName); // 保留使用者原輸入名稱
        $safeAmount = $mysqli->real_escape_string($amount);

        $sql_ins = "
            INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving)
            VALUES ({$new_recipe_id}, {$ingId}, '{$safeName}', '{$safeAmount}')
        ";
        if (!$mysqli->query($sql_ins)) {
            throw new Exception('新增食材項目失敗：' . $mysqli->error, 500);
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