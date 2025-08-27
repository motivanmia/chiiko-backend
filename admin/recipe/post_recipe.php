<?php
// /admin/recipe/post_recipe.php

session_start();
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/conn.php';

try {
    require_method('POST');
    if (!isset($_SESSION['manager_id']) || empty($_SESSION['manager_id'])) {
        throw new Exception('未登入或 Session 已過期，請重新登入。', 401);
    }
    $manager_id = (int)$_SESSION['manager_id'];
    $data = get_json_input();

    // 資料清洗
    $toInt = fn($v) => (isset($v) && is_numeric($v)) ? (int)$v : 0;
    $toStr = fn($v) => (string)($v ?? '');
    $recipe_category_id = $toInt($data['recipe_category_id'] ?? 0);
    $name = $toStr($data['name'] ?? null);
    $content = $toStr($data['content'] ?? null);
    $serving = $toStr($data['serving'] ?? null);
    $image = $toStr($data['image'] ?? null);
    $cooked_time = $toStr($data['cooked_time'] ?? null);
    $tag = $toStr($data['tag'] ?? null);
    $views = $toInt($data['views'] ?? 0);
    $status_code = is_numeric($data['status'] ?? 3) && in_array((int)($data['status'] ?? 3), [0, 1, 2, 3], true) ? (int)$data['status'] : 3;
    $ingredients = $data['ingredients'] ?? [];
    $steps = $data['steps'] ?? [];

    // 發布時的驗證
    if ($status_code === 1) { 
        $errors = [];
        if (empty(trim($name))) $errors[] = '請輸入食譜名稱';
        if (empty($image)) $errors[] = '缺少圖片資訊';
        if ($recipe_category_id === 0) $errors[] = '請選擇食譜分類';
        if (!empty($errors)) {
            throw new Exception('發布失敗，請修正以下問題：' . implode('、', $errors), 400);
        }
    }

    $mysqli->begin_transaction();

    // 1. 新增主食譜資料
    $sql_recipe = "INSERT INTO recipe (user_id, manager_id, recipe_category_id, name, content, serving, image, cooked_time, status, tag, views, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt_recipe = $mysqli->prepare($sql_recipe);
    if (!$stmt_recipe) throw new Exception("SQL 預處理失敗: " . $mysqli->error, 500);
    
    $types = "iisssssisi";
    $stmt_recipe->bind_param($types, $manager_id, $recipe_category_id, $name, $content, $serving, $image, $cooked_time, $status_code, $tag, $views);
    if (!$stmt_recipe->execute()) throw new Exception("新增食譜主資料失敗: " . $stmt_recipe->error, 500);
    
    $new_recipe_id = $stmt_recipe->insert_id;
    $stmt_recipe->close();

    // 2. 新增食材資料 (帶有比對和自動新增邏輯)
    if (!empty($ingredients) && is_array($ingredients)) {
        $stmt_find = $mysqli->prepare("SELECT ingredient_id FROM ingredients WHERE name = ?");
        $stmt_add_master = $mysqli->prepare("INSERT INTO ingredients (name) VALUES (?)");
        $stmt_add_item = $mysqli->prepare("INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving) VALUES (?, ?, ?, ?)");
        
        if (!$stmt_find || !$stmt_add_master || !$stmt_add_item) {
            throw new Exception("食材相關 SQL 預處理失敗: " . $mysqli->error, 500);
        }
        
        foreach ($ingredients as $item) {
            $ing_name = trim($item['name'] ?? '');
            $ing_amount = trim($item['amount'] ?? '');
            if (empty($ing_name) || empty($ing_amount)) continue;
            
            $stmt_find->bind_param("s", $ing_name);
            $stmt_find->execute();
            $result = $stmt_find->get_result();
            if ($row = $result->fetch_assoc()) {
                $ing_id = $row['ingredient_id'];
            } else {
                $stmt_add_master->bind_param("s", $ing_name);
                if (!$stmt_add_master->execute()) throw new Exception("自動新增主食材 '{$ing_name}' 失敗: " . $stmt_add_master->error, 500);
                $ing_id = $stmt_add_master->insert_id;
            }
            $result->close();
            
            if ($ing_id) {
                $stmt_add_item->bind_param("iiss", $new_recipe_id, $ing_id, $ing_name, $ing_amount);
                if (!$stmt_add_item->execute()) throw new Exception("新增食材項目關聯失敗: " . $stmt_add_item->error, 500);
            }
        }
        $stmt_find->close();
        $stmt_add_master->close();
        $stmt_add_item->close();
    }

    // 3. 新增步驟資料
    if (!empty($steps) && is_array($steps)) {
        $stmt_step = $mysqli->prepare("INSERT INTO steps (recipe_id, `order`, content) VALUES (?, ?, ?)");
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

    $mysqli->commit();
    send_json(['status' => 'success', 'message' => '食譜新增成功！', 'recipe_id' => $new_recipe_id], 201);

} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli->ping()) $mysqli->rollback();
    $code = is_numeric($e->getCode()) && $e->getCode() > 300 ? $e->getCode() : 500;
    send_json(['status' => 'fail', 'message' => $e->getMessage() ?: '伺服器發生未預期錯誤'], $code);
} finally {
    if (isset($mysqli)) $mysqli->close();
}
?>
