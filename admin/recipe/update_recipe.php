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
  // 因為我們要同時操作多張表，必須使用交易來確保資料一致性
  $mysqli->begin_transaction();

  // ==========================================================
  //  1. 更新主食譜資料 (沿用您靈活的動態更新邏輯)
  // ==========================================================
  $fields = [
    'recipe_category_id' => 'i',
    'name'               => 's',
    'content'            => 's',
    'serving'            => 's',
    'image'              => 's',
    'cooked_time'        => 's',
    'status'             => 'i',
    'tag'                => 's',
  ];
  $set = [];
  $types = '';
  $params = [];

  foreach ($fields as $k => $t) {
    if (array_key_exists($k, $data)) {
      $set[] = "`$k` = ?";
      $types .= $t;
      $params[] = $data[$k];
    }
  }

  // 只有在前端確實傳來了 recipe 主表的欄位時，才執行 UPDATE
  if (!empty($set)) {
    $sql_update_recipe = "UPDATE recipe SET " . implode(', ', $set) . " WHERE recipe_id = ?";
    $types .= 'i';
    $params[] = $recipe_id;

    $stmt_update = $mysqli->prepare($sql_update_recipe);
    $stmt_update->bind_param($types, ...$params);
    if (!$stmt_update->execute()) throw new Exception('更新食譜主資料失敗：' . $stmt_update->error, 500);
    $stmt_update->close();
  }

  // ==========================================================
  // 【✅ 核心修正 ✅】
  //  2. 處理食材和步驟的更新 (採用「先刪後增」策略)
  // ==========================================================
  
  // 只有在前端 payload 中明確帶有 'ingredients' 鍵時，才執行更新
  if (array_key_exists('ingredients', $data)) {
    // a. 刪除所有舊的食材關聯
    $stmt_del_ing = $mysqli->prepare("DELETE FROM ingredient_item WHERE recipe_id = ?");
    $stmt_del_ing->bind_param("i", $recipe_id);
    $stmt_del_ing->execute();
    $stmt_del_ing->close();

    // b. 重新插入新的食材關聯 (邏輯與 post_recipe.php 完全相同)
    $ingredients = $data['ingredients'] ?? [];
    if (!empty($ingredients) && is_array($ingredients)) {
        $stmt_find = $mysqli->prepare("SELECT ingredient_id FROM ingredients WHERE name = ?");
        $stmt_add_master = $mysqli->prepare("INSERT INTO ingredients (name) VALUES (?)");
        $stmt_add_item = $mysqli->prepare("INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving) VALUES (?, ?, ?, ?)");
        
        foreach ($ingredients as $item) {
            $ing_name = trim($item['name'] ?? ''); $ing_amount = trim($item['amount'] ?? '');
            if (empty($ing_name) || empty($ing_amount)) continue;
            
            $stmt_find->bind_param("s", $ing_name); $stmt_find->execute(); $result = $stmt_find->get_result();
            if ($row = $result->fetch_assoc()) { $ing_id = $row['ingredient_id']; } else {
                $stmt_add_master->bind_param("s", $ing_name); $stmt_add_master->execute(); $ing_id = $stmt_add_master->insert_id;
            }
            $result->close();
            
            if ($ing_id) { $stmt_add_item->bind_param("iiss", $recipe_id, $ing_id, $ing_name, $ing_amount); $stmt_add_item->execute(); }
        }
        $stmt_find->close(); $stmt_add_master->close(); $stmt_add_item->close();
    }
  }

  // 只有在前端 payload 中明確帶有 'steps' 鍵時，才執行更新
  if (array_key_exists('steps', $data)) {
    // a. 刪除所有舊的步驟
    $stmt_del_steps = $mysqli->prepare("DELETE FROM steps WHERE recipe_id = ?");
    $stmt_del_steps->bind_param("i", $recipe_id);
    $stmt_del_steps->execute();
    $stmt_del_steps->close();

    // b. 重新插入新的步驟
    $steps = $data['steps'] ?? [];
    if (!empty($steps) && is_array($steps)) {
        $stmt_step = $mysqli->prepare("INSERT INTO steps (recipe_id, `order`, content) VALUES (?, ?, ?)");
        foreach ($steps as $step) {
            $step_content = $step['content'] ?? null; $step_order = $step['order'] ?? 0;
            if ($step_content) { $stmt_step->bind_param("iis", $recipe_id, $step_order, $step_content); $stmt_step->execute(); }
        }
        $stmt_step->close();
    }
  }

  // ---- 提交交易 ----
  $mysqli->commit();

  send_json(['status'=>'success','message'=>'食譜已成功更新']);

} catch (Throwable $e) {
  // 如果中間任何一步出錯，就回滾所有操作
  if (isset($mysqli) && $mysqli->ping()) $mysqli->rollback();

  $code = $e->getCode() ?: 500;
  $code = ($code>=400 && $code<600) ? $code : 500;
  send_json(['status'=>'fail','message'=>$e->getMessage()], $code);
} finally {
  if (isset($mysqli)) $mysqli->close();
}
?>