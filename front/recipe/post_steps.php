<?php
// 先處理 CORS，確保就算錯誤也能回 JSON
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

try {
  // 只收 POST + JSON
  require_method('POST');
  $data = get_json_input();

  // 基本驗證
  if (!isset($data['recipe_id']) || !is_numeric($data['recipe_id'])) {
    throw new Exception('缺少或不合法的 recipe_id', 400);
  }
  if (!isset($data['steps']) || !is_array($data['steps'])) {
    throw new Exception('缺少 steps 或格式不正確（需為陣列）', 400);
  }

  $recipe_id = (int)$data['recipe_id'];

  // 模式：append = 只新增（保留原本功能）；replace = 整批取代（編輯用）
  $mode = strtolower(trim((string)($data['mode'] ?? 'append')));
  if (!in_array($mode, ['append', 'replace'], true)) {
    $mode = 'append';
  }

  // 查主表狀態：0 待審核 / 1 上架 / 2 下架 / 3 草稿
  $q = $mysqli->prepare("SELECT status FROM recipe WHERE recipe_id = ?");
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

  // 清洗步驟內容（去掉空白與空字串）
  $cleanSteps = [];
  foreach ((array)$data['steps'] as $content) {
    $txt = trim((string)$content);
    if ($txt !== '') $cleanSteps[] = $txt;
  }

  if ($needNonEmpty && count($cleanSteps) === 0) {
    throw new Exception('上線/送審時需至少一筆步驟內容', 400);
  }

  // DB 交易開始
  $mysqli->begin_transaction();

  if ($mode === 'replace') {
    // 編輯情境：整批取代
    $del = $mysqli->prepare("DELETE FROM `steps` WHERE `recipe_id` = ?");
    if (!$del) throw new Exception('刪除舊步驟準備失敗：' . $mysqli->error, 500);
    $del->bind_param('i', $recipe_id);
    $del->execute();
    $del->close();
  }

  $inserted = 0;
  if (!empty($cleanSteps)) {
    // 注意：`order` 是保留字，已用反引號包住
    $ins = $mysqli->prepare("INSERT INTO `steps` (`recipe_id`, `order`, `content`) VALUES (?, ?, ?)");
    if (!$ins) throw new Exception('新增步驟準備失敗：' . $mysqli->error, 500);

    $order = 1;
    foreach ($cleanSteps as $txt) {
      $ins->bind_param('iis', $recipe_id, $order, $txt);
      $ins->execute();
      if ($ins->errno) throw new Exception('新增步驟失敗：' . $ins->error, 500);
      $inserted += $ins->affected_rows;
      $order++;
    }
    $ins->close();
  }

  // 上線/送審：操作後仍需 ≥ 1 筆
  if ($needNonEmpty) {
    $chk = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM `steps` WHERE `recipe_id` = ?");
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
    'inserted' => $inserted,
  ], 200);

} catch (Throwable $e) {
  if (isset($mysqli)) { @ $mysqli->rollback(); }
  $code = $e->getCode() ?: 500;
  $code = ($code >= 400 && $code < 600) ? $code : 500;
  send_json(['status' => 'fail', 'message' => $e->getMessage()], $code);
} finally {
  if (isset($mysqli)) $mysqli->close();
}