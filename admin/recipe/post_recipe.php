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
    $sql_recipe = "INSERT INTO recipe (user_id, manager_id, recipe_category_id, name, content, serving, image, cooked_time, status, tag, views, created_at) VALUES (NULL, '{$manager_id}', '{$recipe_category_id}', '{$mysqli->real_escape_string($name)}', '{$mysqli->real_escape_string($content)}', '{$mysqli->real_escape_string($serving)}', '{$mysqli->real_escape_string($image)}', '{$mysqli->real_escape_string($cooked_time)}', '{$status_code}', '{$mysqli->real_escape_string($tag)}', '{$views}', NOW())";
    
    if (!$mysqli->query($sql_recipe)) {
        throw new Exception("新增食譜主資料失敗: " . $mysqli->error, 500);
    }
    
    $new_recipe_id = $mysqli->insert_id;

    // 2. 新增食材資料 (帶有比對和自動新增邏輯)
    if (!empty($ingredients) && is_array($ingredients)) {
        foreach ($ingredients as $item) {
            $ing_name = trim($item['name'] ?? '');
            $ing_amount = trim($item['amount'] ?? '');
            if (empty($ing_name) || empty($ing_amount)) continue;
            
            $ing_id = null;
            $safe_ing_name = $mysqli->real_escape_string($ing_name);
            $sql_find = "SELECT ingredient_id FROM ingredients WHERE name = '{$safe_ing_name}'";
            $result = $mysqli->query($sql_find);

            if ($result && $row = $result->fetch_assoc()) {
                $ing_id = $row['ingredient_id'];
                $result->free();
            } else {
                $sql_add_master = "INSERT INTO ingredients (name) VALUES ('{$safe_ing_name}')";
                if (!$mysqli->query($sql_add_master)) {
                    throw new Exception("自動新增主食材 '{$ing_name}' 失敗: " . $mysqli->error, 500);
                }
                $ing_id = $mysqli->insert_id;
            }
            
            if ($ing_id) {
                $safe_ing_amount = $mysqli->real_escape_string($ing_amount);
                $sql_add_item = "INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving) VALUES ('{$new_recipe_id}', '{$ing_id}', '{$safe_ing_name}', '{$safe_ing_amount}')";
                if (!$mysqli->query($sql_add_item)) {
                    throw new Exception("新增食材項目關聯失敗: " . $mysqli->error, 500);
                }
            }
        }
    }

    // 3. 新增步驟資料
    if (!empty($steps) && is_array($steps)) {
        foreach ($steps as $step) {
            $step_content = $step['content'] ?? null;
            $step_order = $step['order'] ?? 0;
            if ($step_content) {
                $safe_step_content = $mysqli->real_escape_string($step_content);
                $sql_step = "INSERT INTO steps (recipe_id, `order`, content) VALUES ('{$new_recipe_id}', '{$step_order}', '{$safe_step_content}')";
                if (!$mysqli->query($sql_step)) {
                    throw new Exception("新增步驟失敗: " . $mysqli->error, 500);
                }
            }
        }
    }

    $mysqli->commit();
    send_json(['status' => 'success', 'message' => '食譜新增成功！', 'recipe_id' => $new_recipe_id], 201);

} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli->ping()) {
        @$mysqli->rollback();
    }
    $code = is_numeric($e->getCode()) && $e->getCode() > 300 ? $e->getCode() : 500;
    send_json(['status' => 'fail', 'message' => $e->getMessage() ?: '伺服器發生未預期錯誤'], $code);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}