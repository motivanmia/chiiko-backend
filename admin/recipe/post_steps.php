<?php
// 後台 - 新增/更新食譜步驟
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
  if (!isset($data['steps']) || !is_array($data['steps'])) {
    throw new Exception('缺少 steps 或格式不正確（需為陣列）', 400);
  }

  $recipe_id = (int)$data['recipe_id'];
  $mode = strtolower(trim((string)($data['mode'] ?? 'replace'))); // 預設 replace
  if (!in_array($mode, ['append', 'replace'], true)) {
    $mode = 'replace';
  }

  // ---- 查主表狀態：確認食譜是否存在 ----
  $q = $mysqli->prepare("SELECT status FROM recipe WHERE recipe_id = ?");
  if (!$q) throw new Exception("查詢食譜狀態失敗：" . $mysqli->error, 500);
  $q->bind_param('i', $recipe_id);
  $q->execute();
  $row = $q->get_result()->fetch_assoc();
  $q->close();

  if (!$row) {
    throw new Exception('找不到對應的食譜', 404);
  }

  $status = (int)$row['status'];
  // 待審核 / 上架 → 至少需要 1 筆有效步驟
  $needNonEmpty = in_array($status, [0, 1], true);

  // ---- 清洗步驟資料 ----
  $clean_steps = [];
  foreach ($data['steps'] as $step) {
    // 支援兩種格式：純字串 or {order, content}
    if (is_array($step)) {
      $txt = trim((string)($step['content'] ?? ''));
      $order = isset($step['order']) && is_numeric($step['order']) ? (int)$step['order'] : null;
    } else {
      $txt = trim((string)$step);
      $order = null;
    }
    if ($txt !== '') {
      $clean_steps[] = [
        'order'   => $order,
        'content' => $txt
      ];
    }
  }

  if ($needNonEmpty && count($clean_steps) === 0) {
    throw new Exception('上線/送審時需至少一筆步驟內容', 400);
  }

  // ---- DB 寫入 ----
  $mysqli->begin_transaction();

  if ($mode === 'replace') {
    $del = $mysqli->prepare("DELETE FROM steps WHERE recipe_id = ?");
    if (!$del) throw new Exception('刪除舊步驟語句準備失敗：' . $mysqli->error, 500);
    $del->bind_param('i', $recipe_id);
    $del->execute();
    $del->close();
  }

  $inserted = 0;
  if (!empty($clean_steps)) {
    $ins = $mysqli->prepare("INSERT INTO steps (recipe_id, step_order, content) VALUES (?, ?, ?)");
    if (!$ins) throw new Exception('新增步驟準備失敗：' . $mysqli->error, 500);

    $order = 1;
    foreach ($clean_steps as $st) {
      // 如果前端有指定順序就用，否則用自動遞增
      $step_order = $st['order'] ?? $order;
      $content = $st['content'];
      $ins->bind_param('iis', $recipe_id, $step_order, $content);
      $ins->execute();
      if ($ins->errno) throw new Exception('新增步驟失敗：' . $ins->error, 500);
      $inserted += $ins->affected_rows;
      $order++;
    }
    $ins->close();
  }

  // ---- 驗證必要條件 ----
  if ($needNonEmpty) {
    $chk = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM steps WHERE recipe_id = ?");
    $chk->bind_param('i', $recipe_id);
    $chk->execute();
    $cntRow = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ((int)$cntRow['cnt'] === 0) {
      $mysqli->rollback();
      throw new Exception('上線/送審時至少需一筆步驟內容', 400);
    }
  }

  $mysqli->commit();

  send_json([
    'status'   => 'success',
    'message'  => $mode === 'replace' ? '步驟已更新（整批取代）' : '步驟已新增（附加）',
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