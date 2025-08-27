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

  // ---- 清洗並處理每一筆食材資料 ----
  $clean_ingredients = [];
  foreach ($data['ingredients'] as $it) {
    $rawName = isset($it['name']) ? (string)$it['name'] : '';
    $name = $norm($rawName);
    $amount = isset($it['amount']) ? trim((string)$it['amount']) : '';

    // 只要名稱或數量是空的，就跳過
    if ($name === '' || $amount === '') continue;

    $ingredient_id = null;

    // 將所有變數用 real_escape_string 處理
    $esc = $mysqli->real_escape_string($name);

    // 1) 精準比對（不分大小寫）
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

    // 2) 模糊比對（智慧排序取最佳一筆）
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

    $clean_ingredients[] = [
      'id'     => $ingredient_id,
      'name'   => $rawName,
      'amount' => $amount
    ];
  }

  // ---- DB 寫入 ----
  $mysqli->begin_transaction();

  if ($mode === 'replace') {
    $sql_del = "DELETE FROM ingredient_item WHERE recipe_id = {$recipe_id}";
    if (!$mysqli->query($sql_del)) {
      throw new Exception('刪除舊食材失敗：' . $mysqli->error, 500);
    }
  }

  $inserted = 0;
  if (!empty($clean_ingredients)) {
    foreach ($clean_ingredients as $ing) {
      $ingId = $ing['id'] === null ? 'NULL' : (int)$ing['id'];
      $safeName = $mysqli->real_escape_string($ing['name']);
      $safeAmount = $mysqli->real_escape_string($ing['amount']);

      $sql_ins = "
        INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving)
        VALUES ({$recipe_id}, {$ingId}, '{$safeName}', '{$safeAmount}')
      ";
      if (!$mysqli->query($sql_ins)) {
        throw new Exception('新增食材失敗：' . $mysqli->error, 500);
      }
      $inserted++;
    }
  }

  $mysqli->commit();

  send_json([
    'status'   => 'success',
    'message'  => '食材已成功更新',
    'mode'     => $mode,
    'inserted' => $inserted
  ], 200);

} catch (Throwable $e) {
  try { $mysqli->rollback(); } catch (Throwable $ignore) {}
  $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
  send_json(['status'=>'fail','message'=>$e->getMessage()], $code);
} finally {
  if (isset($mysqli)) $mysqli->close();
}