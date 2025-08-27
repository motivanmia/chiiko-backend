<?php
// /admin/recipe/update_recipe.php

session_start();
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/conn.php';

try {
    require_method('POST');
    $data = get_json_input();
    
    if (!isset($_SESSION['manager_id'])) {
        throw new Exception('未登入或 Session 已過期，請重新登入。', 401);
    }

    $recipe_id = isset($data['recipe_id']) && is_numeric($data['recipe_id']) ? (int)$data['recipe_id'] : null;
    if (!$recipe_id) throw new Exception('缺少 recipe_id', 400);

    // ---- 啟動資料庫交易 ----
    $mysqli->begin_transaction();

    // ==========================================================
    //  1. 更新主食譜資料 (沿用您靈活的動態更新邏輯)
    // ==========================================================
    $fields = [
        'recipe_category_id',
        'name',
        'content',
        'serving',
        'image',
        'cooked_time',
        'status',
        'tag',
    ];
    $set_clauses = [];
    foreach ($fields as $k) {
        if (array_key_exists($k, $data)) {
            $value = $mysqli->real_escape_string($data[$k]);
            $set_clauses[] = "`$k` = '{$value}'";
        }
    }

    if (!empty($set_clauses)) {
        $sql_update_recipe = "UPDATE recipe SET " . implode(', ', $set_clauses) . " WHERE recipe_id = {$recipe_id}";
        if (!$mysqli->query($sql_update_recipe)) {
            throw new Exception('更新食譜主資料失敗：' . $mysqli->error, 500);
        }
    }

    // ==========================================================
    // 【✅ 核心修正 ✅】
    //  2. 處理食材和步驟的更新 (採用「先刪後增」策略)
    // ==========================================================
    
    // 只有在前端 payload 中明確帶有 'ingredients' 鍵時，才執行更新
    if (array_key_exists('ingredients', $data)) {
        // a. 刪除所有舊的食材關聯
        $mysqli->query("DELETE FROM ingredient_item WHERE recipe_id = {$recipe_id}");
        if ($mysqli->errno) throw new Exception('刪除舊食材關聯失敗：' . $mysqli->error, 500);

        // b. 重新插入新的食材關聯 (邏輯與 post_recipe.php 完全相同)
        $ingredients = $data['ingredients'] ?? [];
        if (!empty($ingredients) && is_array($ingredients)) {
            foreach ($ingredients as $item) {
                $ing_name = trim($item['name'] ?? ''); $ing_amount = trim($item['amount'] ?? '');
                if (empty($ing_name) || empty($ing_amount)) continue;
                
                $ing_id = null;
                $safe_ing_name = $mysqli->real_escape_string($ing_name);
                $result_find = $mysqli->query("SELECT ingredient_id FROM ingredients WHERE name = '{$safe_ing_name}'");
                
                if ($result_find && $row = $result_find->fetch_assoc()) {
                    $ing_id = $row['ingredient_id'];
                    $result_find->free();
                } else {
                    $sql_add_master = "INSERT INTO ingredients (name) VALUES ('{$safe_ing_name}')";
                    if (!$mysqli->query($sql_add_master)) throw new Exception("自動新增主食材 '{$ing_name}' 失敗: " . $mysqli->error, 500);
                    $ing_id = $mysqli->insert_id;
                }
                
                if ($ing_id) { 
                    $safe_ing_amount = $mysqli->real_escape_string($ing_amount);
                    $sql_add_item = "INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving) VALUES ('{$recipe_id}', '{$ing_id}', '{$safe_ing_name}', '{$safe_ing_amount}')";
                    if (!$mysqli->query($sql_add_item)) throw new Exception("新增食材項目關聯失敗: " . $mysqli->error, 500);
                }
            }
        }
    }

    // 只有在前端 payload 中明確帶有 'steps' 鍵時，才執行更新
    if (array_key_exists('steps', $data)) {
        // a. 刪除所有舊的步驟
        $mysqli->query("DELETE FROM steps WHERE recipe_id = {$recipe_id}");
        if ($mysqli->errno) throw new Exception('刪除舊步驟失敗：' . $mysqli->error, 500);

        // b. 重新插入新的步驟
        $steps = $data['steps'] ?? [];
        if (!empty($steps) && is_array($steps)) {
            foreach ($steps as $step) {
                $step_content = $step['content'] ?? null;
                $step_order = $step['order'] ?? 0;
                if ($step_content) {
                    $safe_step_content = $mysqli->real_escape_string($step_content);
                    $sql_step = "INSERT INTO steps (recipe_id, `order`, content) VALUES ('{$recipe_id}', '{$step_order}', '{$safe_step_content}')";
                    if (!$mysqli->query($sql_step)) throw new Exception("新增步驟失敗: " . $mysqli->error, 500);
                }
            }
        }
    }

    // ---- 提交交易 ----
    $mysqli->commit();

    send_json(['status'=>'success','message'=>'食譜已成功更新']);

} catch (Throwable $e) {
    // 如果中間任何一步出錯，就回滾所有操作
    if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->rollback();
    }
    
    $code = $e->getCode() ?: 500;
    $code = ($code>=400 && $code<600) ? $code : 500;
    send_json(['status'=>'fail','message'=>$e->getMessage()], $code);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>