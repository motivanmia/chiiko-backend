<?php
// 後台 - 新增食譜功能（管理員發佈）- 採用「統合式」設計
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/conn.php';

try {
    require_method('POST');
    $data = get_json_input();

    // ---- 數據清洗與驗證 ----
    $toIntOrNull = fn($v) => (isset($v) && $v !== '' && is_numeric($v)) ? (int)$v : null;
    $toStrOrNull = fn($v) => (isset($v) && $v !== '') ? (string)$v : null;

    $manager_id = $toIntOrNull($data['manager_id'] ?? null);
    if (!$manager_id) {
        throw new Exception('後台新增食譜必須指定管理員 ID', 400);
    }

    $recipe_category_id = $toIntOrNull($data['recipe_category_id'] ?? null);
    $name = $toStrOrNull($data['name'] ?? '');
    $content = $toStrOrNull($data['content'] ?? '');
    $serving = $toStrOrNull($data['serving'] ?? null);
    $image = $toStrOrNull($data['image'] ?? '');
    $cooked_time = $toStrOrNull($data['cooked_time'] ?? null);
    $tag = $toStrOrNull($data['tag'] ?? '');
    $status_code = is_numeric($data['status'] ?? 3) && in_array((int)($data['status'] ?? 3), [0, 1, 2, 3], true)
        ? (int)$data['status'] : 3;

    // **【關鍵】** 接收食材和步驟陣列
    $ingredients = $data['ingredients'] ?? [];
    $steps = $data['steps'] ?? [];

    if ($status_code === 0 || $status_code === 1) {
        $errors = [];
        if (empty(trim($name))) $errors[] = '請輸入食譜名稱';
        if (empty($image)) $errors[] = '缺少圖片資訊';
        if (!empty($errors)) {
            throw new Exception('驗證失敗：' . implode(', ', $errors), 400);
        }
    }

    // ---- 啟動交易 ----
    $mysqli->begin_transaction();

    // 1. 新增主食譜資料 (使用安全的 Prepared Statements)
    $sql_recipe = "INSERT INTO recipe
            (user_id, manager_id, recipe_category_id, name, content, serving, image, cooked_time, status, tag, created_at)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt_recipe = $mysqli->prepare($sql_recipe);
    if (!$stmt_recipe) throw new Exception("SQL 預處理失敗: " . $mysqli->error, 500);
    
    $types = "iisssssis";
    $stmt_recipe->bind_param($types, $manager_id, $recipe_category_id, $name, $content, $serving, $image, $cooked_time, $status_code, $tag);
    
    if (!$stmt_recipe->execute()) throw new Exception("新增食譜主資料失敗: " . $stmt_recipe->error, 500);
    
    $new_recipe_id = $stmt_recipe->insert_id;
    $stmt_recipe->close();

    // 2. 新增食材資料
    if (!empty($ingredients) && is_array($ingredients)) {
        $sql_ingredient = "INSERT INTO ingredient_item (recipe_id, name, serving) VALUES (?, ?, ?)";
        $stmt_ingredient = $mysqli->prepare($sql_ingredient);
        if (!$stmt_ingredient) throw new Exception("食材 SQL 預處理失敗: " . $mysqli->error, 500);

        foreach ($ingredients as $item) {
            $ingredient_name = $item['name'] ?? null;
            $ingredient_amount = $item['amount'] ?? null;
            if ($ingredient_name && $ingredient_amount) {
                $stmt_ingredient->bind_param("iss", $new_recipe_id, $ingredient_name, $ingredient_amount);
                if (!$stmt_ingredient->execute()) throw new Exception("新增食材失敗: " . $stmt_ingredient->error, 500);
            }
        }
        $stmt_ingredient->close();
    }

    // 3. 新增步驟資料
    if (!empty($steps) && is_array($steps)) {
        $sql_step = "INSERT INTO steps (recipe_id, `order`, content) VALUES (?, ?, ?)";
        $stmt_step = $mysqli->prepare($sql_step);
        if (!$stmt_step) throw new Exception("步驟 SQL 預處理失敗: " . $mysqli->error, 500);
        
        foreach ($steps as $step) {
            $step_content = $step['content'] ?? null;
            $step_order = $step['order'] ?? 0;
            if ($step_content) {
                $stmt_step->bind_param("iis", $new_recipe_id, $step_order, $step_content);
                if (!$stmt_step->execute()) throw new Exception("新增步驟失敗: " . $stmt_step->error, 500);
            }
        }
        $stmt_step->close();
    }

    // ---- 提交交易 ----
    $mysqli->commit();

    // ---- 成功回應 ----
    send_json([
        'status'    => 'success',
        'message'   => '食譜新增成功！',
        'recipe_id' => $new_recipe_id
    ], 201);

} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->rollback();
    }

    $code = $e->getCode() ?: 500;
    $code = (is_numeric($code) && $code >= 400 && $code < 600) ? $code : 500;
    send_json([
        'status'  => 'fail',
        'message' => $e->getMessage() ?: '伺服器發生未預期錯誤'
    ], $code);

} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>