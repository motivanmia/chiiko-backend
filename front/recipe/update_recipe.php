<?php
// --- 開發期錯誤輸出 ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

try {
  require_method('POST');
  $data = get_json_input();

  if (session_status() === PHP_SESSION_NONE) session_start();
  $user_id = $_SESSION['user_id'] ?? null;
  if (!$user_id) throw new Exception('使用者未登入', 401);

  // 解析並驗證 recipe_id
  $recipe_id = isset($data['recipe_id']) && is_numeric($data['recipe_id'])
    ? (int)$data['recipe_id'] : null;
  if (!$recipe_id) throw new Exception('缺少食譜 ID', 400);

  // 確認擁有權
  $sql_check = "SELECT recipe_id FROM recipe WHERE recipe_id = {$recipe_id} AND user_id = {$user_id}";
  $res_check = $mysqli->query($sql_check);
  if (!$res_check) throw new Exception('查詢食譜所有權失敗: ' . $mysqli->error, 500);
  $owned = $res_check->fetch_assoc();
  $res_check->free();
  if (!$owned) throw new Exception('找不到食譜或您無權限修改此食譜', 404);

  // 交易開始
  $mysqli->begin_transaction();

  // ---------- Step 1: 更新 recipe 主檔 ----------
  // 僅更新 payload 有帶到的欄位
  $set = [];
  $sqlv = fn($v) => $v === null ? "NULL" : ("'".$mysqli->real_escape_string($v)."'");

  // 允許更新的欄位清單
  $allowed_fields = [
    'recipe_category_id', // 數字或字串皆可，這裡不強轉，交給 DB schema
    'name',
    'content',
    'serving',
    'image',              // 允許完整 URL；寫入時會去掉 IMG_BASE_URL 前綴
    'cooked_time',
    'status',             // 會轉 int
    'tag',
    'manager_id',         // 可為 null/字串/數字
    'views'               // 會轉 int
  ];

  foreach ($allowed_fields as $k) {
    if (!array_key_exists($k, $data)) continue;
    $val = $data[$k];

    // 型別/內容正規化
    if ($k === 'status' || $k === 'views') {
      // 整數欄位
      $val = is_null($val) || $val === '' ? null : (int)$val;
      $set[] = "`{$k}` = " . ($val === null ? "NULL" : (int)$val);
      continue;
    }

    if ($k === 'image' && is_string($val)) {
      // 若傳來完整 URL，去掉 IMG_BASE_URL 前綴後再存
      $prefix = rtrim(IMG_BASE_URL, '/') . '/';
      if (strpos($val, $prefix) === 0) {
        $val = substr($val, strlen($prefix));
      }
    }

    // 一般字串 / 可為 null
    if (is_string($val)) $val = trim($val);
    $set[] = "`{$k}` = " . $sqlv($val === '' ? null : $val);
  }

  if ($set) {
    $sql_upd = "UPDATE recipe SET " . implode(', ', $set) . " WHERE recipe_id = {$recipe_id} AND user_id = {$user_id}";
    if (!$mysqli->query($sql_upd)) {
      throw new Exception('主食譜資料更新失敗：' . $mysqli->error, 500);
    }
  }

  // ---------- Step 2: 更新 ingredients ----------
  // 僅在 payload 有帶 ingredients 時才更新；否則保持原資料不動
  if (array_key_exists('ingredients', $data)) {
    $ingredients = $data['ingredients'];
    if (!is_array($ingredients)) $ingredients = [];

    // 先清掉舊資料
    if (!$mysqli->query("DELETE FROM ingredient_item WHERE recipe_id = {$recipe_id}")) {
      throw new Exception('刪除舊食材失敗: ' . $mysqli->error, 500);
    }

    // 寫入新資料（[{name, amount}]）
    foreach ($ingredients as $it) {
      $name = trim((string)($it['name'] ?? ''));
      $amount = (string)($it['amount'] ?? '');
      if ($name === '' || $amount === '') continue;

      $q = "INSERT INTO ingredient_item (recipe_id, name, serving) VALUES (".
           (int)$recipe_id.", ".$sqlv($name).", ".$sqlv($amount).")";
      if (!$mysqli->query($q)) throw new Exception('新增食材失敗: ' . $mysqli->error, 500);
    }
  }

  // ---------- Step 3: 更新 steps ----------
  // 僅在 payload 有帶 steps 時才更新；否則保持原資料不動
  if (array_key_exists('steps', $data)) {
    $steps = $data['steps'];
    if (!is_array($steps)) $steps = [];

    // 先清掉舊資料
    if (!$mysqli->query("DELETE FROM steps WHERE recipe_id = {$recipe_id}")) {
      throw new Exception('刪除舊步驟失敗: ' . $mysqli->error, 500);
    }

    // 正規化：支援 ["切豆腐", ...] 或 [{order, content}, ...]
    $normalized = [];
    foreach ($steps as $idx => $s) {
      if (is_array($s)) {
        $order = isset($s['order']) ? (int)$s['order'] : ($idx + 1);
        $content = trim((string)($s['content'] ?? ''));
        if ($content !== '') $normalized[] = ['order' => $order, 'content' => $content];
      } else {
        $content = trim((string)$s);
        if ($content !== '') $normalized[] = ['order' => $idx + 1, 'content' => $content];
      }
    }

    foreach ($normalized as $st) {
      $o = (int)$st['order'];
      $c = $sqlv($st['content']);
      $q = "INSERT INTO steps (recipe_id, `order`, content) VALUES (".
           (int)$recipe_id.", {$o}, {$c})";
      if (!$mysqli->query($q)) throw new Exception('新增步驟失敗: ' . $mysqli->error, 500);
    }
  }

  // 成功，提交
  $mysqli->commit();

  send_json([
    'status'  => 'success',
    'message' => '食譜已成功更新',
    'recipe_id' => $recipe_id
  ], 200);

} catch (Throwable $e) {
  if (isset($mysqli)) $mysqli->rollback();
  $code = (int)($e->getCode() ?: 500);
  if ($code < 400 || $code >= 600) $code = 500;
  send_json(['status' => 'fail', 'message' => $e->getMessage()], $code);
} finally {
  if (isset($mysqli)) $mysqli->close();
}
