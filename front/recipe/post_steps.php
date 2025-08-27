<?php
// 後台 - 新增/更新食譜步驟
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

$mysqli->set_charset('utf8');
$mysqli->query("SET collation_connection = 'utf8_general_ci'");

try {
    // 只收 POST + JSON
    require_method('POST');
    $data = get_json_input();

    // 基本驗證
    if (!isset($data['recipe_id']) || !is_numeric($data['recipe_id'])) {
        throw new Exception('缺少或不合法的 recipe_id', 400);
    }
    // 💡 修正：允許空 steps 陣列，但需確保存在
    if (!isset($data['steps']) || !is_array($data['steps'])) {
        throw new Exception('缺少 steps 或格式不正確（需為陣列）', 400);
    }

    $recipe_id = (int)$data['recipe_id'];

    // 模式：append = 只新增（保留原本功能）；replace = 整批取代（編輯用）
    $mode = strtolower(trim((string)($data['mode'] ?? 'append')));
    if (!in_array($mode, ['append', 'replace'], true)) {
        $mode = 'append';
    }

    // 💡 查主表狀態：0 待審核 / 1 上架 / 2 下架 / 3 草稿
    $sql_status = "SELECT status FROM recipe WHERE recipe_id = {$recipe_id}";
    $result = $mysqli->query($sql_status);
    
    if (!$result) {
        throw new Exception("查詢食譜狀態失敗：" . $mysqli->error, 500);
    }

    $row = $result->fetch_assoc();
    $result->free();

    if (!$row) {
        throw new Exception('找不到對應的食譜', 404);
    }

    $status = (int)$row['status'];
    // 待審核 / 上架 → 至少需要 1 筆有效步驟
    $needNonEmpty = in_array($status, [0, 1], true);

    // 💡 修正：保留所有步驟內容，即使是空字串，以確保與前端同步
    $stepsToInsert = [];
    foreach ((array)$data['steps'] as $content) {
        $stepsToInsert[] = trim((string)$content);
    }

    // 💡 修正：發布/上線時才檢查是否有空步驟
    if ($needNonEmpty && count(array_filter($stepsToInsert, function($step) { return $step !== ''; })) === 0) {
        throw new Exception('上線/送審時需至少一筆步驟內容', 400);
    }

    // DB 交易開始
    $mysqli->begin_transaction();

    if ($mode === 'replace') {
        // 💡 編輯情境：整批取代
        $mysqli->query("DELETE FROM `steps` WHERE `recipe_id` = {$recipe_id}");
        if ($mysqli->errno) throw new Exception('刪除舊步驟失敗：' . $mysqli->error, 500);
    }

    $inserted = 0;
    // 💡 修正：即使是空步驟也進行寫入，確保與前端同步
    if (!empty($stepsToInsert)) {
        $order = 1;
        foreach ($stepsToInsert as $txt) {
            $safe_txt = $mysqli->real_escape_string($txt);
            // 💡 將變數直接嵌入 SQL 字串
            $mysqli->query("INSERT INTO `steps` (`recipe_id`, `order`, `content`) VALUES ({$recipe_id}, {$order}, '{$safe_txt}')");
            if ($mysqli->errno) throw new Exception('新增步驟失敗：' . $mysqli->error, 500);
            $inserted += $mysqli->affected_rows;
            $order++;
        }
    }

    // 上線/送審：操作後仍需 ≥ 1 筆
    if ($needNonEmpty) {
        $sql_count = "SELECT COUNT(*) AS cnt FROM `steps` WHERE `recipe_id` = {$recipe_id} AND `content` != ''";
        $result_count = $mysqli->query($sql_count);
        $cntRow = $result_count->fetch_assoc();
        $result_count->free();

        if ((int)$cntRow['cnt'] === 0) {
            $mysqli->rollback();
            throw new Exception('上線/送審時至少需一筆步驟內容', 400);
        }
    }

    $mysqli->commit();

    send_json([
        'status' => 'success',
        'message' => $mode === 'replace' ? '步驟已更新（整批取代）' : '步驟已新增（附加）',
        'mode' => $mode,
        'inserted' => $inserted,
    ], 200);

} catch (Throwable $e) {
    if (isset($mysqli)) {
        @$mysqli->rollback();
    }
    $code = $e->getCode() ?: 500;
    $code = ($code >= 400 && $code < 600) ? $code : 500;
    send_json(['status' => 'fail', 'message' => $e->getMessage()], $code);
} finally {
    if (isset($mysqli)) $mysqli->close();
}