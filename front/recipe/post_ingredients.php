<?php
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

try {
  require_method('POST');
  $data = get_json_input();

  // ---- 基本驗證 ----
  if (!isset($data['recipe_id']) || !is_numeric($data['recipe_id'])) {
    throw new Exception('缺少或不合法的 recipe_id', 400);
  }
  if (!isset($data['ingredients']) || !is_array($data['ingredients'])) {
    throw new Exception('缺少 ingredients 或格式不正確（需為陣列）', 400);
  }

  $recipe_id = (int)$data['recipe_id'];
  $mode = strtolower(trim((string)($data['mode'] ?? 'replace'))); // 預設使用 replace 模式
  if (!in_array($mode, ['append', 'replace'], true)) {
    $mode = 'replace';
  }

  // ---- ⭐️ 核心修改 1: 準備一個查詢 ingredient_id 的 SQL 語句 ----
  $find_id_stmt = $mysqli->prepare("SELECT ingredient_id FROM ingredients WHERE name = ? LIMIT 1");

  // ---- 清洗並處理每一筆食材資料 ----
  $clean_ingredients = [];
  foreach ($data['ingredients'] as $it) {
    $name = isset($it['name']) ? trim((string)$it['name']) : '';
    $amount = isset($it['amount']) ? trim((string)$it['amount']) : '';

    // 只要名稱或數量是空的，就直接跳過這筆資料
    if ($name === '' || $amount === '') {
      continue;
    }

    // ---- ⭐️ 核心修改 2: 執行查詢，實現智能帶入 ID ----
    $ingredient_id = null; // 預設 ingredient_id 為 NULL
    $find_id_stmt->bind_param('s', $name);
    $find_id_stmt->execute();
    $result = $find_id_stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
      // 如果在 ingredients 主表中找到了對應的名稱，就使用它的 ID
      $ingredient_id = (int)$row['ingredient_id'];
    }
    // 如果沒找到，$ingredient_id 會保持為 NULL

    $clean_ingredients[] = [
      'id' => $ingredient_id,
      'name' => $name,
      'amount' => $amount
    ];
  }
  $find_id_stmt->close(); // 關閉查詢語句

  // ---- DB 寫入 ----
  $mysqli->begin_transaction();

  if ($mode === 'replace') {
    $del = $mysqli->prepare("DELETE FROM ingredient_item WHERE recipe_id = ?");
    $del->bind_param('i', $recipe_id);
    $del->execute();
    $del->close();
  }

  $inserted = 0;
  if (!empty($clean_ingredients)) {
    // ---- ⭐️ 核心修改 3: 修正 INSERT 語句，加入 name 欄位 ----
    $ins_stmt = $mysqli->prepare("INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving) VALUES (?, ?, ?, ?)");
    if (!$ins_stmt) throw new Exception('新增食材準備失敗：' . $mysqli->error, 500);

    foreach ($clean_ingredients as $ing) {
      // ---- ⭐️ 核心修改 4: 綁定正確的參數 ----
      $ins_stmt->bind_param('isss', $recipe_id, $ing['id'], $ing['name'], $ing['amount']);
      $ins_stmt->execute();
      if ($ins_stmt->errno) throw new Exception('新增食材失敗：' . $ins_stmt->error, 500);
      $inserted += $ins_stmt->affected_rows;
    }
    $ins_stmt->close();
  }

  $mysqli->commit();

  send_json([
    'status'   => 'success',
    'message'  => '食材已成功更新',
    'mode'     => $mode,
    'inserted' => $inserted
  ], 200);

} catch (Throwable $e) {
  if (isset($mysqli)) { @ $mysqli->rollback(); }
  $code = $e->getCode() ?: 500;
  $code = ($code >= 400 && $code < 600) ? $code : 500;
  send_json(['status' => 'fail', 'message' => $e->getMessage()], $code);
} finally {
  if (isset($mysqli)) $mysqli->close();
}