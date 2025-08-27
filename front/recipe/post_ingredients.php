<?php
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);



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

  // ---- 查詢語句（一次 prepare，多次執行）----
  // 1) 精準比對（不分大小寫）— 使用 LOWER 避免 collation 混用
  $stmt_exact = $mysqli->prepare(
    "SELECT ingredient_id
       FROM ingredients
      WHERE LOWER(name) = LOWER(?)
      LIMIT 1"
  );
  if (!$stmt_exact) throw new Exception('準備精準比對語句失敗：' . $mysqli->error, 500);

  // 2) 模糊比對 + 智慧排序（長度接近、出現位置前、名稱包含關鍵字優先）
  //    全部依賴連線/欄位預設（utf8mb4_unicode_ci），避免 CAST/COLLATE 混用
  $stmt_like_best = $mysqli->prepare(
    "SELECT ingredient_id, name
       FROM ingredients
      WHERE name LIKE CONCAT('%', ?, '%')
      ORDER BY
        -- 名稱中有關鍵字優先
        CASE WHEN INSTR(name, ?) > 0 THEN 0 ELSE 1 END,
        -- 長度越接近越好
        ABS(CHAR_LENGTH(name) - CHAR_LENGTH(?)) ASC,
        -- 關鍵字是否包含名稱（短名優先，如『雞肉』優於『雞肉條炒飯』）
        CASE WHEN INSTR(?, name) > 0 THEN 0 ELSE 1 END,
        -- 出現位置越靠前越好
        INSTR(name, ?) ASC,
        -- 穩定排序
        name ASC
      LIMIT 1"
  );
  if (!$stmt_like_best) throw new Exception('準備模糊比對語句失敗：' . $mysqli->error, 500);

  // ---- 清洗並處理每一筆食材資料 ----
  $clean_ingredients = [];
  foreach ($data['ingredients'] as $it) {
    $rawName = isset($it['name']) ? (string)$it['name'] : '';
    $name = $norm($rawName);
    $amount = isset($it['amount']) ? trim((string)$it['amount']) : '';

    // 只要名稱或數量是空的，就跳過
    if ($name === '' || $amount === '') continue;

    $ingredient_id = null;

    // 1) 精準比對（不分大小寫）
    $stmt_exact->bind_param('s', $name);
    $stmt_exact->execute();
    $res1 = $stmt_exact->get_result();
    if ($row = $res1->fetch_assoc()) {
      $ingredient_id = (int)$row['ingredient_id'];
    }

    // 2) 模糊比對（智慧排序取最佳一筆）
    if ($ingredient_id === null) {
      // 依序填入六個同值參數
      $stmt_like_best->bind_param('sssss', $name, $name, $name, $name, $name);
      $stmt_like_best->execute();
      $res2 = $stmt_like_best->get_result();
      if ($row2 = $res2->fetch_assoc()) {
        $ingredient_id = (int)$row2['ingredient_id'];
        // 如需記錄實際匹配到的名稱，可用 $row2['name']
      }
    }

    $clean_ingredients[] = [
      'id'     => $ingredient_id, // 可能是整數或 NULL（欄位需允許 NULL；否則請改嚴格模式）
      'name'   => $rawName,       // 保留使用者原輸入
      'amount' => $amount
    ];
  }

  // 關閉查詢語句
  $stmt_exact->close();
  $stmt_like_best->close();

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
  // 取代 $mysqli->rollback();
  safe_mysqli_rollback($mysqli);

  // 錯誤回傳...
  send_json(['status'=>'fail','message'=>$e->getMessage()], 500);
}finally {
  if (isset($mysqli)) $mysqli->close();
}
