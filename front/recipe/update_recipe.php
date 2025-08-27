<?php
// --- 開啟錯誤回報，方便開發時除錯 ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 引入必要的檔案 ---
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

// --- 開始資料庫交易 ---
$mysqli->begin_transaction();

try {
    // --- 基本請求驗證 ---
    require_method('POST');
    $data = get_json_input();
    
    // --- 安全地啟動 Session ---
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('使用者未登入', 401);
    }
    
    // --- 驗證食譜 ID ---
    $recipe_id = isset($data['recipe_id']) && is_numeric($data['recipe_id']) ? (int)$data['recipe_id'] : null;
    if (!$recipe_id) {
        throw new Exception('缺少食譜 ID', 400);
    }

    // --- 驗證食譜所有權 ---
    $stmt_check = $mysqli->prepare("SELECT status, image FROM recipe WHERE recipe_id = ? AND user_id = ?");
    $stmt_check->bind_param("ii", $recipe_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $cur = $result_check->fetch_assoc();
    $stmt_check->close();
    
    if (!$cur) {
        throw new Exception('找不到食譜或您無權限修改此食譜', 404);
    }
    
    // --- 步驟 1: 更新主食譜資料 (recipe table) ---
    // (這部分的邏輯與上一版相同)
    $set_clauses = [];
    $bind_values = [];
    $bind_types = '';
    $allowed_fields = ['recipe_category_id', 'name', 'content', 'serving', 'image', 'cooked_time', 'status', 'tag', 'manager_id'];
    foreach ($allowed_fields as $k) {
        if (array_key_exists($k, $data)) {
            $val = is_string($data[$k]) ? trim($data[$k]) : $data[$k];
            if ($k === 'image' && is_string($val)) {
                $imageBaseUrl = IMG_BASE_URL . '/'; 
                if (strpos($val, $imageBaseUrl) === 0) {
                    $val = str_replace($imageBaseUrl, '', $val);
                }
            }
            $set_clauses[] = "`{$k}` = ?";
            $bind_values[] = $val;
            $bind_types .= in_array($k, ['recipe_category_id', 'status', 'manager_id']) ? 'i' : 's';
        }
    }
    if (!empty($set_clauses)) {
        $sql = "UPDATE recipe SET " . implode(', ', $set_clauses) . " WHERE recipe_id = ? AND user_id = ?";
        $bind_values[] = $recipe_id;
        $bind_values[] = $user_id;
        $bind_types .= 'ii';
        $stmt_update = $mysqli->prepare($sql);
        $stmt_update->bind_param($bind_types, ...$bind_values);
        if (!$stmt_update->execute()) {
            throw new Exception('主食譜資料更新失敗：' . $stmt_update->error, 500);
        }
        $stmt_update->close();
    }

    // --- ✅ 步驟 2: 更新食材 (ingredient_item table) ---
    // 2a. 先刪除所有與此食譜相關的舊食材
    $stmt_delete_ing = $mysqli->prepare("DELETE FROM ingredient_item WHERE recipe_id = ?");
    $stmt_delete_ing->bind_param("i", $recipe_id);
    $stmt_delete_ing->execute();
    $stmt_delete_ing->close();

    // 2b. 插入從前端傳來的新食材
    if (isset($data['ingredients']) && is_array($data['ingredients'])) {
        $stmt_insert_ing = $mysqli->prepare("INSERT INTO ingredient_item (recipe_id, name, serving) VALUES (?, ?, ?)");
        foreach ($data['ingredients'] as $ingredient) {
            if (!empty($ingredient['name']) && !empty($ingredient['amount'])) {
                $stmt_insert_ing->bind_param("iss", $recipe_id, $ingredient['name'], $ingredient['amount']);
                $stmt_insert_ing->execute();
            }
        }
        $stmt_insert_ing->close();
    }

    // --- ✅ 步驟 3: 更新料理步驟 (steps table) ---
    // 3a. 先刪除所有與此食譜相關的舊步驟
    $stmt_delete_steps = $mysqli->prepare("DELETE FROM steps WHERE recipe_id = ?");
    $stmt_delete_steps->bind_param("i", $recipe_id);
    $stmt_delete_steps->execute();
    $stmt_delete_steps->close();

    // 3b. 插入從前端傳來的新步驟
    if (isset($data['steps']) && is_array($data['steps'])) {
        $stmt_insert_steps = $mysqli->prepare("INSERT INTO steps (recipe_id, `order`, content) VALUES (?, ?, ?)");
        foreach ($data['steps'] as $index => $step_content) {
            if (!empty(trim($step_content))) {
                $order = $index + 1; // 順序從 1 開始
                $stmt_insert_steps->bind_param("iis", $recipe_id, $order, $step_content);
                $stmt_insert_steps->execute();
            }
        }
        $stmt_insert_steps->close();
    }

    // --- 如果所有操作都成功，提交交易 ---
    $mysqli->commit();
    
    send_json(['status' => 'success', 'message' => '食譜已成功更新']);
    
} catch (Throwable $e) {
    // --- 如果任何步驟出錯，撤銷所有操作 ---
    $mysqli->rollback();
    
    $code = $e->getCode() ?: 500;
    $code = ($code >= 400 && $code < 600) ? $code : 500;
    send_json(['status' => 'fail', 'message' => $e->getMessage()], $code);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}