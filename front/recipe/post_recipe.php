<?php
//新增食譜功能
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/functions.php'; 
  require_once __DIR__ . '/../../common/conn.php';

  session_start();

  try {
    require_method('POST');

    $loggedInUser = checkUserLoggedIn();
    if (!$loggedInUser) {
      throw new Exception('使用者未登入，禁止操作', 401);
    }


    $data = get_json_input();

    $toIntOrNull = fn($v) => (isset($v) && $v !== '' && is_numeric($v)) ? (int)$v : null;
    $toStrOrNull = fn($v) => (isset($v) && $v !== '') ? (string)$v : null;

    $user_id            = $loggedInUser['user_id']; 
    $manager_id          = $toIntOrNull($data['manager_id'] ?? null); 
    $recipe_category_id = $toIntOrNull($data['recipe_category_id'] ?? null);
    
    $status_code        = is_numeric($data['status'] ?? 3) && in_array((int)($data['status'] ?? 3), [0,1,2,3], true) ? (int)$data['status'] : 3;

    // 欄位驗證
    if ($status_code === 0 || $status_code === 1) {
      $errors = [];
      if (empty(trim($data['name'] ?? ''))) $errors[] = '請輸入食譜名稱';
      if (!empty($errors)) {
        throw new Exception('驗證失敗', 400); 
      }
    }

    // SQL 操作
    $sql = "INSERT INTO `recipe`
(`user_id`, `manager_id`, `recipe_category_id`, `name`, `content`, `serving`, `image`, `cooked_time`, `status`, `tag`, `views`, `created_at`)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $types = "iiisssssisi";

    $params = [
      $user_id, 
      $manager_id, 
      $recipe_category_id,
      $data['name'] ?? '', $data['content'] ?? '', $data['serving'] ?? null,
      $data['image'] ?? '', $data['cooked_time'] ?? null, $status_code, $data['tag'] ?? '',
      $toIntOrNull($data['views'] ?? 0)
    ];

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

    // 3. 儲存食材到 `ingredient_item` 資料表（包含模糊比對）
    if (!empty($ingredients)) {
        // 從資料庫中一次性讀取所有現有的食材名稱和 ID
        $all_db_ingredients = [];
        $ingredients_sql = "SELECT `ingredient_id`, `name` FROM `ingredients`";
        $result_ingredients_db = $mysqli->query($ingredients_sql);
        if ($result_ingredients_db) {
            while ($row = $result_ingredients_db->fetch_assoc()) {
                $all_db_ingredients[] = $row;
            }
            $result_ingredients_db->free();
        }

        // 設置相似度門檻，你可以根據需要調整
        $similarity_threshold = 50;

        foreach ($ingredients as $item) {
            $user_ingredient_name = trim($item['name'] ?? null);
            $serving = $item['amount'] ?? null; // **修正: 前端傳送的是 'amount'**
            
            if (empty($user_ingredient_name)) {
                continue;
            }
            
            $db_ingredient_id = 'NULL';
            $final_ingredient_name = $user_ingredient_name;

            $best_match_id = null;
            $highest_similarity = 0;

            foreach ($all_db_ingredients as $db_item) {
                $db_name = trim($db_item['name']);
                $similarity = 0;
                similar_text($user_ingredient_name, $db_name, $similarity);

                if ($similarity > $highest_similarity && $similarity >= $similarity_threshold) {
                    $highest_similarity = $similarity;
                    $best_match_id = (int)$db_item['ingredient_id'];
                    $final_ingredient_name = $db_name;
                }
            }
            
            if ($best_match_id !== null) {
                $db_ingredient_id = $best_match_id;
            }
            
            $clean_final_name = get_sql_value($final_ingredient_name, $mysqli);
            $clean_serving = get_sql_value($serving, $mysqli);

            // 如果最終名稱為空，則跳過此筆，避免寫入空資料
            if(empty($final_ingredient_name)) {
                continue;
            }

            $ingredient_item_sql = "INSERT INTO `ingredient_item` (`ingredient_id`, `recipe_id`, `name`, `serving`) VALUES (
                {$db_ingredient_id},
                '{$new_recipe_id}',
                {$clean_final_name},
                {$clean_serving}
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