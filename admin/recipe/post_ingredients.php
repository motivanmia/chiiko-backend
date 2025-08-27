<?php
// 後台 - 新增/更新食譜食材
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

$mysqli->set_charset('utf8');
$mysqli->query("SET collation_connection = 'utf8_general_ci'");

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
  $mode = strtolower(trim((string)($data['mode'] ?? 'replace'))); // 預設 replace
  if (!in_array($mode, ['append', 'replace'], true)) {
    $mode = 'replace';
  }

  // ---- 名稱正規化 ----
  $norm = function(string $s): string {
    $s = trim($s);
    // 將所有空白(半形/全形)折疊為單一半形空白
    return preg_replace('/[\x{3000}\s]+/u', ' ', $s);
  };

  // ---- 預處理 SQL 語句 ----
  // 精準比對
  $stmt_exact = $mysqli->prepare(
    "SELECT ingredient_id
       FROM ingredients
      WHERE name COLLATE utf8_general_ci = ?
      LIMIT 1"
  );
  if (!$stmt_exact) throw new Exception('準備精準比對語句失敗：' . $mysqli->error, 500);

  // 模糊比對 + 智慧排序
  $stmt_like_best = $mysqli->prepare(
    "SELECT ingredient_id, name
       FROM ingredients
      WHERE
        (
          name COLLATE utf8_general_ci LIKE CONCAT('%', ?, '%')
          OR
          CAST(? AS CHAR CHARACTER SET utf8) LIKE CONCAT('%', name COLLATE utf8_general_ci, '%')
        )
      ORDER BY
        CASE WHEN LOCATE(?, name COLLATE utf8_general_ci) > 0 THEN 0 ELSE 1 END,
        ABS(CHAR_LENGTH(name) - CHAR_LENGTH(?)) ASC,
        CASE WHEN LOCATE(name COLLATE utf8_general_ci, ?) > 0 THEN 0 ELSE 1 END,
        LOCATE(?, name COLLATE utf8_general_ci) ASC,
        name ASC
      LIMIT 1"
  );
  if (!$stmt_like_best) throw new Exception('準備模糊比對語句失敗：' . $mysqli->error, 500);

  // ---- 清洗食材 ----
  $clean_ingredients = [];
  foreach ($data['ingredients'] as $it) {
    $rawName = isset($it['name']) ? (string)$it['name'] : '';
    $name = $norm($rawName);
    $amount = isset($it['amount']) ? trim((string)$it['amount']) : '';

    if ($name === '' || $amount === '') continue;

    $ingredient_id = null;

    // 1) 精準比對
    $stmt_exact->bind_param('s', $name);
    $stmt_exact->execute();
    $res1 = $stmt_exact->get_result();
    if ($row = $res1->fetch_assoc()) {
      $ingredient_id = (int)$row['ingredient_id'];
    }

    // 2) 模糊比對
    if ($ingredient_id === null) {
      $stmt_like_best->bind_param('ssssss', $name, $name, $name, $name, $name, $name);
      $stmt_like_best->execute();
      $res2 = $stmt_like_best->get_result();
      if ($row2 = $res2->fetch_assoc()) {
        $ingredient_id = (int)$row2['ingredient_id'];
      }
    }

    $clean_ingredients[] = [
      'id'     => $ingredient_id, // 可能 NULL
      'name'   => $rawName,       // 保留使用者輸入
      'amount' => $amount
    ];
  }

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
      $ins_stmt->bind_param('iiss', $recipe_id, $ing['id'], $ing['name'], $ing['amount']);
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
  if (isset($mysqli)) { @$mysqli->rollback(); }
  $code = $e->getCode() ?: 500;
  $code = ($code >= 400 && $code < 600) ? $code : 500;
  send_json(['status' => 'fail', 'message' => $e->getMessage()], $code);
} finally {
  if (isset($mysqli)) $mysqli->close();
}