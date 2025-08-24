<?php
// ✅ 先處理 CORS，避免錯誤時瀏覽器看不到 JSON
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
  // 模式：append = 只新增（保留原本功能）；replace = 整批取代（提供修改用）
  $mode = strtolower(trim((string)($data['mode'] ?? 'append')));
  if (!in_array($mode, ['append', 'replace'], true)) {
    $mode = 'append';
  }

  // （可選）相容型 fallback：若前端沒給 ingredient_id，但有 name，就套用一個預設 ID。
  // 若不想開這個相容模式，改成 null 即可。
  $FALLBACK_ING_ID = 1; // ← 你可以換掉或設成 null 以關閉

  // 讀主表狀態：0 待審核 / 1 上架 / 2 下架 / 3 草稿
  $q = $mysqli->prepare("SELECT status FROM recipe WHERE recipe_id = ?");
  $q->bind_param('i', $recipe_id);
  $q->execute();
  $row = $q->get_result()->fetch_assoc();
  $q->close();

  if (!$row) {
    throw new Exception('找不到對應的食譜', 404);
  }
  $status = (int)$row['status'];
  // 待審核/上架：需至少一筆且每筆完整
  $needNonEmpty = in_array($status, [0, 1], true);

  // ---- 清洗資料 ----
  $clean = [];
  foreach ($data['ingredients'] as $idx => $it) {
    $amount = isset($it['amount']) ? trim((string)$it['amount']) : '';

    // 優先吃 ingredient_id；沒有就看 name（如果你開了 fallback）
    $ingredient_id = null;
    if (isset($it['ingredient_id']) && is_numeric($it['ingredient_id'])) {
      $ingredient_id = (int)$it['ingredient_id'];
    } elseif (!empty($it['name']) && $FALLBACK_ING_ID !== null) {
      // 相容舊行為：暫時用一個預設 ID
      $ingredient_id = (int)$FALLBACK_ING_ID;
    }

    if ($ingredient_id !== null && $amount !== '') {
      $clean[] = [$ingredient_id, $amount];
    } elseif ($needNonEmpty) {
      throw new Exception("第 " . ($idx + 1) . " 筆食材缺少 ingredient_id/name 或 amount", 400);
    }
    // 若非上線/送審，空的就略過不插
  }

  if ($needNonEmpty && count($clean) === 0) {
    throw new Exception('上線/送審時需至少一筆完整的食材', 400);
  }

  // ---- DB 寫入 ----
  $mysqli->begin_transaction();

  if ($mode === 'replace') {
    // 修改情境：整批取代
    $del = $mysqli->prepare("DELETE FROM ingredient_item WHERE recipe_id = ?");
    if (!$del) throw new Exception('刪除舊食材準備失敗：' . $mysqli->error, 500);
    $del->bind_param('i', $recipe_id);
    $del->execute();
    $del->close();
  }

  $inserted = 0;
  if (!empty($clean)) {
    $ins = $mysqli->prepare("INSERT INTO ingredient_item (recipe_id, ingredient_id, serving) VALUES (?, ?, ?)");
    if (!$ins) throw new Exception('新增食材準備失敗：' . $mysqli->error, 500);

    foreach ($clean as [$ingId, $serv]) {
      $ins->bind_param('iis', $recipe_id, $ingId, $serv);
      $ins->execute();
      if ($ins->errno) throw new Exception('新增食材失敗：' . $ins->error, 500);
      $inserted += $ins->affected_rows;
    }
    $ins->close();
  }

  // 上線/送審強制檢查：操作後總數仍需 ≥ 1
  if ($needNonEmpty) {
    $chk = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM ingredient_item WHERE recipe_id = ?");
    $chk->bind_param('i', $recipe_id);
    $chk->execute();
    $cntRow = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ((int)$cntRow['cnt'] === 0) {
      $mysqli->rollback();
      throw new Exception('上線/送審時至少需一筆食材', 400);
    }
  }

  $mysqli->commit();

  send_json([
    'status'   => 'success',
    'message'  => $mode === 'replace' ? '食材已更新（整批取代）' : '食材已新增（附加）',
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