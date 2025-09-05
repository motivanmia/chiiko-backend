<?php

//刪除食譜功能
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 【修正 1】將請求方法改為 'POST'，以匹配前端 Vue 的 axios 請求
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // 設定回應標頭為 JSON
    header('Content-Type: application/json');

    try {
        // 接收前端傳來的 JSON 資料
        $data = json_decode(file_get_contents('php://input'), true);

        // 驗證 recipe_id 是否存在且為有效的數字
        if (!isset($data['recipe_id']) || !is_numeric($data['recipe_id'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'fail', 'message' => '未提供有效的食譜 ID']);
            exit();
        }

        $recipe_id = (int)$data['recipe_id'];

        // 【修正 2】使用資料庫交易，確保資料一致性
        $mysqli->begin_transaction();

        $stmt0->bind_param("i", $recipe_id);
        $stmt0->execute();
        $stmt0->close();

        // 1. 刪除相關的食材項目 (ingredient_item)
        // 【修正 3】使用預備敘述防止 SQL 注入
        $stmt1 = $mysqli->prepare("DELETE FROM `ingredient_item` WHERE `recipe_id` = ?");
        $stmt1->bind_param("i", $recipe_id);
        $stmt1->execute();
        $stmt1->close();

        // 2. 刪除相關的步驟 (steps)
        $stmt2 = $mysqli->prepare("DELETE FROM `steps` WHERE `recipe_id` = ?");
        $stmt2->bind_param("i", $recipe_id);
        $stmt2->execute();
        $stmt2->close();

        // 3. 最後刪除主食譜 (recipe)
        $stmt3 = $mysqli->prepare("DELETE FROM `recipe` WHERE `recipe_id` = ?");
        $stmt3->bind_param("i", $recipe_id);
        $stmt3->execute();

        // 檢查主食譜是否真的被刪除，如果沒有，代表 ID 不存在
        if ($stmt3->affected_rows === 0) {
            // 拋出例外，這會觸發下面的 catch 區塊並回滾交易
            throw new Exception('找不到指定的食譜ID，任何資料都未被刪除。');
        }
        $stmt3->close();

        // 如果以上所有 SQL 都成功執行，則提交交易
        $mysqli->commit();

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => '食譜刪除成功！']);

    } catch (Throwable $e) {
        // 如果 try 區塊中有任何錯誤，回滾所有資料庫操作
        $mysqli->rollback();

        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'fail', 'message' => '刪除食譜失敗: ' . $e->getMessage()]);
    } finally {
        // 無論成功或失敗，最後都關閉連線
        if (isset($mysqli)) {
            $mysqli->close();
        }
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => '僅允許 POST 方法']);
}
?>