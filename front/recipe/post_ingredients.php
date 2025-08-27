<?php
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

  // 名稱簡易正規化：去頭尾、折疊空白（含全形）
  $norm = function(string $s): string {
    $s = trim($s);
    // 將所有空白(半形/全形)折疊為單一半形空白
    $s = preg_replace('/[\x{3000}\s]+/u', ' ', $s);
    return $s;
  };

  // ---- 清洗並處理每一筆食材資料（使用 db_query，避免 get_result）----
  $clean_ingredients = [];
  foreach ($data['ingredients'] as $it) {
    $rawName = isset($it['name']) ? (string)$it['name'] : '';
    $name = $norm($rawName);
    $amount = isset($it['amount']) ? trim((string)$it['amount']) : '';

    // 只要名稱或數量是空的，就跳過
    if ($name === '' || $amount === '') continue;

    $ingredient_id = null;

    // 1) 精準比對（不分大小寫）
    $esc = $mysqli->real_escape_string($name);
    $sql1 = "
      SELECT ingredient_id
      FROM ingredients
      WHERE LOWER(name) = LOWER('{$esc}')
      LIMIT 1
    ";
    $res1 = db_query($mysqli, $sql1);
    if ($row = $res1->fetch_assoc()) {
      $ingredient_id = (int)$row['ingredient_id'];
    }
    $res1->free(); // 釋放結果集，避免 out-of-sync

    // 2) 模糊比對（智慧排序取最佳一筆）
    if ($ingredient_id === null) {
      // 需要重複使用關鍵字的地方全部用同一個已跳脫的字串
      // name LIKE、INSTR(name, ?)、INSTR(?, name) 等等
      $escLike = $esc; // 同一份已跳脫字串即可
      $sql2 = "
        SELECT ingredient_id, name
        FROM ingredients
        WHERE name LIKE CONCAT('%', '{$escLike}', '%')
        ORDER BY
          CASE WHEN INSTR(name, '{$escLike}') > 0 THEN 0 ELSE 1 END,
          ABS(CHAR_LENGTH(name) - CHAR_LENGTH('{$escLike}')) ASC,
          CASE WHEN INSTR('{$escLike}', name) > 0 THEN 0 ELSE 1 END,
          INSTR(name, '{$escLike}') ASC,
          name ASC
        LIMIT 1
      ";
      $res2 = db_query($mysqli, $sql2);
      if ($row2 = $res2->fetch_assoc()) {
        $ingredient_id = (int)$row2['ingredient_id'];
        // 如需記錄實際匹配到的名稱，可用 $row2['name']
      }
      $res2->free(); // 釋放結果集
    }

    $clean_ingredients[] = [
      'id'     => $ingredient_id, // 可能是整數或 NULL（欄位需允許 NULL；否則請改嚴格模式）
      'name'   => $rawName,       // 保留使用者原輸入
      'amount' => $amount
    ];
  }

  // ---- DB 寫入 ----
  $mysqli->begin_transaction();

  if ($mode === 'replace') {
    $del = $mysqli->prepare("DELETE FROM ingredient_item WHERE recipe_id = ?");
    if (!$del) throw new Exception('刪除舊食材語句準備失敗：' . $mysqli->error, 500);
    $del->bind_param('i', $recipe_id);
    $del->execute();
    $del->close();
  }

  $inserted = 0;
  if (!empty($clean_ingredients)) {
    $ins_stmt = $mysqli->prepare(
      "INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving)
       VALUES (?, ?, ?, ?)"
    );
    if (!$ins_stmt) throw new Exception('新增食材準備失敗：' . $mysqli->error, 500);

    foreach ($clean_ingredients as $ing) {
      // 注意：若 ingredient_item.ingredient_id 為 NOT NULL + 外鍵，且 $ing['id'] 為 NULL，會失敗
      // 若要允許自由輸入名稱，請把 ingredient_id 改為 NULLable，且 FK 設 ON DELETE SET NULL
      $ingId = $ing['id']; // 可能為 null
      $ins_stmt->bind_param('iiss', $recipe_id, $ingId, $ing['name'], $ing['amount']);
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
  // 失敗回滾（已在 functions.php 實作 safe 版本亦可）
  try { $mysqli->rollback(); } catch (Throwable $ignore) {}

  send_json(['status'=>'fail','message'=>$e->getMessage()], 500);
} finally {
  if (isset($mysqli)) $mysqli->close();
}
