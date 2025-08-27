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
        return preg_replace('/[\x{3000}\s]+/u', ' ', $s);
    };

    // ---- 清洗食材 ----
    $clean_ingredients = [];
    foreach ($data['ingredients'] as $it) {
        $rawName = isset($it['name']) ? (string)$it['name'] : '';
        $name = $norm($rawName);
        $amount = isset($it['amount']) ? trim((string)$it['amount']) : '';

        if ($name === '' || $amount === '') continue;

        $ingredient_id = null;

        // 💡 1) 精準比對
        // 💡 直接將變數嵌入 SQL 字串
        $sql_exact = "SELECT ingredient_id FROM ingredients WHERE name COLLATE utf8_general_ci = '{$mysqli->real_escape_string($name)}' LIMIT 1";
        $result_exact = $mysqli->query($sql_exact);
        if ($result_exact && $row = $result_exact->fetch_assoc()) {
            $ingredient_id = (int)$row['ingredient_id'];
            $result_exact->free();
        }

        // 💡 2) 模糊比對
        if ($ingredient_id === null) {
            // 💡 直接將變數嵌入 SQL 字串
            $safe_name = $mysqli->real_escape_string($name);
            $sql_like_best = "SELECT ingredient_id, name FROM ingredients WHERE (name COLLATE utf8_general_ci LIKE '%{$safe_name}%' OR CAST('{$safe_name}' AS CHAR CHARACTER SET utf8) LIKE CONCAT('%', name COLLATE utf8_general_ci, '%')) ORDER BY CASE WHEN LOCATE('{$safe_name}', name COLLATE utf8_general_ci) > 0 THEN 0 ELSE 1 END, ABS(CHAR_LENGTH(name) - CHAR_LENGTH('{$safe_name}')) ASC, CASE WHEN LOCATE(name COLLATE utf8_general_ci, '{$safe_name}') > 0 THEN 0 ELSE 1 END, LOCATE('{$safe_name}', name COLLATE utf8_general_ci) ASC, name ASC LIMIT 1";
            $result_like_best = $mysqli->query($sql_like_best);
            if ($result_like_best && $row2 = $result_like_best->fetch_assoc()) {
                $ingredient_id = (int)$row2['ingredient_id'];
                $result_like_best->free();
            }
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
        // 💡 直接將變數嵌入 SQL 字串
        $mysqli->query("DELETE FROM ingredient_item WHERE recipe_id = {$recipe_id}");
        if ($mysqli->errno) throw new Exception('刪除舊食材失敗：' . $mysqli->error, 500);
    }

    $inserted = 0;
    if (!empty($clean_ingredients)) {
        foreach ($clean_ingredients as $ing) {
            // 💡 處理可能為 NULL 的 ingredient_id
            $ing_id = $ing['id'] === null ? 'NULL' : (int)$ing['id'];
            $safe_name = $mysqli->real_escape_string($ing['name']);
            $safe_amount = $mysqli->real_escape_string($ing['amount']);

            // 💡 直接將變數嵌入 SQL 字串
            $mysqli->query("INSERT INTO ingredient_item (recipe_id, ingredient_id, name, serving) VALUES ({$recipe_id}, {$ing_id}, '{$safe_name}', '{$safe_amount}')");
            if ($mysqli->errno) throw new Exception('新增食材失敗：' . $mysqli->error, 500);
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
    if (isset($mysqli)) { @$mysqli->rollback(); }
    $code = $e->getCode() ?: 500;
    $code = ($code >= 400 && $code < 600) ? $code : 500;
    send_json(['status' => 'fail', 'message' => $e->getMessage()], $code);
} finally {
    if (isset($mysqli)) $mysqli->close();
}