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
    // 💡 替換成 mysqli_query
    $sql_check = "SELECT status, image FROM recipe WHERE recipe_id = {$recipe_id} AND user_id = {$user_id}";
    $result_check = $mysqli->query($sql_check);
    
    if (!$result_check) {
        throw new Exception('查詢食譜所有權失敗: ' . $mysqli->error, 500);
    }
    
    $cur = $result_check->fetch_assoc();
    $result_check->free();
    
    if (!$cur) {
        throw new Exception('找不到食譜或您無權限修改此食譜', 404);
    }
    
    // --- 步驟 1: 更新主食譜資料 (recipe table) ---
    $set_clauses = [];
    $allowed_fields = ['recipe_category_id', 'name', 'content', 'serving', 'image', 'cooked_time', 'status', 'tag', 'manager_id'];
    foreach ($allowed_fields as $k) {
        if (array_key_exists($k, $data)) {
            $val = is_string($data[$k]) ? trim($data[$k]) : $data[$k];
            
            // 處理圖片 URL
            if ($k === 'image' && is_string($val)) {
                $imageBaseUrl = IMG_BASE_URL . '/'; 
                if (strpos($val, $imageBaseUrl) === 0) {
                    $val = str_replace($imageBaseUrl, '', $val);
                }
            }
            
            // 💡 使用 real_escape_string 處理字串
            $safe_val = is_string($val) ? "'" . $mysqli->real_escape_string($val) . "'" : (is_null($val) ? "NULL" : $val);
            $set_clauses[] = "`{$k}` = {$safe_val}";
        }
    }
    
    if (!empty($set_clauses)) {
        // 💡 替換成 mysqli_query
        $sql = "UPDATE recipe SET " . implode(', ', $set_clauses) . " WHERE recipe_id = {$recipe_id} AND user_id = {$user_id}";
        if (!$mysqli->query($sql)) {
            throw new Exception('主食譜資料更新失敗：' . $mysqli->error, 500);
        }
    }

    // --- ✅ 步驟 2: 更新食材 (ingredient_item table) ---
    // 💡 2a. 先刪除所有與此食譜相關的舊食材
    $mysqli->query("DELETE FROM ingredient_item WHERE recipe_id = {$recipe_id}");
    if ($mysqli->errno) {
        throw new Exception('刪除舊食材失敗: ' . $mysqli->error, 500);
    }

    // 💡 2b. 插入從前端傳來的新食材
    if (isset($data['ingredients']) && is_array($data['ingredients'])) {
        foreach ($data['ingredients'] as $ingredient) {
            if (!empty($ingredient['name']) && !empty($ingredient['amount'])) {
                $safe_name = $mysqli->real_escape_string($ingredient['name']);
                $safe_amount = $mysqli->real_escape_string($ingredient['amount']);
                $mysqli->query("INSERT INTO ingredient_item (recipe_id, name, serving) VALUES ({$recipe_id}, '{$safe_name}', '{$safe_amount}')");
                if ($mysqli->errno) {
                    throw new Exception('新增食材失敗: ' . $mysqli->error, 500);
                }
            }
        }
    }

    // --- ✅ 步驟 3: 更新料理步驟 (steps table) ---
    // 💡 3a. 先刪除所有與此食譜相關的舊步驟
    $mysqli->query("DELETE FROM steps WHERE recipe_id = {$recipe_id}");
    if ($mysqli->errno) {
        throw new Exception('刪除舊步驟失敗: ' . $mysqli->error, 500);
    }

    // 💡 3b. 插入從前端傳來的新步驟
    if (isset($data['steps']) && is_array($data['steps'])) {
        foreach ($data['steps'] as $index => $step_content) {
            if (!empty(trim($step_content))) {
                $order = $index + 1; // 順序從 1 開始
                $safe_content = $mysqli->real_escape_string($step_content);
                $mysqli->query("INSERT INTO steps (recipe_id, `order`, content) VALUES ({$recipe_id}, {$order}, '{$safe_content}')");
                if ($mysqli->errno) {
                    throw new Exception('新增步驟失敗: ' . $mysqli->error, 500);
                }
            }
        }
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