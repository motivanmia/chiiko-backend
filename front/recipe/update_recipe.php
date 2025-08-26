<?php
// 更新（修改）食譜
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

try {
    require_method('POST');
    $data = get_json_input();
    
    // 關鍵修正：從 SESSION 中取得 user_id
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('使用者未登入', 401);
    }
    
    $tidy = fn($s) => is_string($s) ? trim($s) : $s;
    
    $recipe_id = isset($data['recipe_id']) && is_numeric($data['recipe_id']) ? (int)$data['recipe_id'] : null;
    if (!$recipe_id) throw new Exception('缺少 recipe_id', 400);

    // 檢查食譜是否存在，並確認其屬於當前登入的使用者
    $recipe_id_safe = $mysqli->real_escape_string($recipe_id);
    $user_id_safe = $mysqli->real_escape_string($user_id);
    $sql_check = "SELECT status, image, user_id FROM recipe WHERE recipe_id = '{$recipe_id_safe}' AND user_id = '{$user_id_safe}'";
    $result_check = $mysqli->query($sql_check);
    if (!$result_check) {
        throw new Exception('資料庫操作失敗: ' . $mysqli->error, 500);
    }
    $cur = $result_check->fetch_assoc();
    if (!$cur) {
        throw new Exception('找不到食譜或您無權限修改此食譜', 404);
    }
    $result_check->free();
    
    $fields_to_update = [];
    $update_values = [];

    // 允許更新的欄位
    $allowed_fields = ['user_id', 'manager_id', 'recipe_category_id', 'name', 'content', 'serving', 'image', 'cooked_time', 'status', 'tag'];
    
    foreach ($allowed_fields as $k) {
        if (array_key_exists($k, $data)) {
            $val = $tidy($data[$k]);
            $safe_val = $mysqli->real_escape_string($val);
            if ($k === 'manager_id' || $k === 'recipe_category_id') {
                $safe_val = ($val === null || $val === '') ? 'NULL' : "'{$safe_val}'";
            } else {
                $safe_val = "'{$safe_val}'";
            }
            $fields_to_update[] = "`{$k}` = {$safe_val}";
        }
    }

    if (empty($fields_to_update)) throw new Exception('沒有可更新的欄位', 400);

    // 處理 status 欄位驗證
    $nextStatus = array_key_exists('status', $data) ? (int)$data['status'] : (int)$cur['status'];
    if ($nextStatus === 0 || $nextStatus === 1) {
        // ... (這部分驗證邏輯與原先相同，但要確保變數有定義) ...
        $name = array_key_exists('name', $data) ? $tidy($data['name']) : null;
        $content = array_key_exists('content', $data) ? $tidy($data['content']) : null;
        $tag = array_key_exists('tag', $data) ? $tidy($data['tag']) : null;
        $cooked_time = array_key_exists('cooked_time', $data) ? $tidy($data['cooked_time']) : null;
        $serving = array_key_exists('serving', $data) ? $tidy($data['serving']) : null;
        $image = array_key_exists('image', $data) ? $tidy($data['image']) : $cur['image'];

        if ($name === null || $content === null || $tag === null || $cooked_time === null || $serving === null) {
            $sql_fetch_old = "SELECT name, content, tag, cooked_time, serving FROM recipe WHERE recipe_id = '{$recipe_id_safe}'";
            $res_old = $mysqli->query($sql_fetch_old);
            $row = $res_old->fetch_assoc();
            if ($name === null) $name = $row['name'] ?? '';
            if ($content === null) $content = $row['content'] ?? '';
            if ($tag === null) $tag = $row['tag'] ?? '';
            if ($cooked_time === null) $cooked_time = $row['cooked_time'] ?? '';
            if ($serving === null) $serving = $row['serving'] ?? '';
            $res_old->free();
        }

        $errors = [];
        if ($name === '' || mb_strlen($name) > 15) $errors[] = '標題必填且 ≤ 15 字';
        if ($content === '' || mb_strlen($content) > 40) $errors[] = '內文必填且 ≤ 40 字';
        if ($tag === '' || strpos($tag, '#') === false) $errors[] = '至少一個 TAG，如 #蛋#家常';
        $allowTimes = ['5~10', '10~15', '15~30', '30~60', '60~120', '120~180', '180+'];
        if (!in_array($cooked_time, $allowTimes, true)) $errors[] = '烹煮時間不合法';
        $allowServings = ['1~2', '3~4', '5~6', '7~8', '9~10'];
        if (!in_array($serving, $allowServings, true)) $errors[] = '料理份數不合法';
        if (!$image) $errors[] = '請上傳圖片';
    
        if (!empty($errors)) throw new Exception('欄位驗證失敗：' . implode('；', $errors), 400);
    }

    $sql = "UPDATE recipe SET " . implode(', ', $fields_to_update) . " WHERE recipe_id = '{$recipe_id_safe}' AND user_id = '{$user_id_safe}'";
    $result = $mysqli->query($sql);

    if (!$result) {
        throw new Exception('資料更新失敗：' . $mysqli->error, 500);
    }
    
    send_json(['status' => 'success', 'message' => '食譜已更新']);
    
} catch (Throwable $e) {
    $code = $e->getCode() ?: 500;
    $code = ($code >= 400 && $code < 600) ? $code : 500;
    send_json(['status' => 'fail', 'message' => $e->getMessage()], $code);
} finally {
    if (isset($mysqli)) $mysqli->close();
}